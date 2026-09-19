<?php
/**
 * Override do template nativo "Packages" (frontend-submission-page)
 * com o layout de Planos & Assinaturas alimentado pelos pacotes reais
 * (houzez_packages) do projeto.
 */

$paid_submission_type = esc_html( houzez_option( 'enable_paid_submission', '' ) );
if ( $paid_submission_type != 'membership' ) {
    wp_redirect( home_url() );
    exit;
}

get_header();

$page_content = '';
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        $page_content = get_the_content();
    }
}
wp_reset_postdata();

$plans_groups = function_exists( 'houzez_child_get_plans_groups' ) ? houzez_child_get_plans_groups() : array(
    'agent'  => array(),
    'agency' => array(),
    'table'  => array(),
);

$currency_symbol = houzez_option( 'currency_symbol' );
$where_currency  = houzez_option( 'currency_position' );
if ( class_exists( 'Houzez_Currencies' ) ) {
    $multi_currency   = houzez_option( 'multi_currency' );
    $default_currency = houzez_option( 'default_multi_currency' );
    if ( empty( $default_currency ) ) {
        $default_currency = 'USD';
    }
    if ( $multi_currency == 1 ) {
        $currency        = Houzez_Currencies::get_currency_by_code( $default_currency );
        $currency_symbol = $currency['currency_symbol'];
    }
}
$payment_page_link = houzez_get_template_link( 'template/template-payment.php' );
$use_woocommerce   = function_exists( 'houzez_is_woocommerce' ) && houzez_is_woocommerce();
$brand_name        = get_bloginfo( 'name' );

$ipc_check_svg = '<span class="ipc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"></path></svg></span>';
$ipc_cross_svg = '<span class="ipc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"></path></svg></span>';

/**
 * Linha de recurso do card (check verde/rosa ou item desativado).
 */
function ipc_plans_feature_row( $label, $value, $check_svg, $cross_svg, $enabled = true ) {
    if ( $enabled ) {
        return '<li>' . $check_svg . '<span>' . $label . ': <strong>' . esc_html( $value ) . '</strong></span></li>';
    }
    return '<li class="is-off">' . $cross_svg . '<span>' . esc_html( $label ) . '</span></li>';
}

/**
 * Renderiza um card de plano.
 */
function ipc_plans_render_card( $plan, $kicker, $segment, $currency_symbol, $where_currency, $use_woocommerce, $payment_page_link, $check_svg, $cross_svg ) {
    $is_featured = ! empty( $plan['popular'] ) && empty( $plan['is_free'] ) && empty( $plan['price_on_request'] );
    ?>
    <article class="ipc-plan-card<?php echo $is_featured ? ' is-featured' : ''; ?>">
        <?php if ( $is_featured ) : ?>
            <div class="ipc-plan-flag">Mais Escolhido</div>
        <?php endif; ?>
        <div class="ipc-plan-top">
            <span class="ipc-plan-kicker"><?php echo esc_html( $kicker ); ?></span>
            <h3 class="ipc-plan-name"><?php echo esc_html( $plan['title'] ); ?></h3>
            <div class="ipc-plan-price<?php echo $plan['is_free'] ? ' is-free' : ''; ?>">
                <?php if ( $plan['is_free'] ) : ?>
                    <span class="ipc-amount">Grátis</span>
                <?php elseif ( $plan['price_on_request'] ) : ?>
                    <span class="ipc-amount" style="font-size:32px;">Sob consulta</span>
                <?php elseif ( 'before' === $where_currency ) : ?>
                    <span class="ipc-currency"><?php echo esc_html( $currency_symbol ); ?></span>
                    <span class="ipc-amount"><?php echo esc_html( $plan['price_label'] ); ?></span>
                    <span class="ipc-plan-per">/mês</span>
                <?php else : ?>
                    <span class="ipc-amount"><?php echo esc_html( $plan['price_label'] ); ?></span>
                    <span class="ipc-currency"><?php echo esc_html( $currency_symbol ); ?></span>
                    <span class="ipc-plan-per">/mês</span>
                <?php endif; ?>
            </div>
            <p class="ipc-plan-note<?php echo ( ! $plan['is_free'] && ! $plan['price_on_request'] ) ? ' is-ok' : ''; ?>">
                <?php
                if ( $plan['is_free'] ) {
                    echo 'Sem compromisso e sem cartão de crédito';
                } elseif ( $plan['price_on_request'] ) {
                    echo 'Fale com nossa equipe comercial';
                } else {
                    echo 'Cobrança mensal';
                }
                ?>
            </p>
        </div>
        <ul class="ipc-plan-features">
            <li>
                <?php echo $check_svg; ?>
                <span>Validade: <strong><?php echo esc_html( $plan['validity_label'] ); ?></strong></span>
            </li>
            <?php if ( 'agency' === $segment && $plan['max_agents'] > 0 ) : ?>
                <li>
                    <?php echo $check_svg; ?>
                    <span>Corretores: <strong>Até <?php echo esc_html( number_format_i18n( $plan['max_agents'] ) ); ?></strong></span>
                </li>
            <?php endif; ?>
            <li>
                <?php echo $check_svg; ?>
                <span>Propriedades:
                    <?php if ( $plan['unlimited'] ) : ?>
                        <strong>Anúncios ilimitados</strong>
                    <?php elseif ( null !== $plan['listings'] ) : ?>
                        <strong><?php echo esc_html( $plan['listings'] ); ?></strong>
                    <?php else : ?>
                        <strong>—</strong>
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <?php echo $check_svg; ?>
                <span>Anúncios em destaque:
                    <?php if ( null !== $plan['featured'] ) : ?>
                        <strong><?php echo esc_html( $plan['featured'] ); ?> inclusos</strong>
                    <?php else : ?>
                        <strong>—</strong>
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <?php echo $check_svg; ?>
                <span>Imagens:
                    <?php if ( null !== $plan['images'] ) : ?>
                        <strong><?php echo esc_html( $plan['images'] ); ?> fotos</strong> por anúncio
                    <?php else : ?>
                        <strong>—</strong>
                    <?php endif; ?>
                </span>
            </li>
            <?php if ( 'agent' === $segment ) : ?>
                <?php
                $has_seal = ! $plan['is_free'];
                echo ipc_plans_feature_row( 'Selo de Corretor Verificado', '', $check_svg, $cross_svg, $has_seal );
                if ( $has_seal ) {
                    echo '<li>' . $check_svg . '<span><strong>Suporte prioritário via WhatsApp</strong></span></li>';
                }
                ?>
            <?php endif; ?>
        </ul>
        <div class="ipc-plan-cta">
            <?php if ( $plan['price_on_request'] ) : ?>
                <a class="ipc-request-quote ipc-plan-btn is-primary" data-packid="<?php echo esc_attr( $plan['id'] ); ?>" data-packname="<?php echo esc_attr( $plan['title'] ); ?>" href="#">Fale conosco</a>
            <?php elseif ( $use_woocommerce ) : ?>
                <a class="houzez-woocommerce-package ipc-plan-btn <?php echo $plan['is_free'] ? 'is-muted' : ( $is_featured ? 'is-gradient' : 'is-primary' ); ?>" data-packid="<?php echo esc_attr( $plan['id'] ); ?>" href="#">
                    <?php echo $plan['is_free'] ? 'Começar Teste Grátis' : 'Comece agora'; ?>
                </a>
            <?php else : ?>
                <a class="ipc-plan-btn <?php echo $plan['is_free'] ? 'is-muted' : 'is-primary'; ?>" href="<?php echo esc_url( add_query_arg( 'selected_package', $plan['id'], $payment_page_link ) ); ?>">
                    <?php echo $plan['is_free'] ? 'Começar Teste Grátis' : 'Comece agora'; ?>
                </a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * Células estáticas da tabela comparativa (linhas de infraestrutura).
 */
function ipc_plans_matrix_cell( $title, $row ) {
    $t = mb_strtolower( $title, 'UTF-8' );

    if ( 'xml' === $row ) {
        if ( false !== mb_strpos( $t, 'premium', 0, 'UTF-8' ) ) {
            return array( 'Incluso (Zap/VivaReal/OLX)', 'is-positive' );
        }
        if ( preg_match( '/\bpro\b/u', $t ) ) {
            return array( 'Opcional', '' );
        }
        return array( '—', 'is-dim' );
    }

    if ( 'support' === $row ) {
        if ( false !== mb_strpos( $t, 'premium', 0, 'UTF-8' ) ) {
            return array( 'Gerente Dedicado Exclusivo', 'is-positive' );
        }
        if ( preg_match( '/\bpro\b/u', $t ) ) {
            return array( 'WhatsApp Prioritário', '' );
        }
        if ( false !== mb_strpos( $t, 'corretor individual', 0, 'UTF-8' ) ) {
            return array( 'WhatsApp & E-mail', '' );
        }
        return array( 'Ticket & E-mail', '' );
    }

    return array( '—', 'is-dim' );
}

$user_role     = class_exists( 'Imovel_Parceiro_Package_Access' ) ? Imovel_Parceiro_Package_Access::target_role_for_user() : null;
$default_tab   = ( 'houzez_agency' === $user_role && ! empty( $plans_groups['agency'] ) ) ? 'agency' : 'agent';
$has_agent     = ! empty( $plans_groups['agent'] );
$has_agency    = ! empty( $plans_groups['agency'] );
$recommended_id = 0;
foreach ( $plans_groups['table'] as $table_plan ) {
    if ( ! empty( $table_plan['popular'] ) ) {
        $recommended_id = (int) $table_plan['id'];
        break;
    }
}
?>

<section class="frontend-submission-page">
    <div class="ipc-plans">
        <header class="ipc-plans-header">
            <div class="ipc-plans-header-inner">
                <div class="ipc-plans-badge">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path clip-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" fill-rule="evenodd"></path></svg>
                    Ecossistema Imobiliário de Alta Conversão
                </div>
                <h1 class="ipc-plans-title">Planos &amp; Assinaturas <span class="ipc-plans-brand"><?php echo esc_html( $brand_name ); ?></span></h1>
                <p class="ipc-plans-subtitle">Escolha a estrutura ideal para impulsionar suas captações, gerenciar múltiplos corretores e fechar transações com máxima visibilidade.</p>

                <?php if ( $has_agent && $has_agency ) : ?>
                    <div class="ipc-plans-tabs">
                        <nav class="ipc-plans-tabs-nav" aria-label="Segmentos de Planos" role="tablist">
                            <button type="button" role="tab" id="ipc-tab-agent" data-ipc-plans-tab="agent" aria-selected="<?php echo 'agent' === $default_tab ? 'true' : 'false'; ?>" aria-controls="ipc-view-agent" class="ipc-plans-tab<?php echo 'agent' === $default_tab ? ' is-active' : ''; ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                Para Corretores Autônomos
                            </button>
                            <button type="button" role="tab" id="ipc-tab-agency" data-ipc-plans-tab="agency" aria-selected="<?php echo 'agency' === $default_tab ? 'true' : 'false'; ?>" aria-controls="ipc-view-agency" class="ipc-plans-tab<?php echo 'agency' === $default_tab ? ' is-active' : ''; ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                Para Imobiliárias &amp; Equipes
                            </button>
                        </nav>
                    </div>
                <?php endif; ?>

                <p class="ipc-plans-billing-note">Faturamento <strong>mensal</strong> em todos os planos, sem fidelidade.</p>
            </div>
        </header>

        <main class="ipc-plans-main">
            <?php if ( ! empty( trim( $page_content ) ) ) : ?>
                <div class="ipc-plans-page-content">
                    <?php echo apply_filters( 'the_content', $page_content ); ?>
                </div>
            <?php endif; ?>

            <?php if ( ! $has_agent && ! $has_agency ) : ?>
                <div class="ipc-plans-empty">
                    <p>Nenhum plano disponível para o seu perfil no momento.</p>
                </div>
            <?php else : ?>
                <?php if ( $has_agent ) : ?>
                    <section id="ipc-view-agent" class="ipc-plans-view"<?php echo ( $has_agency && 'agency' === $default_tab ) ? ' hidden' : ''; ?> aria-labelledby="ipc-tab-agent">
                        <div class="ipc-plans-grid is-duo">
                            <?php
                            foreach ( $plans_groups['agent'] as $plan ) {
                                ipc_plans_render_card(
                                    $plan,
                                    $plan['is_free'] ? 'Degustação' : 'Autônomo Profissional',
                                    'agent',
                                    $currency_symbol,
                                    $where_currency,
                                    $use_woocommerce,
                                    $payment_page_link,
                                    $ipc_check_svg,
                                    $ipc_cross_svg
                                );
                            }
                            ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( $has_agency ) : ?>
                    <section id="ipc-view-agency" class="ipc-plans-view"<?php echo ( $has_agent && 'agent' === $default_tab ) ? ' hidden' : ''; ?> aria-labelledby="ipc-tab-agency">
                        <div class="ipc-plans-grid is-quartet">
                            <?php
                            foreach ( $plans_groups['agency'] as $plan ) {
                                ipc_plans_render_card(
                                    $plan,
                                    $plan['popular'] ? 'Recomendado' : 'Imobiliárias & Equipes',
                                    'agency',
                                    $currency_symbol,
                                    $where_currency,
                                    $use_woocommerce,
                                    $payment_page_link,
                                    $ipc_check_svg,
                                    $ipc_cross_svg
                                );
                            }
                            ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ! empty( $plans_groups['table'] ) ) : ?>
                <section class="ipc-plans-compare">
                    <div class="ipc-plans-section-title">
                        <h2>Tabela Comparativa de Recursos</h2>
                        <p>Veja os detalhes técnicos de cada modalidade e escolha a infraestrutura ideal para sua operação.</p>
                    </div>
                    <div class="ipc-plans-table-wrap">
                        <div class="ipc-plans-table-scroll">
                            <table class="ipc-plans-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Recurso &amp; Benefício</th>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <th scope="col"<?php echo (int) $table_plan['id'] === $recommended_id ? ' class="is-recommended"' : ''; ?>>
                                                <?php echo esc_html( $table_plan['title'] ); ?>
                                                <?php if ( (int) $table_plan['id'] === $recommended_id ) : ?>
                                                    (Recomendado)
                                                <?php endif; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Limite de Corretores Vinculados</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <td<?php echo (int) $table_plan['id'] === $recommended_id ? ' class="is-recommended"' : ''; ?>>
                                                <?php
                                                if ( $table_plan['max_agents'] > 0 ) {
                                                    echo 'Até ' . esc_html( number_format_i18n( $table_plan['max_agents'] ) );
                                                } else {
                                                    echo '1 usuário';
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Capacidade de Imóveis</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <?php $is_unlimited = ! empty( $table_plan['unlimited'] ); ?>
                                            <td<?php echo (int) $table_plan['id'] === $recommended_id ? ' class="is-recommended' . ( $is_unlimited ? ' is-positive' : '' ) . '"' : ( $is_unlimited ? ' class="is-positive"' : '' ); ?>>
                                                <?php
                                                if ( $is_unlimited ) {
                                                    echo 'Ilimitado';
                                                } elseif ( null !== $table_plan['listings'] ) {
                                                    echo esc_html( $table_plan['listings'] );
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Anúncios com Selo Super Destaque</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <td<?php echo (int) $table_plan['id'] === $recommended_id ? ' class="is-recommended"' : ''; ?>>
                                                <?php echo null !== $table_plan['featured'] ? esc_html( $table_plan['featured'] ) . ' destaques' : '—'; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Fotos em Alta Resolução por Imóvel</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <td<?php echo (int) $table_plan['id'] === $recommended_id ? ' class="is-recommended"' : ''; ?>>
                                                <?php echo null !== $table_plan['images'] ? esc_html( $table_plan['images'] ) . ' fotos' : '—'; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Integração com Portais via XML</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <?php
                                            list( $xml_label, $xml_class ) = ipc_plans_matrix_cell( $table_plan['title'], 'xml' );
                                            $xml_classes = trim( ( (int) $table_plan['id'] === $recommended_id ? 'is-recommended ' : '' ) . $xml_class );
                                            ?>
                                            <td<?php echo '' !== $xml_classes ? ' class="' . esc_attr( $xml_classes ) . '"' : ''; ?>><?php echo esc_html( $xml_label ); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Canal de Atendimento &amp; Suporte</td>
                                        <?php foreach ( $plans_groups['table'] as $table_plan ) : ?>
                                            <?php
                                            list( $support_label, $support_class ) = ipc_plans_matrix_cell( $table_plan['title'], 'support' );
                                            $support_classes = trim( ( (int) $table_plan['id'] === $recommended_id ? 'is-recommended ' : '' ) . $support_class );
                                            ?>
                                            <td<?php echo '' !== $support_classes ? ' class="' . esc_attr( $support_classes ) . '"' : ''; ?>><?php echo esc_html( $support_label ); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="ipc-plans-trust">
                <div class="ipc-plans-trust-item">
                    <div class="ipc-plans-trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </div>
                    <h4>Garantia Incondicional de 7 Dias</h4>
                    <p>Não aprovou? Devolvemos 100% do seu investimento sem questionamentos.</p>
                </div>
                <div class="ipc-plans-trust-item">
                    <div class="ipc-plans-trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </div>
                    <h4>Pagamento Seguro &amp; Flexível</h4>
                    <p>PIX com ativação imediata, Boleto Bancário ou Cartão de Crédito em até 12x.</p>
                </div>
                <div class="ipc-plans-trust-item">
                    <div class="ipc-plans-trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </div>
                    <h4>Criptografia de Ponta a Ponta</h4>
                    <p>Seus dados e leads são estritamente confidenciais e protegidos pela LGPD.</p>
                </div>
            </section>

            <section class="ipc-plans-faq">
                <div class="ipc-plans-faq-head">
                    <span class="ipc-plans-faq-kicker">Esclarecimentos</span>
                    <h2>Perguntas Frequentes (FAQ)</h2>
                    <p>Dúvidas sobre os planos? Veja as respostas abaixo.</p>
                </div>
                <div class="ipc-faq-item">
                    <button type="button" class="ipc-faq-toggle" aria-expanded="false">
                        <span>Como funciona o plano "Gratuito para Teste"?</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="ipc-faq-content" hidden>
                        O plano gratuito tem duração integral de 3 meses. Você pode cadastrar imóveis ilimitados, usar 5 destaques e 15 fotos por propriedade, sem precisar informar dados de pagamento na ativação.
                    </div>
                </div>
                <div class="ipc-faq-item">
                    <button type="button" class="ipc-faq-toggle" aria-expanded="false">
                        <span>Posso trocar de plano ou cancelar a qualquer momento?</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="ipc-faq-content" hidden>
                        Sim! Os planos mensais não possuem taxa de cancelamento ou contrato de fidelidade. Você gerencia sua assinatura diretamente pelo painel, na página de informações da assinatura.
                    </div>
                </div>
                <div class="ipc-faq-item">
                    <button type="button" class="ipc-faq-toggle" aria-expanded="false">
                        <span>Como são adicionados os corretores da equipe?</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="ipc-faq-content" hidden>
                        Nas contas de imobiliária, o administrador convida os corretores pelo painel. Cada corretor ganha seu próprio acesso para cadastrar imóveis e receber leads, respeitando o limite de corretores do plano contratado.
                    </div>
                </div>
                <div class="ipc-faq-item">
                    <button type="button" class="ipc-faq-toggle" aria-expanded="false">
                        <span>Quais as formas de pagamento disponíveis?</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <div class="ipc-faq-content" hidden>
                        Aceitamos PIX com ativação imediata, cartões de crédito em até 12 parcelas e boleto bancário para assinaturas.
                    </div>
                </div>
            </section>
        </main>
    </div>
</section>

<?php get_template_part( 'template-parts/membership/quote-modal' ); ?>

<?php get_footer(); ?>
