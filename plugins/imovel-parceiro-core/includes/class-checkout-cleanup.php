<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Imovel_Parceiro_Checkout_Cleanup {

	public function __construct() {
		add_filter( 'woocommerce_subscriptions_display_recurring_totals', array( $this, 'hide_recurring_totals' ) );
		add_action( 'wp_head', array( $this, 'print_styles' ), 100 );
		add_filter( 'woocommerce_billing_fields', array( $this, 'restore_company_field' ), 9, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'layout_field_sizes' ), 9999 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'restore_company_field_late' ), 999999 );
		add_filter( 'woocommerce_restored_session_data', array( $this, 'sanitize_restored_session' ) );
		add_action( 'woocommerce_loaded', array( $this, 'load_safe_session_handler' ) );
	}

	/**
	 * Load the safe session handler once WooCommerce (and WC_Session_Handler) is available.
	 *
	 * @return void
	 */
	public function load_safe_session_handler() {
		require_once __DIR__ . '/class-safe-session-handler.php';

		add_filter( 'woocommerce_session_handler', array( $this, 'filter_session_handler' ) );
	}

	/**
	 * Swap the default session handler for the hardened one.
	 *
	 * @param string $class Session handler class name.
	 * @return string
	 */
	public function filter_session_handler( $class ) {
		if ( class_exists( 'Imovel_Parceiro_Safe_Session_Handler' ) ) {
			return 'Imovel_Parceiro_Safe_Session_Handler';
		}

		return $class;
	}

	/**
	 * Prevent a fatal error when the stored WooCommerce session holds an invalid
	 * billing e-mail (for example when the WP user e-mail is not a valid address).
	 *
	 * WC_Customer_Data_Store_Session::read() calls set_billing_email() without a
	 * try/catch, so an invalid value throws WC_Data_Exception on every page load.
	 * Cleaning the value here (before WC_Customer is built) keeps the site working.
	 *
	 * @param array $data Session data restored from storage.
	 * @return array
	 */
	public function sanitize_restored_session( $data ) {
		if ( ! is_array( $data ) || empty( $data['customer'] ) || ! is_array( $data['customer'] ) ) {
			return $data;
		}

		foreach ( array( 'email', 'billing_email' ) as $key ) {
			if ( isset( $data['customer'][ $key ] ) && '' !== $data['customer'][ $key ] && ! is_email( (string) $data['customer'][ $key ] ) ) {
				$data['customer'][ $key ] = '';
			}
		}

		return $data;
	}

	/**
	 * Whether the Brazilian person-type plugin requires a company field (Pessoa Jurídica / CNPJ).
	 *
	 * @return bool
	 */
	private function needs_company_field() {
		if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			return false;
		}

		$settings = get_option( 'wcbcf_settings' );

		return ! empty( $settings['person_type'] );
	}

	/**
	 * Definition used when the company field has been removed by another plugin/option.
	 *
	 * @return array
	 */
	private function company_field_definition() {
		return array(
			'label'        => __( 'Empresa', 'imovel-parceiro-core' ),
			'required'     => false,
			'class'        => array( 'form-row-wide', 'person-type-field' ),
			'clear'        => true,
			'priority'     => 25,
			'autocomplete' => 'organization',
		);
	}

	/**
	 * Re-add the company field before the person-type plugin processes the billing fields,
	 * so it can attach its own classes/priorities.
	 *
	 * @param array  $fields  Billing fields.
	 * @param string $country Country code.
	 * @return array
	 */
	public function restore_company_field( $fields, $country = '' ) {
		if ( isset( $fields['billing_company'] ) || ! $this->needs_company_field() ) {
			return $fields;
		}

		$fields['billing_company'] = $this->company_field_definition();

		return $fields;
	}

	/**
	 * Safety net: ensure the company field survives late removals on the checkout.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function restore_company_field_late( $fields ) {
		if ( ! $this->needs_company_field() ) {
			return $fields;
		}

		if ( ! isset( $fields['billing']['billing_company'] ) ) {
			$fields['billing']['billing_company'] = $this->company_field_definition();
		}

		return $fields;
	}

	/**
	 * Largura dos campos conforme o tamanho do dado (metades nativas
	 * form-row-first/last; roda depois dos plugins de checkout/BR).
	 *
	 * Pares: Nome+Sobrenome, CPF+RG, CNPJ+IE, Número+Complemento, Bairro+Cidade,
	 * CEP+Estado, Telefone+E-mail. Endereço, País e Tipo/Empresa: integrais.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function layout_field_sizes( $fields ) {
		$map = array(
			'billing_first_name'    => array( 'first', 10 ),
			'billing_last_name'     => array( 'last', 20 ),
			'billing_persontype'    => array( 'wide', 22 ),
			'billing_cpf'           => array( 'first', 23 ),
			'billing_rg'            => array( 'last', 24 ),
			'billing_company'       => array( 'wide', 25 ),
			'billing_cnpj'          => array( 'first', 26 ),
			'billing_ie'            => array( 'last', 27 ),
			'billing_country'       => array( 'wide', 40 ),
			'billing_address_1'     => array( 'wide', 50 ),
			'billing_number'        => array( 'first', 55 ),
			'billing_address_2'     => array( 'last', 60 ),
			'billing_neighborhood'  => array( 'first', 65 ),
			'billing_city'          => array( 'last', 70 ),
			'billing_postcode'      => array( 'first', 75 ),
			'billing_state'         => array( 'last', 80 ),
			'billing_phone'         => array( 'first', 100 ),
			'billing_email'         => array( 'last', 110 ),
			'shipping_first_name'   => array( 'first', 10 ),
			'shipping_last_name'    => array( 'last', 20 ),
			'shipping_country'      => array( 'wide', 40 ),
			'shipping_address_1'    => array( 'wide', 50 ),
			'shipping_number'       => array( 'first', 55 ),
			'shipping_address_2'    => array( 'last', 60 ),
			'shipping_neighborhood' => array( 'first', 65 ),
			'shipping_city'         => array( 'last', 70 ),
			'shipping_postcode'     => array( 'first', 75 ),
			'shipping_state'        => array( 'last', 80 ),
		);

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( empty( $fields[ $group ] ) || ! is_array( $fields[ $group ] ) ) {
				continue;
			}

			foreach ( $map as $key => $layout ) {
				if ( ! isset( $fields[ $group ][ $key ] ) || ! is_array( $fields[ $group ][ $key ] ) ) {
					continue;
				}

				list( $size, $priority ) = $layout;

				$classes = array();
				if ( 'first' === $size ) {
					$classes[] = 'form-row-first';
				} elseif ( 'last' === $size ) {
					$classes[] = 'form-row-last';
				} else {
					$classes[] = 'form-row-wide';
				}

				// Preserva marcadores funcionais dos plugins (endereço, totais, pessoa).
				foreach ( (array) $fields[ $group ][ $key ]['class'] as $existing ) {
					if ( in_array( $existing, array( 'address-field', 'update_totals_on_change', 'person-type-field' ), true )
						&& ! in_array( $existing, $classes, true ) ) {
						$classes[] = $existing;
					}
				}

				$fields[ $group ][ $key ]['class']    = $classes;
				$fields[ $group ][ $key ]['priority'] = $priority;
				$fields[ $group ][ $key ]['clear']    = false;
			}
		}

		return $fields;
	}

	public function hide_recurring_totals( $show ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}

		return $show;
	}

	public function print_styles() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		?>
		<style id="ipc-checkout-hide-totals">
			.woocommerce-checkout .woocommerce-checkout-review-order-table .cart-subtotal,
			.woocommerce-checkout .woocommerce-checkout-review-order-table .order-total,
			.woocommerce-checkout .woocommerce-checkout-review-order-table .recurring-totals,
			.woocommerce-checkout .woocommerce-checkout-review-order-table .recurring-total,
			.woocommerce-checkout .woocommerce-checkout-review-order-table tr.cart-subtotal,
			.woocommerce-checkout .woocommerce-checkout-review-order-table tr.order-total {
				display: none !important;
			}
		</style>
		<?php
	}
}

new Imovel_Parceiro_Checkout_Cleanup();
