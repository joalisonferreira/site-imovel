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

<?php get_template_part( 'template-parts/membership/quote-modal' ); ?>
