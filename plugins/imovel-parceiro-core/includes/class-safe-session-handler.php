<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Session_Handler' ) && ! class_exists( 'Imovel_Parceiro_Safe_Session_Handler' ) ) {

	/**
	 * Session handler that never exposes an invalid billing e-mail.
	 *
	 * WC_Customer_Data_Store_Session::read() calls set_billing_email() without a
	 * try/catch, so a stored session holding an invalid address throws
	 * WC_Data_Exception on every page load (white screen). Sanitizing the value
	 * when the session is read avoids the fatal and lets the session self-heal.
	 */
	class Imovel_Parceiro_Safe_Session_Handler extends WC_Session_Handler {

		/**
		 * @param string $key           Session key.
		 * @param mixed  $default_value Default value.
		 * @return mixed
		 */
		public function get( $key, $default_value = null ) {
			$value = parent::get( $key, $default_value );

			if ( 'customer' === $key && is_array( $value ) ) {
				$changed = false;

				foreach ( array( 'email', 'billing_email' ) as $field ) {
					if ( isset( $value[ $field ] ) && '' !== $value[ $field ] && ! is_email( (string) $value[ $field ] ) ) {
						$value[ $field ] = '';
						$changed         = true;
					}
				}

				if ( $changed ) {
					$this->_data[ $key ] = $value;
					$this->_dirty        = true;
				}
			}

			return $value;
		}
	}
}
