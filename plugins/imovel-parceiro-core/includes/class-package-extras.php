<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extras de planos: limite de corretores para planos de imobiliária,
 * preço sob consulta e fluxo de solicitação de personalização.
 */
class Imovel_Parceiro_Package_Extras {

	const META_MAX_AGENTS       = '_imovel_parceiro_max_agents';
	const META_PRICE_ON_REQUEST = '_imovel_parceiro_price_on_request';

	const LEAD_CPT               = 'imovel_quote_lead';
	const LEAD_STATUS_META       = '_ipc_lead_status';
	const LEAD_NAME_META         = '_ipc_lead_name';
	const LEAD_EMAIL_META        = '_ipc_lead_email';
	const LEAD_PHONE_META        = '_ipc_lead_phone';
	const LEAD_PACKAGE_ID_META   = '_ipc_lead_package_id';
	const LEAD_PACKAGE_NAME_META = '_ipc_lead_package_name';
	const LEAD_TYPE_META         = '_ipc_lead_type';
	const LEAD_MESSAGE_META      = '_ipc_lead_message';
	const LEAD_USER_ID_META      = '_ipc_lead_user_id';
	const LEAD_IP_META           = '_ipc_lead_ip';

	public function __construct() {
		add_action( 'init', array( $this, 'register_lead_cpt' ) );
		add_action( 'init', array( $this, 'handle_lead_status_action' ) );

		add_action( 'wp_ajax_houzez_agency_agent', array( $this, 'guard_agency_agent_creation' ), 1 );

		add_action( 'wp_ajax_ipc_request_quote', array( $this, 'ajax_request_quote' ) );
		add_action( 'wp_ajax_nopriv_ipc_request_quote', array( $this, 'ajax_request_quote' ) );
	}

	/* ---------------------------------------------------------------------
	 * Meta helpers
	 * ------------------------------------------------------------------ */

	public static function max_agents( $package_id ) {
		return absint( get_post_meta( $package_id, self::META_MAX_AGENTS, true ) );
	}

	public static function price_on_request( $package_id ) {
		return 'yes' === get_post_meta( $package_id, self::META_PRICE_ON_REQUEST, true );
	}

	public static function save_package_fields( $package_id, $max_agents, $price_on_request ) {
		$max_agents = absint( $max_agents );
		if ( $max_agents > 0 ) {
			update_post_meta( $package_id, self::META_MAX_AGENTS, $max_agents );
		} else {
			delete_post_meta( $package_id, self::META_MAX_AGENTS );
		}

		if ( $price_on_request ) {
			update_post_meta( $package_id, self::META_PRICE_ON_REQUEST, 'yes' );
		} else {
			delete_post_meta( $package_id, self::META_PRICE_ON_REQUEST );
		}
	}

	/* ---------------------------------------------------------------------
	 * Limite de corretores por plano de imobiliária
	 * ------------------------------------------------------------------ */

	public static function count_agency_agents( $agency_user_id ) {
		$agency_user_id = absint( $agency_user_id );
		if ( ! $agency_user_id ) {
			return 0;
		}

		$users = get_users(
			array(
				'role'       => 'houzez_agent',
				'meta_key'   => 'fave_agent_agency',
				'meta_value' => $agency_user_id,
				'fields'     => 'ID',
			)
		);

		return is_array( $users ) ? count( $users ) : 0;
	}

	public static function agency_agent_limit( $agency_user_id ) {
		if ( ! class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) ) {
			return 0;
		}

		$package_id = Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::active_package_for_user( $agency_user_id );
		if ( ! $package_id ) {
			return 0;
		}

		return self::max_agents( $package_id );
	}

	/**
	 * Roda antes do handler nativo do houzez-login-register e impede o
	 * cadastro de corretores quando o plano da imobiliária atingiu o limite.
	 */
	public function guard_agency_agent_creation() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user_id = get_current_user_id();
		$agency_user_id  = $current_user_id;

		if ( user_can( $current_user_id, 'manage_options' ) && ! empty( $_POST['agency_id'] ) ) {
			$agency_user_id = absint( wp_unslash( $_POST['agency_id'] ) );
		}

		$limit = self::agency_agent_limit( $agency_user_id );
		if ( $limit <= 0 ) {
			return;
		}

		if ( self::count_agency_agents( $agency_user_id ) >= $limit ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'msg'     => sprintf(
						/* translators: %d: limite de corretores */
						esc_html__( 'Limite de %d corretores do seu plano atingido. Faça upgrade para cadastrar mais corretores.', 'imovel-parceiro-core' ),
						$limit
					),
				)
			);
			wp_die();
		}
	}

	/* ---------------------------------------------------------------------
	 * Leads (preço sob consulta)
	 * ------------------------------------------------------------------ */

	public function register_lead_cpt() {
		register_post_type(
			self::LEAD_CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Orçamentos', 'imovel-parceiro-core' ),
					'singular_name' => __( 'Orçamento', 'imovel-parceiro-core' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	public function ajax_request_quote() {
		check_ajax_referer( 'ipc_request_quote_nonce', 'nonce' );

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$type    = isset( $_POST['customization_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customization_type'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$package = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;

		if ( '' === $name || '' === $email || '' === $phone || '' === $type ) {
			wp_send_json_error( array( 'message' => __( 'Preencha nome, e-mail, telefone e o tipo de personalização.', 'imovel-parceiro-core' ) ), 400 );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Informe um e-mail válido.', 'imovel-parceiro-core' ) ), 400 );
		}

		$package_name = ( $package && 'houzez_packages' === get_post_type( $package ) ) ? get_the_title( $package ) : __( 'Plano', 'imovel-parceiro-core' );

		$lead_id = wp_insert_post(
			array(
				'post_type'   => self::LEAD_CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s - %s', $package_name, $name ),
			)
		);

		if ( ! $lead_id || is_wp_error( $lead_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Não foi possível registrar seu pedido. Tente novamente.', 'imovel-parceiro-core' ) ), 500 );
		}

		update_post_meta( $lead_id, self::LEAD_NAME_META, $name );
		update_post_meta( $lead_id, self::LEAD_EMAIL_META, $email );
		update_post_meta( $lead_id, self::LEAD_PHONE_META, $phone );
		update_post_meta( $lead_id, self::LEAD_TYPE_META, $type );
		update_post_meta( $lead_id, self::LEAD_MESSAGE_META, $message );
		update_post_meta( $lead_id, self::LEAD_PACKAGE_ID_META, $package );
		update_post_meta( $lead_id, self::LEAD_PACKAGE_NAME_META, $package_name );
		update_post_meta( $lead_id, self::LEAD_USER_ID_META, get_current_user_id() );
		update_post_meta( $lead_id, self::LEAD_IP_META, self::get_request_ip() );
		update_post_meta( $lead_id, self::LEAD_STATUS_META, 'novo' );

		self::send_lead_email( $lead_id );

		wp_send_json_success( array( 'message' => __( 'Recebemos seu pedido! Nossa equipe entrará em contato em breve.', 'imovel-parceiro-core' ) ) );
	}

	private static function send_lead_email( $lead_id ) {
		$to = get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}

		$package_name = get_post_meta( $lead_id, self::LEAD_PACKAGE_NAME_META, true );
		$subject      = sprintf(
			/* translators: %s: nome do plano */
			__( '[%1$s] Preço sob consulta: %2$s', 'imovel-parceiro-core' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$package_name
		);

		$lines = array(
			sprintf( __( 'Plano: %s', 'imovel-parceiro-core' ), $package_name ),
			sprintf( __( 'Nome: %s', 'imovel-parceiro-core' ), get_post_meta( $lead_id, self::LEAD_NAME_META, true ) ),
			sprintf( __( 'E-mail: %s', 'imovel-parceiro-core' ), get_post_meta( $lead_id, self::LEAD_EMAIL_META, true ) ),
			sprintf( __( 'Telefone: %s', 'imovel-parceiro-core' ), get_post_meta( $lead_id, self::LEAD_PHONE_META, true ) ),
			sprintf( __( 'Tipo de personalização: %s', 'imovel-parceiro-core' ), get_post_meta( $lead_id, self::LEAD_TYPE_META, true ) ),
			sprintf( __( 'Mensagem: %s', 'imovel-parceiro-core' ), get_post_meta( $lead_id, self::LEAD_MESSAGE_META, true ) ),
		);

		$body = Imovel_Parceiro_Email_Template::text_to_html( implode( "\n", $lines ) );

		if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			Imovel_Parceiro_Email_Template::send(
				$to,
				$subject,
				$body,
				array( 'title' => __( 'Preço sob consulta', 'imovel-parceiro-core' ) )
			);
		} else {
			wp_mail( $to, $subject, implode( "\n", $lines ) );
		}
	}

	private static function get_request_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$value = trim( explode( ',', $value )[0] );
			if ( $value ) {
				return $value;
			}
		}
		return '';
	}

	public function handle_lead_status_action() {
		if ( empty( $_GET['imovel_lead_action'] ) || empty( $_GET['lead_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$lead_id = absint( wp_unslash( $_GET['lead_id'] ) );
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ipc_lead_done_' . $lead_id ) ) {
			return;
		}

		if ( 'imovel_quote_lead' !== get_post_type( $lead_id ) ) {
			return;
		}

		if ( 'done' === sanitize_key( wp_unslash( $_GET['imovel_lead_action'] ) ) ) {
			update_post_meta( $lead_id, self::LEAD_STATUS_META, 'atendido' );
		}

		wp_safe_redirect( remove_query_arg( array( 'imovel_lead_action', 'lead_id', '_wpnonce' ) ) );
		exit;
	}

	public static function render_dashboard_section() {
		$leads = get_posts(
			array(
				'post_type'      => self::LEAD_CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$base_url = add_query_arg(
			array(
				'imovel_admin_area'    => 'gestao',
				'imovel_admin_section' => 'orcamentos',
			),
			get_permalink()
		);
		?>
		<div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
			<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
				<h4 class="text-lg font-bold text-slate-900"><?php esc_html_e( 'Solicitações de personalização', 'imovel-parceiro-core' ); ?></h4>
				<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"><?php echo esc_html( number_format_i18n( count( $leads ) ) ); ?></span>
			</div>

			<?php if ( empty( $leads ) ) : ?>
				<p class="text-sm text-slate-500"><?php esc_html_e( 'Nenhuma solicitação de preço sob consulta recebida ainda.', 'imovel-parceiro-core' ); ?></p>
			<?php else : ?>
				<div class="overflow-x-auto">
					<table class="w-full text-left text-sm">
						<thead>
							<tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
								<th class="py-2 pr-4"><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></th>
								<th class="py-2 pr-4"><?php esc_html_e( 'Contato', 'imovel-parceiro-core' ); ?></th>
								<th class="py-2 pr-4"><?php esc_html_e( 'Plano', 'imovel-parceiro-core' ); ?></th>
								<th class="py-2 pr-4"><?php esc_html_e( 'Personalização', 'imovel-parceiro-core' ); ?></th>
								<th class="py-2 pr-4"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
								<th class="py-2"><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $leads as $lead ) : ?>
								<?php
								$status  = get_post_meta( $lead->ID, self::LEAD_STATUS_META, true );
								$status  = $status ? $status : 'novo';
								$done    = ( 'atendido' === $status );
								$done_url = add_query_arg(
									array(
										'imovel_lead_action' => 'done',
										'lead_id'            => $lead->ID,
										'_wpnonce'           => wp_create_nonce( 'ipc_lead_done_' . $lead->ID ),
									),
									$base_url
								);
								?>
								<tr class="border-b border-slate-50 align-top">
									<td class="py-3 pr-4 text-slate-500"><?php echo esc_html( get_the_date( 'd/m/Y H:i', $lead ) ); ?></td>
									<td class="py-3 pr-4">
										<div class="font-semibold text-slate-800"><?php echo esc_html( get_post_meta( $lead->ID, self::LEAD_NAME_META, true ) ); ?></div>
										<div class="text-slate-500"><?php echo esc_html( get_post_meta( $lead->ID, self::LEAD_EMAIL_META, true ) ); ?></div>
										<div class="text-slate-500"><?php echo esc_html( get_post_meta( $lead->ID, self::LEAD_PHONE_META, true ) ); ?></div>
									</td>
									<td class="py-3 pr-4 text-slate-600"><?php echo esc_html( get_post_meta( $lead->ID, self::LEAD_PACKAGE_NAME_META, true ) ); ?></td>
									<td class="py-3 pr-4 text-slate-600">
										<div class="font-semibold text-slate-700"><?php echo esc_html( get_post_meta( $lead->ID, self::LEAD_TYPE_META, true ) ); ?></div>
										<?php $msg = get_post_meta( $lead->ID, self::LEAD_MESSAGE_META, true ); ?>
										<?php if ( $msg ) : ?>
											<div class="mt-1 max-w-xs whitespace-pre-line text-slate-500"><?php echo esc_html( $msg ); ?></div>
										<?php endif; ?>
									</td>
									<td class="py-3 pr-4">
										<?php if ( $done ) : ?>
											<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700"><?php esc_html_e( 'Atendido', 'imovel-parceiro-core' ); ?></span>
										<?php else : ?>
											<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"><?php esc_html_e( 'Novo', 'imovel-parceiro-core' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="py-3">
										<?php if ( ! $done ) : ?>
											<a href="<?php echo esc_url( $done_url ); ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-all hover:border-slate-300 hover:text-slate-800">
												<?php esc_html_e( 'Marcar atendido', 'imovel-parceiro-core' ); ?>
											</a>
										<?php else : ?>
											<span class="text-xs text-slate-400">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Imovel_Parceiro_Package_Extras();
