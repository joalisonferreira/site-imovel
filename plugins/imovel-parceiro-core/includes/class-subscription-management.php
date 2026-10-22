<?php
/**
 * Gestão de assinaturas (área administrativa).
 *
 * Fase 1: consulta, diagnóstico e leitura das assinaturas do site.
 * Fase 2 (prevista): ações do administrador sobre assinaturas gratuitas
 * e sobre assinaturas com pendência de pagamento.
 *
 * @package Imovel_Parceiro_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Imovel_Parceiro_Subscription_Management {

	/**
	 * Capacidade para gerenciar assinaturas de terceiros.
	 */
	const CAP_MANAGE_SUBSCRIPTIONS = 'imovel_parceiro_manage_subscriptions';

	/**
	 * Controle de versão das capacidades (evita rodar a cada request).
	 */
	const CAPS_VERSION_OPTION = 'imovel_parceiro_subscription_caps_version';
	const CAPS_VERSION        = '1.0.0';

	/**
	 * Meta que liga o produto WooCommerce ao pacote do Houzez.
	 */
	const PRODUCT_PACKAGE_META = '_imovel_parceiro_houzez_package_id';

	/**
	 * Meta que liga o pacote do Houzez ao produto WooCommerce.
	 */
	const PACKAGE_PRODUCT_META = '_imovel_parceiro_subscription_product_id';

	/**
	 * Meta com a data de término do plano gratuito.
	 */
	const FREE_EXPIRY_META = '_imovel_parceiro_free_plan_expires_at';

	/**
	 * @var Imovel_Parceiro_Subscription_Management|null
	 */
	private static $instance = null;

	/**
	 * Retorna a instância única.
	 *
	 * @return Imovel_Parceiro_Subscription_Management
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'ensure_capabilities' ) );
	}

	/* ---------------------------------------------------------------------
	 * Capacidades
	 * ------------------------------------------------------------------ */

	/**
	 * Dá a capacidade de gestão de assinaturas aos papéis administrativos.
	 */
	public function ensure_capabilities() {
		if ( get_option( self::CAPS_VERSION_OPTION ) === self::CAPS_VERSION ) {
			return;
		}

		foreach ( array( 'administrator', 'editor', 'houzez_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP_MANAGE_SUBSCRIPTIONS ) ) {
				$role->add_cap( self::CAP_MANAGE_SUBSCRIPTIONS );
			}
		}

		update_option( self::CAPS_VERSION_OPTION, self::CAPS_VERSION );
	}

	/**
	 * O usuário atual pode gerenciar assinaturas?
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::CAP_MANAGE_SUBSCRIPTIONS ) || current_user_can( 'manage_options' );
	}

	/* ---------------------------------------------------------------------
	 * Consulta
	 * ------------------------------------------------------------------ */

	/**
	 * Filtros disponíveis na listagem.
	 *
	 * @return array
	 */
	public static function status_options() {
		return array(
			'any'            => __( 'Todos os status', 'imovel-parceiro-core' ),
			'active'         => __( 'Ativas', 'imovel-parceiro-core' ),
			'pending-cancel' => __( 'Cancelamento agendado', 'imovel-parceiro-core' ),
			'on-hold'        => __( 'Suspensas', 'imovel-parceiro-core' ),
			'pending'        => __( 'Aguardando pagamento', 'imovel-parceiro-core' ),
			'failed'         => __( 'Pagamento falhou', 'imovel-parceiro-core' ),
			'cancelled'      => __( 'Canceladas', 'imovel-parceiro-core' ),
			'expired'        => __( 'Expiradas', 'imovel-parceiro-core' ),
			'trash'          => __( 'Excluídas', 'imovel-parceiro-core' ),
		);
	}

	/**
	 * Rótulos (PT-BR) dos status de assinatura.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'active'         => __( 'Ativa', 'imovel-parceiro-core' ),
			'pending-cancel' => __( 'Cancelamento agendado', 'imovel-parceiro-core' ),
			'on-hold'        => __( 'Suspensa', 'imovel-parceiro-core' ),
			'pending'        => __( 'Aguardando pagamento', 'imovel-parceiro-core' ),
			'failed'         => __( 'Pagamento falhou', 'imovel-parceiro-core' ),
			'cancelled'      => __( 'Cancelada', 'imovel-parceiro-core' ),
			'expired'        => __( 'Expirada', 'imovel-parceiro-core' ),
			'trash'          => __( 'Excluída', 'imovel-parceiro-core' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Classes de badge (Bootstrap) por status.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function status_badge( $status ) {
		$map = array(
			'active'         => 'bg-success',
			'pending-cancel' => 'bg-warning text-dark',
			'on-hold'        => 'bg-secondary',
			'pending'        => 'bg-info text-dark',
			'failed'         => 'bg-danger',
			'cancelled'      => 'bg-danger',
			'expired'        => 'bg-dark',
			'trash'          => 'bg-dark',
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : 'bg-secondary';
	}

	/**
	 * Pacotes (planos) vinculados a um produto de assinatura.
	 *
	 * @return array package_id => título
	 */
	public function package_options() {
		$options = array();

		$posts = get_posts(
			array(
				'post_type'      => 'houzez_packages',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$product_id = absint( get_post_meta( $post->ID, self::PACKAGE_PRODUCT_META, true ) );
			if ( ! $product_id ) {
				continue;
			}
			$options[ (int) $post->ID ] = $post->post_title;
		}

		return $options;
	}

	/**
	 * IDs de usuário que batem com a busca (nome, login ou e-mail).
	 *
	 * @param string $search
	 * @return int[]
	 */
	private function customer_ids_for_search( $search ) {
		$search = trim( (string) $search );
		if ( '' === $search ) {
			return array();
		}

		$query = new WP_User_Query(
			array(
				'number'        => 50,
				'search'        => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'fields'        => 'ID',
			)
		);

		return array_map( 'absint', (array) $query->get_results() );
	}

	/**
	 * IDs de produto dos planos de acordo com o filtro (plano específico e/ou
	 * tipo gratuito x pago).
	 *
	 * @param int   $package_id
	 * @param string $type 'free', 'paid' ou ''
	 * @return int[]
	 */
	private function product_ids_for_filter( $package_id, $type ) {
		$package_id = absint( $package_id );
		$type       = sanitize_key( (string) $type );

		if ( $package_id ) {
			$product_id = absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) );
			return $product_id ? array( $product_id ) : array();
		}

		if ( ! $type ) {
			return array();
		}

		$product_ids = array();
		foreach ( $this->package_options() as $pid => $title ) {
			$is_free = class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' )
				&& Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $pid );

			if ( 'free' === $type && ! $is_free ) {
				continue;
			}
			if ( 'paid' === $type && $is_free ) {
				continue;
			}

			$product_id = absint( get_post_meta( $pid, self::PACKAGE_PRODUCT_META, true ) );
			if ( $product_id ) {
				$product_ids[] = $product_id;
			}
		}

		return $product_ids;
	}

	/**
	 * Todos os status de assinatura (com prefixo wc-, como o WooCommerce
	 * grava no banco).
	 *
	 * @return array
	 */
	private function all_statuses() {
		return array(
			'wc-active',
			'wc-pending-cancel',
			'wc-on-hold',
			'wc-pending',
			'wc-failed',
			'wc-cancelled',
			'wc-expired',
			'wc-trash',
		);
	}

	/**
	 * Status da assinatura em formato aceito pela consulta (wc- prefixado).
	 *
	 * @param string $status
	 * @return array
	 */
	private function statuses_for_filter( $status ) {
		$status = sanitize_key( (string) $status );
		if ( ! $status || 'any' === $status ) {
			return $this->all_statuses();
		}

		$allowed = array_keys( self::status_options() );
		if ( ! in_array( $status, $allowed, true ) ) {
			return $this->all_statuses();
		}

		return array( 'wc-' . $status );
	}

	/**
	 * Busca as assinaturas conforme os filtros.
	 *
	 * @param array $filters
	 * @return array items, total, pages, per_page, paged
	 */
	public function query_subscriptions( $filters = array() ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'search'   => '',
				'status'   => 'any',
				'package'  => 0,
				'type'     => '',
				'per_page' => 25,
				'paged'    => 1,
			)
		);

		$per_page = (int) $filters['per_page'];
		$per_page = in_array( $per_page, array( 10, 25, 50, 100 ), true ) ? $per_page : 25;
		$paged    = max( 1, absint( $filters['paged'] ) );

		$statuses = $this->statuses_for_filter( $filters['status'] );

		$args = array(
			'type'    => 'shop_subscription',
			'status'  => $statuses,
			'limit'   => $per_page,
			'paged'   => $paged,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'ids',
		);

		$search = trim( (string) $filters['search'] );
		if ( '' !== $search ) {
			$customer_ids = $this->customer_ids_for_search( $search );
			if ( empty( $customer_ids ) ) {
				return array(
					'items'    => array(),
					'total'    => 0,
					'pages'    => 1,
					'per_page' => $per_page,
					'paged'    => $paged,
				);
			}
			$args['customer_id'] = $customer_ids;
		}

		$product_ids = $this->product_ids_for_filter( $filters['package'], $filters['type'] );
		if ( ! empty( $product_ids ) ) {
			$subscription_ids = wcs_get_subscriptions_for_product(
				$product_ids,
				'ids',
				array( 'subscription_status' => $statuses ? $statuses : array( 'any' ) )
			);
			$args['post__in'] = ! empty( $subscription_ids ) ? array_map( 'absint', $subscription_ids ) : array( 0 );
		}

		$query     = new WC_Order_Query( $args );
		$ids       = $query->get_orders();
		$total     = $this->count_matching( $args );

		$items = array();
		foreach ( (array) $ids as $id ) {
			$subscription = wcs_get_subscription( $id );
			if ( ! $subscription ) {
				continue;
			}
			$items[] = $this->prepare_row( $subscription );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'pages'    => max( 1, (int) ceil( $total / $per_page ) ),
			'per_page' => $per_page,
			'paged'    => $paged,
		);
	}

	/**
	 * Conta quantas assinaturas atendem aos mesmos filtros.
	 *
	 * A consulta do WooCommerce não expõe o total, então conta os IDs
	 * (a listagem de assinaturas do site é pequena).
	 *
	 * @param array $args
	 * @return int
	 */
	private function count_matching( $args ) {
		$args['limit']  = -1;
		$args['paged']  = 1;
		$args['return'] = 'ids';

		$query = new WC_Order_Query( $args );

		return count( (array) $query->get_orders() );
	}

	/**
	 * Monta os dados de exibição de uma assinatura.
	 *
	 * @param WC_Subscription $subscription
	 * @return array
	 */
	private function prepare_row( $subscription ) {
		$user_id = (int) $subscription->get_customer_id();
		$user    = $user_id ? get_userdata( $user_id ) : false;

		$product_id = 0;
		$package_id = 0;
		foreach ( $subscription->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$package_id = absint( get_post_meta( $product_id, self::PRODUCT_PACKAGE_META, true ) );
			if ( $package_id ) {
				break;
			}
		}

		$is_free = $package_id && class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' )
			&& Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $package_id );

		$status = $subscription->get_status();

		$expiry = 0;
		if ( $is_free && class_exists( 'Imovel_Parceiro_Free_Plan_Expiration' ) ) {
			$expiry = (int) Imovel_Parceiro_Free_Plan_Expiration::instance()->compute_expiry( $subscription, $package_id );
		}

		$next_payment = (int) $subscription->get_time( 'next_payment' );
		$ends_on      = (int) $subscription->get_time( 'end' );

		$role_labels = array(
			'houzez_agent'  => __( 'Corretor', 'imovel-parceiro-core' ),
			'houzez_agency' => __( 'Imobiliária', 'imovel-parceiro-core' ),
			'houzez_owner'  => __( 'Proprietário', 'imovel-parceiro-core' ),
			'houzez_seller' => __( 'Vendedor', 'imovel-parceiro-core' ),
			'houzez_buyer'  => __( 'Cliente', 'imovel-parceiro-core' ),
			'subscriber'    => __( 'Cliente', 'imovel-parceiro-core' ),
			'administrator' => __( 'Administrador', 'imovel-parceiro-core' ),
			'editor'        => __( 'Editor', 'imovel-parceiro-core' ),
		);

		$role = '';
		if ( $user ) {
			foreach ( (array) $user->roles as $user_role ) {
				if ( isset( $role_labels[ $user_role ] ) ) {
					$role = $role_labels[ $user_role ];
					break;
				}
			}
			if ( ! $role ) {
				$role = __( 'Sem papel', 'imovel-parceiro-core' );
			}
		}

		$interval = (int) $subscription->get_billing_interval();
		$period   = (string) $subscription->get_billing_period();

		return array(
			'id'            => (int) $subscription->get_id(),
			'status'        => $status,
			'status_label'  => self::status_label( $status ),
			'status_badge'  => self::status_badge( $status ),
			'user_id'       => $user_id,
			'user_name'     => $user ? $user->display_name : __( 'Usuário removido', 'imovel-parceiro-core' ),
			'user_email'    => $user ? $user->user_email : '',
			'user_role'     => $role,
			'package_id'    => $package_id,
			'package_title' => $package_id ? get_the_title( $package_id ) : __( 'Sem pacote vinculado', 'imovel-parceiro-core' ),
			'product_id'    => $product_id,
			'is_free'       => (bool) $is_free,
			'total'         => (float) $subscription->get_total(),
			'currency'      => $subscription->get_currency(),
			'interval'      => $interval,
			'period'        => $period,
			'cycle'         => $interval . ' ' . $period,
			'created'       => (int) $subscription->get_date_created() ? $subscription->get_date_created()->getTimestamp() : 0,
			'next_payment'  => $next_payment,
			'ends_on'       => $ends_on,
			'expiry'        => $expiry,
			'expired'       => $expiry ? ( $expiry <= time() ) : false,
			'needs_payment' => (bool) $subscription->needs_payment(),
			'payment_method'=> $subscription->get_payment_method_title(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Indicadores
	 * ------------------------------------------------------------------ */

	/**
	 * Contagem de assinaturas por status.
	 *
	 * @param string $status
	 * @return int
	 */
	private function count_by_status( $status ) {
		return $this->count_matching(
			array(
				'type'   => 'shop_subscription',
				'status' => array( 'wc-' . $status ),
			)
		);
	}

	/**
	 * Indicadores da área de assinaturas.
	 *
	 * @return array
	 */
	public function counters() {
		$counters = array(
			'active'         => $this->count_by_status( 'active' ),
			'pending_cancel' => $this->count_by_status( 'pending-cancel' ),
			'on_hold'        => $this->count_by_status( 'on-hold' ),
			'pending'        => $this->count_by_status( 'pending' ) + $this->count_by_status( 'failed' ),
			'cancelled'      => $this->count_by_status( 'cancelled' ) + $this->count_by_status( 'expired' ),
			'expired_active' => 0,
			'expiring_soon'  => 0,
			'total'          => 0,
		);

		// Diagnóstico dos planos gratuitos (validade vencida ou a vencer).
		$soon = time() + ( 30 * DAY_IN_SECONDS );
		$ids  = $this->free_subscription_ids();

		foreach ( $ids as $id ) {
			$subscription = wcs_get_subscription( $id );
			if ( ! $subscription ) {
				continue;
			}

			$counters['total']++;

			if ( ! in_array( $subscription->get_status(), array( 'active', 'pending-cancel' ), true ) ) {
				continue;
			}

			$expiry = (int) $subscription->get_time( 'end' );
			if ( ! $expiry ) {
				$expiry = (int) $subscription->get_time( 'next_payment' );
			}
			if ( ! $expiry ) {
				continue;
			}

			if ( $expiry <= time() ) {
				$counters['expired_active']++;
			} elseif ( $expiry <= $soon ) {
				$counters['expiring_soon']++;
			}
		}

		return $counters;
	}

	/**
	 * IDs das assinaturas dos planos gratuitos.
	 *
	 * @return int[]
	 */
	private function free_subscription_ids() {
		if ( ! function_exists( 'wcs_get_subscriptions_for_product' ) ) {
			return array();
		}

		$product_ids = array();
		foreach ( $this->package_options() as $package_id => $title ) {
			$is_free = class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' )
				&& Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $package_id );
			if ( ! $is_free ) {
				continue;
			}
			$product_id = absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) );
			if ( $product_id ) {
				$product_ids[] = $product_id;
			}
		}

		if ( empty( $product_ids ) ) {
			return array();
		}

		return array_map( 'absint', (array) wcs_get_subscriptions_for_product( $product_ids, 'ids' ) );
	}

	/* ---------------------------------------------------------------------
	 * Tela
	 * ------------------------------------------------------------------ */

	/**
	 * Renderiza a seção "Assinaturas" do painel administrativo.
	 */
	public static function render_dashboard_section() {
		if ( ! self::can_manage() ) {
			return;
		}

		self::instance();

		$filters = array(
			'search'   => isset( $_GET['imovel_sub_search'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_sub_search'] ) ) : '',
			'status'   => isset( $_GET['imovel_sub_status'] ) ? sanitize_key( wp_unslash( $_GET['imovel_sub_status'] ) ) : 'any',
			'package'  => isset( $_GET['imovel_sub_package'] ) ? absint( wp_unslash( $_GET['imovel_sub_package'] ) ) : 0,
			'type'     => isset( $_GET['imovel_sub_type'] ) ? sanitize_key( wp_unslash( $_GET['imovel_sub_type'] ) ) : '',
			'per_page' => isset( $_GET['imovel_sub_per_page'] ) ? absint( wp_unslash( $_GET['imovel_sub_per_page'] ) ) : 25,
			'paged'    => isset( $_GET['imovel_admin_paged'] ) ? absint( wp_unslash( $_GET['imovel_admin_paged'] ) ) : 1,
		);

		$manager  = self::instance();
		$results  = $manager->query_subscriptions( $filters );
		$counters = $manager->counters();
		$packages = $manager->package_options();

		$dashboard_url = houzez_get_template_link_2( 'template/user_dashboard.php' );
		$base_url      = add_query_arg(
			array(
				'imovel_admin_area'    => 'gestao',
				'imovel_admin_section' => 'subscriptions',
			),
			$dashboard_url
		);

		$selected_id = isset( $_GET['imovel_sub_id'] ) ? absint( wp_unslash( $_GET['imovel_sub_id'] ) ) : 0;
		$selected    = null;
		if ( $selected_id ) {
			$subscription = wcs_get_subscription( $selected_id );
			if ( $subscription ) {
				$selected = $manager->prepare_row_public( $subscription );
			}
		}
		?>
		<div class="dashboard-content-block rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
				<div>
					<h5 style="margin:0 0 4px;"><?php esc_html_e( 'Assinaturas', 'imovel-parceiro-core' ); ?></h5>
					<p style="margin:0; color:#6b7280; font-size:14px;"><?php esc_html_e( 'Acompanhe as assinaturas do site, os planos contratados e os prazos de vencimento.', 'imovel-parceiro-core' ); ?></p>
				</div>
			</div>

			<div class="d-flex flex-wrap gap-2 mb-4">
				<span class="badge bg-success"><?php echo esc_html( sprintf( __( '%d ativas', 'imovel-parceiro-core' ), (int) $counters['active'] ) ); ?></span>
				<span class="badge bg-warning text-dark"><?php echo esc_html( sprintf( __( '%d em cancelamento agendado', 'imovel-parceiro-core' ), (int) $counters['pending_cancel'] ) ); ?></span>
				<span class="badge bg-secondary"><?php echo esc_html( sprintf( __( '%d suspensas', 'imovel-parceiro-core' ), (int) $counters['on_hold'] ) ); ?></span>
				<span class="badge bg-info text-dark"><?php echo esc_html( sprintf( __( '%d aguardando pagamento', 'imovel-parceiro-core' ), (int) $counters['pending'] ) ); ?></span>
				<span class="badge bg-dark"><?php echo esc_html( sprintf( __( '%d canceladas ou expiradas', 'imovel-parceiro-core' ), (int) $counters['cancelled'] ) ); ?></span>
				<?php if ( (int) $counters['expired_active'] > 0 ) : ?>
					<span class="badge bg-danger"><?php echo esc_html( sprintf( __( '%d vencidas ainda ativas', 'imovel-parceiro-core' ), (int) $counters['expired_active'] ) ); ?></span>
				<?php endif; ?>
				<?php if ( (int) $counters['expiring_soon'] > 0 ) : ?>
					<span class="badge bg-warning text-dark"><?php echo esc_html( sprintf( __( '%d gratuitas vencendo em 30 dias', 'imovel-parceiro-core' ), (int) $counters['expiring_soon'] ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $selected ) : ?>
				<?php self::render_detail( $selected, $base_url ); ?>
			<?php endif; ?>

			<form method="get" style="background:#f8fafc; border:1px solid #e6e6e6; border-radius:12px; padding:16px; margin-bottom:20px;">
				<input type="hidden" name="imovel_admin_area" value="gestao" />
				<input type="hidden" name="imovel_admin_section" value="subscriptions" />
				<div class="row g-3 align-items-end">
					<div class="col-md-3 col-sm-6">
						<label for="imovel_sub_search" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Buscar assinante', 'imovel-parceiro-core' ); ?></label>
						<input type="text" id="imovel_sub_search" name="imovel_sub_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Nome, login ou e-mail', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
					</div>
					<div class="col-md-2 col-sm-6">
						<label for="imovel_sub_status" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></label>
						<select id="imovel_sub_status" name="imovel_sub_status" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
							<?php foreach ( self::status_options() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-2 col-sm-6">
						<label for="imovel_sub_package" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Plano', 'imovel-parceiro-core' ); ?></label>
						<select id="imovel_sub_package" name="imovel_sub_package" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
							<option value="0"><?php esc_html_e( 'Todos os planos', 'imovel-parceiro-core' ); ?></option>
							<?php foreach ( $packages as $package_id => $title ) : ?>
								<option value="<?php echo esc_attr( $package_id ); ?>" <?php selected( (int) $filters['package'], (int) $package_id ); ?>><?php echo esc_html( $title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-2 col-sm-6">
						<label for="imovel_sub_type" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Tipo', 'imovel-parceiro-core' ); ?></label>
						<select id="imovel_sub_type" name="imovel_sub_type" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
							<option value=""><?php esc_html_e( 'Todos', 'imovel-parceiro-core' ); ?></option>
							<option value="free" <?php selected( $filters['type'], 'free' ); ?>><?php esc_html_e( 'Gratuitos', 'imovel-parceiro-core' ); ?></option>
							<option value="paid" <?php selected( $filters['type'], 'paid' ); ?>><?php esc_html_e( 'Pagos', 'imovel-parceiro-core' ); ?></option>
						</select>
					</div>
					<div class="col-md-2 col-sm-6">
						<label for="imovel_sub_per_page" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Por página', 'imovel-parceiro-core' ); ?></label>
						<select id="imovel_sub_per_page" name="imovel_sub_per_page" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
							<?php foreach ( array( 10, 25, 50, 100 ) as $size ) : ?>
								<option value="<?php echo esc_attr( $size ); ?>" <?php selected( (int) $filters['per_page'], $size ); ?>><?php echo esc_html( $size ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-1 col-sm-6">
						<button type="submit" class="btn btn-success" style="width:100%;"><?php esc_html_e( 'Filtrar', 'imovel-parceiro-core' ); ?></button>
					</div>
				</div>
			</form>

			<div style="overflow-x:auto;">
				<table class="ipc-table table table-hover align-middle mb-0">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Assinatura', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Assinante', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Valor', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Ciclo', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Próxima cobrança', 'imovel-parceiro-core' ); ?></th>
							<th><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $results['items'] ) ) : ?>
							<tr><td colspan="7" class="text-center text-muted py-4"><?php esc_html_e( 'Nenhuma assinatura encontrada.', 'imovel-parceiro-core' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $results['items'] as $row ) : ?>
								<tr>
									<td>
										<strong style="font-weight:600; color:#1f2937;"><?php echo esc_html( $row['package_title'] ); ?></strong>
										<div class="text-muted" style="font-size:12px;">#<?php echo esc_html( $row['id'] ); ?>
											<?php if ( $row['is_free'] ) : ?>
												· <?php esc_html_e( 'gratuito', 'imovel-parceiro-core' ); ?>
											<?php endif; ?>
										</div>
									</td>
									<td>
										<div class="d-flex align-items-center gap-2">
											<?php echo get_avatar( $row['user_id'], 36 ); ?>
											<div>
												<strong style="font-weight:600; color:#1f2937;"><?php echo esc_html( $row['user_name'] ); ?></strong>
												<div class="text-muted" style="font-size:12px;"><?php echo esc_html( $row['user_email'] ); ?> · <?php echo esc_html( $row['user_role'] ); ?></div>
											</div>
										</div>
									</td>
									<td>
										<span class="badge <?php echo esc_attr( $row['status_badge'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
										<?php if ( $row['expired'] ) : ?>
											<div class="text-danger" style="font-size:12px;"><?php esc_html_e( 'validade vencida', 'imovel-parceiro-core' ); ?></div>
										<?php elseif ( $row['expiry'] ) : ?>
											<div class="text-muted" style="font-size:12px;">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: data de expiração. */
														__( 'expira em %s', 'imovel-parceiro-core' ),
														date_i18n( get_option( 'date_format' ), $row['expiry'] )
													)
												);
												?>
											</div>
										<?php endif; ?>
									</td>
									<td><?php echo wp_kses_post( wc_price( $row['total'], array( 'currency' => $row['currency'] ) ) ); ?></td>
									<td><?php echo esc_html( $row['cycle'] ); ?></td>
									<td>
										<?php if ( $row['next_payment'] ) : ?>
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), $row['next_payment'] ) ); ?>
										<?php else : ?>
											<span class="text-muted">—</span>
										<?php endif; ?>
									</td>
									<td>
										<a class="btn btn-sm btn-outline-primary" href="<?php echo esc_url( add_query_arg( 'imovel_sub_id', $row['id'], $base_url ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'imovel-parceiro-core' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ( (int) $results['pages'] > 1 ) : ?>
				<div style="margin-top:12px; display:flex; justify-content:flex-end;">
					<?php
					$pagination_base = add_query_arg(
						array(
							'imovel_sub_search'   => $filters['search'],
							'imovel_sub_status'   => $filters['status'],
							'imovel_sub_package'  => $filters['package'],
							'imovel_sub_type'     => $filters['type'],
							'imovel_sub_per_page' => $filters['per_page'],
							'imovel_admin_paged'  => '%#%',
						),
						$base_url
					);
					echo paginate_links(
						array(
							'base'      => $pagination_base,
							'format'    => '',
							'current'   => (int) $results['paged'],
							'total'     => (int) $results['pages'],
							'prev_text' => '«',
							'next_text' => '»',
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Detalhe de uma assinatura (somente leitura na Fase 1).
	 *
	 * @param array  $row
	 * @param string $base_url
	 */
	private static function render_detail( $row, $base_url ) {
		?>
		<div style="background:#f8fafc; border:1px solid #e6e6e6; border-radius:12px; padding:18px; margin-bottom:20px;">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<h6 style="margin:0;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: número da assinatura. */
							__( 'Assinatura #%s', 'imovel-parceiro-core' ),
							$row['id']
						)
					);
					?>
				</h6>
				<a href="<?php echo esc_url( $base_url ); ?>" class="btn btn-sm btn-outline-secondary"><?php esc_html_e( 'Fechar detalhe', 'imovel-parceiro-core' ); ?></a>
			</div>
			<div class="row g-3">
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php esc_html_e( 'Assinante', 'imovel-parceiro-core' ); ?></div>
					<strong><?php echo esc_html( $row['user_name'] ); ?></strong>
					<div style="font-size:13px;"><?php echo esc_html( $row['user_email'] ); ?></div>
					<div style="font-size:13px;"><?php echo esc_html( $row['user_role'] ); ?></div>
				</div>
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php esc_html_e( 'Plano', 'imovel-parceiro-core' ); ?></div>
					<strong><?php echo esc_html( $row['package_title'] ); ?></strong>
					<div style="font-size:13px;">
						<span class="badge <?php echo esc_attr( $row['status_badge'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
						<?php if ( $row['is_free'] ) : ?>
							<span class="badge bg-light text-dark"><?php esc_html_e( 'gratuito', 'imovel-parceiro-core' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php esc_html_e( 'Valores', 'imovel-parceiro-core' ); ?></div>
					<div style="font-size:13px;">
						<?php echo wp_kses_post( wc_price( $row['total'], array( 'currency' => $row['currency'] ) ) ); ?>
						· <?php echo esc_html( $row['cycle'] ); ?>
					</div>
					<div style="font-size:13px;">
						<?php esc_html_e( 'Método de pagamento:', 'imovel-parceiro-core' ); ?>
						<?php echo esc_html( $row['payment_method'] ? $row['payment_method'] : '—' ); ?>
					</div>
				</div>
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php esc_html_e( 'Início', 'imovel-parceiro-core' ); ?></div>
					<div style="font-size:13px;">
						<?php echo $row['created'] ? esc_html( date_i18n( get_option( 'date_format' ), $row['created'] ) ) : '—'; ?>
					</div>
				</div>
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php esc_html_e( 'Próxima cobrança', 'imovel-parceiro-core' ); ?></div>
					<div style="font-size:13px;">
						<?php echo $row['next_payment'] ? esc_html( date_i18n( get_option( 'date_format' ), $row['next_payment'] ) ) : '—'; ?>
					</div>
				</div>
				<div class="col-md-4 col-sm-6">
					<div class="text-muted" style="font-size:12px;"><?php echo $row['is_free'] ? esc_html__( 'Expira em', 'imovel-parceiro-core' ) : esc_html__( 'Termina em', 'imovel-parceiro-core' ); ?></div>
					<div style="font-size:13px;">
						<?php
						$end = $row['expiry'] ? $row['expiry'] : $row['ends_on'];
						echo $end ? esc_html( date_i18n( get_option( 'date_format' ), $end ) ) : '—';
						?>
						<?php if ( $row['expired'] ) : ?>
							<span class="badge bg-danger"><?php esc_html_e( 'vencida', 'imovel-parceiro-core' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Versão pública de prepare_row() (usada na tela).
	 *
	 * @param WC_Subscription $subscription
	 * @return array
	 */
	public function prepare_row_public( $subscription ) {
		return $this->prepare_row( $subscription );
	}
}

Imovel_Parceiro_Subscription_Management::instance();
