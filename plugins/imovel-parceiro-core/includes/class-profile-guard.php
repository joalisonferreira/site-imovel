<?php
/**
 * Require a complete profile before a user is allowed to buy a plan.
 *
 * @package Imovel_Parceiro_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Imovel_Parceiro_Profile_Guard' ) ) {

	class Imovel_Parceiro_Profile_Guard {

		const TAX_META     = 'fave_author_tax_no';
		const LICENSE_META = 'fave_author_license';

		/**
		 * Accepted phone meta keys (any non-empty one satisfies the requirement).
		 *
		 * @var string[]
		 */
		private static $phone_metas = array( 'fave_author_phone', 'fave_author_mobile', 'fave_author_whatsapp' );

		/**
		 * Roles that must also provide a CRECI number.
		 *
		 * @var string[]
		 */
		private static $creci_roles = array( 'houzez_agent', 'houzez_agency' );

		public function __construct() {
			add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout' ) );
		}

		/**
		 * Dashboard profile URL.
		 *
		 * @param int $user_id Optional user id.
		 * @return string
		 */
		public static function profile_url( $user_id = 0 ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			if ( function_exists( 'houzez_get_template_link_2' ) ) {
				$link = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );
				if ( ! empty( $link ) ) {
					return $link;
				}
			}

			return home_url( '/meu-perfil/' );
		}

		/**
		 * Return the labels of the missing required profile fields.
		 *
		 * @param int $user_id Optional user id.
		 * @return string[]
		 */
		public static function missing_fields( $user_id = 0 ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			$missing = array();

			if ( ! $user_id ) {
				$missing[] = __( 'login', 'imovel-parceiro-core' );
				return $missing;
			}

			$phone = '';
			foreach ( self::$phone_metas as $meta ) {
				$value = trim( (string) get_user_meta( $user_id, $meta, true ) );
				if ( '' !== $value ) {
					$phone = $value;
					break;
				}
			}
			if ( '' === $phone ) {
				$missing[] = __( 'Telefone', 'imovel-parceiro-core' );
			}

			if ( '' === trim( (string) get_user_meta( $user_id, self::TAX_META, true ) ) ) {
				$missing[] = __( 'CPF/CNPJ', 'imovel-parceiro-core' );
			}

			$user = get_userdata( $user_id );
			if ( $user && array_intersect( (array) $user->roles, self::$creci_roles ) ) {
				if ( '' === trim( (string) get_user_meta( $user_id, self::LICENSE_META, true ) ) ) {
					$missing[] = __( 'CRECI', 'imovel-parceiro-core' );
				}
			}

			return $missing;
		}

		/**
		 * Whether the user's profile is complete.
		 *
		 * @param int $user_id Optional user id.
		 * @return bool
		 */
		public static function is_complete( $user_id = 0 ) {
			return empty( self::missing_fields( $user_id ) );
		}

		/**
		 * Human readable message telling the user what is missing.
		 *
		 * @param string[] $missing Optional missing field labels.
		 * @return string
		 */
		public static function incomplete_message( $missing = array() ) {
			if ( empty( $missing ) ) {
				$missing = self::missing_fields();
			}

			return sprintf(
				/* translators: 1: comma separated list of missing fields, 2: profile url */
				__( 'Antes de assinar um plano, complete seu perfil. Campos pendentes: %1$s. Acesse seu perfil em %2$s', 'imovel-parceiro-core' ),
				implode( ', ', $missing ),
				self::profile_url()
			);
		}

		/**
		 * Whether the current cart contains one of our managed subscription plans.
		 *
		 * @return bool
		 */
		private function cart_has_managed_plan() {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return false;
			}

			foreach ( WC()->cart->get_cart() as $item ) {
				if ( empty( $item['product_id'] ) ) {
					continue;
				}
				if ( get_post_meta( $item['product_id'], Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::PRODUCT_PACKAGE_META, true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Block checkout when a managed plan is being purchased with an incomplete profile.
		 *
		 * @return void
		 */
		public function validate_checkout() {
			if ( ! self::cart_has_managed_plan() ) {
				return;
			}

			$missing = self::missing_fields();
			if ( empty( $missing ) ) {
				return;
			}

			wc_add_notice( self::incomplete_message( $missing ), 'error' );
		}
	}
}

new Imovel_Parceiro_Profile_Guard();
