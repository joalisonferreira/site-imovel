<?php
/**
 * Override do card de plano (Houzez) com suporte a "Preço sob consulta",
 * limite de corretores e fluxo de solicitação de personalização.
 */

global $houzez_local;
$currency_symbol = houzez_option( 'currency_symbol' );
$where_currency  = houzez_option( 'currency_position' );
if ( class_exists( 'Houzez_Currencies' ) ) {
	$multi_currency   = houzez_option( 'multi_currency' );
	$default_currency = houzez_option( 'default_multi_currency' );
	if ( empty( $default_currency ) ) {
		$default_currency = 'USD';
	}

	if ( $multi_currency == 1 ) {
		$currency       = Houzez_Currencies::get_currency_by_code( $default_currency );
		$currency_symbol = $currency['currency_symbol'];
	}
}
$payment_page_link = houzez_get_template_link( 'template/template-payment.php' );

$args = array(
	'post_type'      => 'houzez_packages',
	'posts_per_page' => -1,
	'meta_query'     => array(
		array(
			'key'     => 'fave_package_visible',
			'value'   => 'yes',
			'compare' => '=',
		),
	),
);
$fave_qry = new WP_Query( $args );

$total_packages = $first_pkg_column = '';
$total_packages = $fave_qry->found_posts;

if ( $total_packages == 3 ) {
	$pkg_classes = 'col-md-4 col-sm-4 col-xs-12';
} elseif ( $total_packages == 4 ) {
	$pkg_classes = 'col-md-3 col-sm-6';
} elseif ( $total_packages == 2 ) {
	$pkg_classes = 'col-md-6 col-sm-6';
} elseif ( $total_packages == 1 ) {
	$pkg_classes = 'col-md-4 col-sm-12';
} else {
	$pkg_classes = 'col-md-3 col-sm-6';
}
$i = 0;
while ( $fave_qry->have_posts() ) :
	$fave_qry->the_post();
	$i++;

	$pack_price              = get_post_meta( get_the_ID(), 'fave_package_price', true );
	$pack_listings           = get_post_meta( get_the_ID(), 'fave_package_listings', true );
	$pack_featured_listings  = get_post_meta( get_the_ID(), 'fave_package_featured_listings', true );
	$pack_unlimited_listings = get_post_meta( get_the_ID(), 'fave_unlimited_listings', true );
	$pack_billing_period     = get_post_meta( get_the_ID(), 'fave_billing_time_unit', true );
	$pack_billing_frquency   = get_post_meta( get_the_ID(), 'fave_billing_unit', true );
	$fave_package_images     = get_post_meta( get_the_ID(), 'fave_package_images', true );
	$pack_package_tax        = get_post_meta( get_the_ID(), 'fave_package_tax', true );
	$fave_package_popular    = get_post_meta( get_the_ID(), 'fave_package_popular', true );
	$package_custom_link     = get_post_meta( get_the_ID(), 'fave_package_custom_link', true );
	$never_expire            = get_post_meta( get_the_ID(), 'fave_never_expire', true );

	$price_on_request = class_exists( 'Imovel_Parceiro_Package_Extras' ) && Imovel_Parceiro_Package_Extras::price_on_request( get_the_ID() );
	$max_agents       = class_exists( 'Imovel_Parceiro_Package_Extras' ) ? Imovel_Parceiro_Package_Extras::max_agents( get_the_ID() ) : 0;
	$is_free_plan     = class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) && Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( get_the_ID() );

	if ( $never_expire == 1 ) {
		$pack_billing_period   = 'Lifetime';
		$pack_billing_frquency = '';
	} elseif ( $pack_billing_frquency > 1 ) {
		$pack_billing_period .= 's';
	}

	if ( $is_free_plan ) {
		$free_validity         = Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::free_plan_validity( get_the_ID() );
		$pack_billing_frquency = $free_validity['value'];
		$pack_billing_period   = ucfirst( $free_validity['unit'] );
		if ( $pack_billing_frquency > 1 ) {
			$pack_billing_period .= 's';
		}
	}

	if ( $is_free_plan ) {
		$package_price = '<span class="price-table-price">' . esc_html__( 'Grátis', 'houzez' ) . '</span>';
	} elseif ( $price_on_request ) {
		$package_price = '<span class="price-table-price">' . esc_html__( 'Sob consulta', 'houzez' ) . '</span>';
	} elseif ( $where_currency == 'before' ) {
		$package_price = '<span class="price-table-currency">' . $currency_symbol . '</span><span class="price-table-price">' . $pack_price . '</span>';
	} else {
		$package_price = '<span class="price-table-price">' . $pack_price . '</span><span class="price-table-currency">' . $currency_symbol . '</span>';
	}

	if ( $fave_package_popular == 'yes' ) {
		$is_popular = 'featured';
	} else {
		$is_popular = '';
	}

	$payment_process_link = add_query_arg( 'selected_package', get_the_ID(), $payment_page_link );

	if ( $i == 1 && $total_packages == 2 ) {
		$first_pkg_column = 'col-md-offset-2 col-sm-offset-0';
	} elseif ( $i == 1 && $total_packages == 1 ) {
		$first_pkg_column = 'col-md-offset-4 col-sm-offset-0';
	} else {
		$first_pkg_column = '';
	}

	if ( ! empty( $package_custom_link ) && ! $price_on_request ) {
		$payment_process_link = $package_custom_link;
	}
	?>
	<div class="<?php echo esc_attr( $pkg_classes ); ?>">
		<div class="price-table-module pt-4 text-center <?php echo esc_attr( $is_popular ); ?>">
			<div class="price-table-title">
				<?php the_title(); ?>
			</div><!-- price-table-title -->
			<div class="price-table-price-wrap py-4">
				<div class="d-flex align-items-start justify-content-center">
					<?php echo $package_price; ?>
				</div><!-- d-flex -->
			</div><!-- price-table-price-wrap -->
			<div class="price-table-description border-bottom">
				<ul class="list-unstyled d-flex flex-column justify-content-center mb-0">
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php echo $houzez_local['time_period']; ?>:
						<strong><?php echo trim( esc_attr( $pack_billing_frquency ) . ' ' . HOUZEZ_billing_period( $pack_billing_period ) ); ?></strong>
					</li>
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php echo houzez_option( 'cl_properties', 'Properties' ); ?>:
						<?php if ( $pack_unlimited_listings == 1 ) { ?>
							<strong><?php echo $houzez_local['unlimited_listings']; ?></strong>
						<?php } else { ?>
							<strong><?php echo esc_attr( $pack_listings ); ?></strong>
						<?php } ?>
					</li>
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php echo $houzez_local['featured_listings']; ?>:
						<strong><?php echo esc_attr( $pack_featured_listings ); ?></strong>
					</li>

					<?php if ( $max_agents > 0 ) { ?>
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php esc_html_e( 'Corretores', 'houzez' ); ?>:
						<strong><?php echo esc_html( number_format_i18n( $max_agents ) ); ?></strong>
					</li>
					<?php } ?>

					<?php if ( $fave_package_images != '' ) { ?>
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php esc_html_e( 'Images', 'houzez' ); ?>:
						<strong><?php echo esc_attr( $fave_package_images ); ?></strong>
					</li>
					<?php } ?>

					<?php if ( $pack_package_tax != '' ) { ?>
					<li class="border-top py-3">
						<i class="houzez-icon icon-check-circle-1 primary-text me-1"></i>
						<?php esc_html_e( 'Taxes', 'houzez' ); ?>:
						<strong><?php echo esc_attr( $pack_package_tax ) . '%'; ?></strong>
					</li>
					<?php } ?>
				</ul>
			</div><!-- price-table-description -->
			<div class="price-table-button p-4">
				<?php if ( $price_on_request ) { ?>
					<a class="ipc-request-quote btn btn-primary" data-packid="<?php echo esc_attr( get_the_ID() ); ?>" data-packname="<?php echo esc_attr( get_the_title() ); ?>" href="#">
						<i class="houzez-icon icon-check-circle-1 me-1"></i> <?php esc_html_e( 'Fale conosco', 'houzez' ); ?>
					</a>
				<?php } elseif ( houzez_is_woocommerce() ) { ?>
					<a class="houzez-woocommerce-package btn btn-primary" data-packid="<?php echo get_the_ID(); ?>" href="#">
						<i class="houzez-icon icon-check-circle-1 me-1"></i> <?php echo $houzez_local['get_started']; ?>
					</a>
				<?php } else { ?>
					<a class="btn btn-primary" href="<?php echo esc_url( $payment_process_link ); ?>">
						<i class="houzez-icon icon-check-circle-1 me-1"></i> <?php echo $houzez_local['get_started']; ?>
					</a>
				<?php } ?>
			</div><!-- price-table-button -->
		</div><!-- taxonomy-grids-module -->
	</div>
<?php endwhile; ?>
<?php wp_reset_postdata(); ?>

<?php
if ( ! class_exists( 'Imovel_Parceiro_Package_Extras' ) ) {
	return;
}

static $ipc_quote_modal_rendered = false;
if ( $ipc_quote_modal_rendered ) {
	return;
}
$ipc_quote_modal_rendered = true;

$ipc_current_user = wp_get_current_user();
$ipc_prefill      = array(
	'name'  => $ipc_current_user->exists() ? $ipc_current_user->display_name : '',
	'email' => $ipc_current_user->exists() ? $ipc_current_user->user_email : '',
	'phone' => $ipc_current_user->exists() ? get_user_meta( $ipc_current_user->ID, 'fave_author_mobile', true ) : '',
);
?>
<div class="ipc-quote-modal" id="ipc-quote-modal" aria-hidden="true">
	<div class="ipc-quote-backdrop" data-ipc-close="1"></div>
	<div class="ipc-quote-dialog" role="dialog" aria-modal="true" aria-labelledby="ipc-quote-title">
		<button type="button" class="ipc-quote-close" data-ipc-close="1" aria-label="<?php esc_attr_e( 'Fechar', 'houzez' ); ?>">&times;</button>
		<h3 id="ipc-quote-title" class="ipc-quote-heading"><?php esc_html_e( 'Solicitar personalização', 'houzez' ); ?></h3>
		<p class="ipc-quote-sub"><?php esc_html_e( 'Conte o que você precisa e nossa equipe entra em contato.', 'houzez' ); ?></p>
		<form class="ipc-quote-form">
			<input type="hidden" name="package_id" value="" />
			<div class="ipc-quote-plan">
				<strong><?php esc_html_e( 'Plano:', 'houzez' ); ?></strong> <span class="ipc-quote-plan-name"></span>
			</div>
			<div class="ipc-quote-row">
				<label><?php esc_html_e( 'Nome', 'houzez' ); ?>
					<input type="text" name="name" value="<?php echo esc_attr( $ipc_prefill['name'] ); ?>" required />
				</label>
				<label><?php esc_html_e( 'E-mail', 'houzez' ); ?>
					<input type="email" name="email" value="<?php echo esc_attr( $ipc_prefill['email'] ); ?>" required />
				</label>
			</div>
			<div class="ipc-quote-row">
				<label><?php esc_html_e( 'Telefone', 'houzez' ); ?>
					<input type="text" name="phone" value="<?php echo esc_attr( $ipc_prefill['phone'] ); ?>" required />
				</label>
				<label><?php esc_html_e( 'Tipo de personalização', 'houzez' ); ?>
					<input type="text" name="customization_type" placeholder="<?php esc_attr_e( 'Ex.: integração, marca, quantidade de anúncios', 'houzez' ); ?>" required />
				</label>
			</div>
			<label class="ipc-quote-message"><?php esc_html_e( 'Detalhes (opcional)', 'houzez' ); ?>
				<textarea name="message" rows="4"></textarea>
			</label>
			<div class="ipc-quote-feedback" role="status"></div>
			<button type="submit" class="btn btn-primary ipc-quote-submit"><?php esc_html_e( 'Enviar solicitação', 'houzez' ); ?></button>
		</form>
	</div>
</div>
<style>
.ipc-quote-modal{position:fixed;inset:0;z-index:100000;display:none}
.ipc-quote-modal.is-open{display:block}
.ipc-quote-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55)}
.ipc-quote-dialog{position:relative;max-width:560px;margin:6vh auto 0;background:#fff;border-radius:16px;padding:28px;box-shadow:0 24px 60px rgba(15,23,42,.25);max-height:88vh;overflow:auto}
.ipc-quote-close{position:absolute;top:12px;right:16px;border:0;background:none;font-size:28px;line-height:1;color:#64748b;cursor:pointer}
.ipc-quote-heading{margin:0 0 6px;font-size:20px;font-weight:700;color:#0f172a}
.ipc-quote-sub{margin:0 0 18px;color:#64748b;font-size:14px}
.ipc-quote-plan{margin-bottom:14px;font-size:14px;color:#334155}
.ipc-quote-row{display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap}
.ipc-quote-row label{flex:1 1 220px;display:block;font-size:13px;font-weight:600;color:#334155}
.ipc-quote-row input,.ipc-quote-message textarea{width:100%;margin-top:6px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
.ipc-quote-message{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:14px}
.ipc-quote-feedback{margin:6px 0 12px;font-size:14px;font-weight:600}
.ipc-quote-feedback.is-error{color:#b91c1c}
.ipc-quote-feedback.is-success{color:#047857}
.ipc-quote-submit{width:100%}
</style>
<script>
(function () {
	var modal = document.getElementById('ipc-quote-modal');
	if (!modal) { return; }
	var form = modal.querySelector('.ipc-quote-form');
	var feedback = modal.querySelector('.ipc-quote-feedback');
	var planName = modal.querySelector('.ipc-quote-plan-name');
	var packageInput = form.querySelector('input[name="package_id"]');
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ipc_request_quote_nonce' ) ); ?>;

	function open(packId, packName) {
		packageInput.value = packId || '';
		planName.textContent = packName || '';
		feedback.textContent = '';
		feedback.className = 'ipc-quote-feedback';
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
	}
	function close() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
	}

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('.ipc-request-quote');
		if (trigger) {
			e.preventDefault();
			open(trigger.getAttribute('data-packid'), trigger.getAttribute('data-packname'));
			return;
		}
		if (e.target.closest('[data-ipc-close]')) {
			close();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { close(); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		feedback.textContent = '';
		feedback.className = 'ipc-quote-feedback';
		var data = new FormData(form);
		data.append('action', 'ipc_request_quote');
		data.append('nonce', nonce);
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					feedback.className = 'ipc-quote-feedback is-success';
					feedback.textContent = res.data && res.data.message ? res.data.message : 'Enviado!';
					form.reset();
				} else {
					feedback.className = 'ipc-quote-feedback is-error';
					feedback.textContent = res && res.data && res.data.message ? res.data.message : 'Não foi possível enviar.';
				}
			})
			.catch(function () {
				feedback.className = 'ipc-quote-feedback is-error';
				feedback.textContent = 'Não foi possível enviar. Tente novamente.';
			});
	});
})();
</script>
