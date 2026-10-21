<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() || ! class_exists( 'IPC_Notifications' ) ) {
    return;
}

$service = IPC_Notifications::instance();
$user_id = get_current_user_id();
$status_filter = isset( $_GET['ipc_notification_filter'] ) ? sanitize_key( wp_unslash( $_GET['ipc_notification_filter'] ) ) : 'all';
$category_filter = isset( $_GET['ipc_notification_category'] ) ? sanitize_key( wp_unslash( $_GET['ipc_notification_category'] ) ) : '';
$paged = isset( $_GET['ipc_notification_paged'] ) ? absint( wp_unslash( $_GET['ipc_notification_paged'] ) ) : 1;

if ( ! in_array( $status_filter, array( 'all', 'unread' ), true ) ) {
    $status_filter = 'all';
}

$items = $service->list_notifications(
    $user_id,
    array(
        'status' => $status_filter,
        'category' => $category_filter,
        'paged' => $paged,
        'per_page' => 20,
    )
);
$total_items = $service->count_notifications( $user_id, $status_filter, $category_filter );
$per_page = 20;
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

$unread_count = $service->unread_count( $user_id );
$base_url = IPC_Notifications::get_dashboard_url();
$filters = array(
    'all' => __( 'Todas', 'imovel-parceiro-core' ),
    'unread' => __( 'Não lidas', 'imovel-parceiro-core' ),
);
$categories = array(
    '' => __( 'Todos os tipos', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PROPRIEDADES => __( 'Imóveis', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_DOCUMENTACAO => __( 'Documentação', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PARCERIAS => __( 'Parcerias', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PROPOSTAS => __( 'Propostas', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_VISITAS => __( 'Visitas', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_NEGOCIACOES => __( 'Negociações', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_SISTEMA => __( 'Sistema', 'imovel-parceiro-core' ),
);

/**
 * Meta visual de cada categoria: rótulo, ícone e "tom" de cor do card.
 */
$ipc_cat_meta = array(
    IPC_Notifications::CATEGORY_PROPRIEDADES => array( 'icon' => 'house', 'tone' => 'imoveis' ),
    IPC_Notifications::CATEGORY_DOCUMENTACAO => array( 'icon' => 'file-text', 'tone' => 'documentacao' ),
    IPC_Notifications::CATEGORY_PARCERIAS => array( 'icon' => 'handshake', 'tone' => 'parcerias' ),
    IPC_Notifications::CATEGORY_PROPOSTAS => array( 'icon' => 'receipt', 'tone' => 'propostas' ),
    IPC_Notifications::CATEGORY_VISITAS => array( 'icon' => 'calendar-days', 'tone' => 'visitas' ),
    IPC_Notifications::CATEGORY_NEGOCIACOES => array( 'icon' => 'credit-card', 'tone' => 'negociacoes' ),
    IPC_Notifications::CATEGORY_SISTEMA => array( 'icon' => 'settings', 'tone' => 'sistema' ),
);

/**
 * Rótulo de tempo relativo ("há 5 min") a partir do datetime local do banco.
 *
 * @param string $raw Data MySQL (horário local do site).
 * @return array{ts:int,rel:string,when:string}
 */
$ipc_notif_time = function ( $raw ) {
    $ts = time();
    $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', (string) $raw, wp_timezone() );

    if ( $dt instanceof DateTime ) {
        $ts = $dt->getTimestamp();
    }

    $diff = max( 0, time() - $ts );

    if ( $diff < 60 ) {
        $rel = __( 'agora', 'imovel-parceiro-core' );
    } elseif ( $diff < HOUR_IN_SECONDS ) {
        $rel = sprintf( __( 'há %d min', 'imovel-parceiro-core' ), (int) floor( $diff / 60 ) );
    } elseif ( $diff < DAY_IN_SECONDS ) {
        $rel = sprintf( __( 'há %d h', 'imovel-parceiro-core' ), (int) floor( $diff / HOUR_IN_SECONDS ) );
    } elseif ( $diff < WEEK_IN_SECONDS ) {
        $rel = sprintf( __( 'há %d d', 'imovel-parceiro-core' ), (int) floor( $diff / DAY_IN_SECONDS ) );
    } else {
        $rel = '';
    }

    // Data absoluta com o dia da semana relativo quando aplicável.
    $now       = new DateTime( 'now', wp_timezone() );
    $today     = $now->format( 'Y-m-d' );
    $yesterday = $now->modify( '-1 day' )->format( 'Y-m-d' );
    $day       = $dt instanceof DateTime ? $dt->format( 'Y-m-d' ) : '';
    $hour      = wp_date( 'H:i', $ts );

    if ( '' === $day ) {
        $when = '';
    } elseif ( $day === $today ) {
        $when = sprintf( __( 'Hoje, %s', 'imovel-parceiro-core' ), $hour );
    } elseif ( $day === $yesterday ) {
        $when = sprintf( __( 'Ontem, %s', 'imovel-parceiro-core' ), $hour );
    } else {
        $when = wp_date( 'j \d\e F \d\e Y, H:i', $ts );
    }

    return array( 'ts' => $ts, 'rel' => $rel, 'when' => $when );
};
?>

<div class="imovel-parceiro-notifications-dashboard">
    <header class="ipc-notif-header">
        <div class="ipc-notif-header__main">
            <div class="ipc-notif-header__id">
                <span class="ipc-notif-header__icon"><?php echo houzez_dash_icon( 'bell', 'h-5 w-5' ); ?></span>
                <div class="min-w-0">
                    <nav class="ipc-notif-crumbs" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'imovel-parceiro-core' ); ?>">
                        <span><?php esc_html_e( 'Painel', 'imovel-parceiro-core' ); ?></span>
                        <span aria-hidden="true">/</span>
                        <span class="is-current"><?php esc_html_e( 'Notificações', 'imovel-parceiro-core' ); ?></span>
                    </nav>
                    <h2 class="ipc-notif-header__title"><?php esc_html_e( 'Notificações', 'imovel-parceiro-core' ); ?></h2>
                    <p class="ipc-notif-header__sub">
                        <?php esc_html_e( 'Acompanhe os eventos relacionados aos seus imóveis, parcerias e documentação.', 'imovel-parceiro-core' ); ?>
                    </p>
                </div>
            </div>

            <div class="ipc-notif-header__actions">
                <button type="button" class="ipc-notif-action ipc-notif-action--ghost ipc-notif-enable-push">
                    <?php echo houzez_dash_icon( 'bell', 'h-4 w-4' ); ?>
                    <span><?php esc_html_e( 'Ativar notificações do navegador', 'imovel-parceiro-core' ); ?></span>
                </button>
                <button type="button" class="ipc-notif-action ipc-notif-action--primary ipc-notif-mark-all" <?php disabled( $unread_count < 1 ); ?>>
                    <?php echo houzez_dash_icon( 'circle-check', 'h-4 w-4' ); ?>
                    <span><?php esc_html_e( 'Marcar todas como lidas', 'imovel-parceiro-core' ); ?></span>
                </button>
                <button type="button" class="ipc-notif-action ipc-notif-action--danger ipc-notifications-delete-all" <?php disabled( ! ( $total_items > 0 ) ); ?>>
                    <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                    <span><?php esc_html_e( 'Excluir todas', 'imovel-parceiro-core' ); ?></span>
                </button>
            </div>
        </div>

        <div class="ipc-notif-stats">
            <div class="ipc-notif-stat">
                <span class="ipc-notif-stat__value"><?php echo esc_html( number_format_i18n( $total_items ) ); ?></span>
                <span class="ipc-notif-stat__label"><?php esc_html_e( 'no filtro atual', 'imovel-parceiro-core' ); ?></span>
            </div>
            <div class="ipc-notif-stat <?php echo $unread_count > 0 ? 'is-alert' : ''; ?>">
                <span class="ipc-notif-stat__value"><?php echo esc_html( number_format_i18n( $unread_count ) ); ?></span>
                <span class="ipc-notif-stat__label"><?php esc_html_e( 'não lidas', 'imovel-parceiro-core' ); ?></span>
            </div>
            <div class="ipc-notif-stat">
                <span class="ipc-notif-stat__value"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
                <span class="ipc-notif-stat__label"><?php esc_html_e( 'páginas', 'imovel-parceiro-core' ); ?></span>
            </div>
        </div>
    </header>

    <form method="get" class="ipc-notif-filters">
        <input type="hidden" name="imovel_dashboard_area" value="notificacoes" />
        <div class="ipc-notif-filters__field">
            <label for="ipc_notification_filter"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></label>
            <select id="ipc_notification_filter" name="ipc_notification_filter" class="ipc-notif-select">
                <?php foreach ( $filters as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ipc-notif-filters__field">
            <label for="ipc_notification_category"><?php esc_html_e( 'Categoria', 'imovel-parceiro-core' ); ?></label>
            <select id="ipc_notification_category" name="ipc_notification_category" class="ipc-notif-select">
                <?php foreach ( $categories as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ipc-notif-filters__actions">
            <button type="submit" class="ipc-notif-action ipc-notif-action--primary is-compact">
                <?php echo houzez_dash_icon( 'search', 'h-4 w-4' ); ?>
                <span><?php esc_html_e( 'Filtrar', 'imovel-parceiro-core' ); ?></span>
            </button>
            <a class="ipc-notif-action ipc-notif-action--ghost is-compact" href="<?php echo esc_url( $base_url ); ?>">
                <?php esc_html_e( 'Limpar', 'imovel-parceiro-core' ); ?>
            </a>
        </div>
    </form>

    <div class="ipc-notif-panel">
        <?php if ( empty( $items ) ) : ?>
            <div class="ipc-notif-empty">
                <span class="ipc-notif-empty__icon"><?php echo houzez_dash_icon( 'inbox', 'h-6 w-6' ); ?></span>
                <p class="ipc-notif-empty__title"><?php esc_html_e( 'Nenhuma notificação encontrada.', 'imovel-parceiro-core' ); ?></p>
                <p class="ipc-notif-empty__text">
                    <?php
                    if ( 'all' !== $status_filter || '' !== $category_filter ) {
                        esc_html_e( 'Ajuste os filtros ou limpe a busca para ver todas as notificações.', 'imovel-parceiro-core' );
                    } else {
                        esc_html_e( 'Quando houver novidades sobre seus imóveis e parcerias, elas aparecem aqui.', 'imovel-parceiro-core' );
                    }
                    ?>
                </p>
            </div>
        <?php else : ?>
            <div class="ipc-notif-panel__body">
                <?php foreach ( $items as $item ) : ?>
                    <?php
                    $row = $service->format_notification( $item );
                    $meta_cat = isset( $ipc_cat_meta[ $row['category'] ] ) ? $ipc_cat_meta[ $row['category'] ] : array( 'icon' => 'bell', 'tone' => 'sistema' );
                    $cat_label = isset( $categories[ $row['category'] ] ) ? $categories[ $row['category'] ] : __( 'Sistema', 'imovel-parceiro-core' );
                    $time_info = $ipc_notif_time( $row['created_at_raw'] );
                    $is_unread = ! $row['is_read'];
                    $is_critical = 'critical' === $row['priority'];
                    ?>

                    <article class="ipc-notif-row ipc-notif-tone-<?php echo esc_attr( $meta_cat['tone'] ); ?> <?php echo $is_unread ? 'is-unread' : 'is-read'; ?> <?php echo $is_critical ? 'is-critical' : ''; ?>" data-notification-id="<?php echo esc_attr( $row['id'] ); ?>"<?php echo $row['url'] ? ' data-href="' . esc_url( $row['url'] ) . '"' : ''; ?>>
                        <span class="ipc-notif-row__icon"><?php echo houzez_dash_icon( $meta_cat['icon'], 'h-4 w-4' ); ?></span>

                        <div class="ipc-notif-row__body">
                            <div class="ipc-notif-row__head">
                                <h3 class="ipc-notif-row__title">
                                    <?php if ( $row['url'] ) : ?>
                                        <a class="ipc-notif-row__link ipc-notification-item" href="<?php echo esc_url( $row['url'] ); ?>" data-notification-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $row['title'] ); ?>
                                    <?php endif; ?>
                                </h3>
                                <span class="ipc-notif-chip ipc-notif-tone-<?php echo esc_attr( $meta_cat['tone'] ); ?>"><?php echo esc_html( $cat_label ); ?></span>
                                <?php if ( $is_unread ) : ?>
                                    <span class="ipc-notif-new"><?php esc_html_e( 'Nova', 'imovel-parceiro-core' ); ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="ipc-notif-row__text" data-ipc-notif-text><?php echo esc_html( $row['message'] ); ?></p>
                            <button type="button" class="ipc-notif-row__more" data-ipc-notif-more hidden><?php esc_html_e( 'Ler mensagem completa', 'imovel-parceiro-core' ); ?></button>

                            <div class="ipc-notif-row__meta">
                                <span class="ipc-notif-row__time">
                                    <?php echo houzez_dash_icon( 'calendar-days', 'h-3.5 w-3.5' ); ?>
                                    <?php if ( '' !== $time_info['rel'] ) : ?>
                                        <?php echo esc_html( $time_info['rel'] ); ?>
                                    <?php else : ?>
                                        <?php echo esc_html( $time_info['when'] ); ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ( '' !== $time_info['rel'] && '' !== $time_info['when'] ) : ?>
                                    <span class="ipc-notif-row__sep" aria-hidden="true">•</span>
                                    <span class="ipc-notif-row__abs"><?php echo esc_html( $time_info['when'] ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ipc-notif-row__side">
                            <button type="button" class="ipc-notification-delete" data-notification-id="<?php echo esc_attr( $row['id'] ); ?>" aria-label="<?php esc_attr_e( 'Excluir notificação', 'imovel-parceiro-core' ); ?>">
                                <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="ipc-notif-pagination">
                    <span class="ipc-notif-pagination__info">
                        <?php
                        echo esc_html(
                            sprintf(
                                __( 'Mostrando %1$d de %2$d notificações', 'imovel-parceiro-core' ),
                                (int) ( ( $paged - 1 ) * $per_page ) + 1,
                                (int) min( $total_items, ( $paged * $per_page ) )
                            )
                        );
                        ?>
                    </span>
                    <div class="ipc-notif-pagination__nav">
                        <?php if ( $paged > 1 ) : ?>
                            <a class="ipc-notif-action ipc-notif-action--ghost is-compact" href="<?php echo esc_url( add_query_arg( array( 'imovel_dashboard_area' => 'notificacoes', 'ipc_notification_filter' => $status_filter, 'ipc_notification_category' => $category_filter, 'ipc_notification_paged' => $paged - 1 ), $base_url ) ); ?>">
                                <?php echo houzez_dash_icon( 'arrow-left', 'h-3.5 w-3.5' ); ?>
                                <span><?php esc_html_e( 'Anterior', 'imovel-parceiro-core' ); ?></span>
                            </a>
                        <?php endif; ?>
                        <span class="ipc-notif-pagination__page">
                            <?php echo esc_html( sprintf( __( 'Página %1$d de %2$d', 'imovel-parceiro-core' ), max( 1, $paged ), $total_pages ) ); ?>
                        </span>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a class="ipc-notif-action ipc-notif-action--ghost is-compact" href="<?php echo esc_url( add_query_arg( array( 'imovel_dashboard_area' => 'notificacoes', 'ipc_notification_filter' => $status_filter, 'ipc_notification_category' => $category_filter, 'ipc_notification_paged' => $paged + 1 ), $base_url ) ); ?>">
                                <span><?php esc_html_e( 'Próxima', 'imovel-parceiro-core' ); ?></span>
                                <?php echo houzez_dash_icon( 'arrow-right', 'h-3.5 w-3.5' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
