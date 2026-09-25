<?php

namespace WC_Asaas\Split\Hook;

use WC_Asaas\Split\Service\Split_Legacy_Service;
use WC_Order;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Split\Adapter\Split_Payment_Method_Object_Setting_Array_To_Data_Adapter;
use WC_Asaas\Split\Adapter\Split_Payment_Method_Object_Setting_Data_To_Array_Adapter;
use WC_Asaas\Split\Service\Split_Gateway_Log_Service;
use WC_Asaas\Split\Service\Split_Settings_Service;
use WC_Asaas\Split\Service\Split_WC_Order_Service;
use WC_Asaas\WC_Asaas;

class Split_Checkout_Hook {

	public function __construct() {
		add_filter( 'woocommerce_asaas_payment_data', array( $this, 'split_payment_data' ), 10, 3 );
		add_filter( 'woocommerce_payment_successful_result', array( $this, 'add_split_info_order_note' ), 10, 3 );
	}

	public function split_payment_data( array $payment_data, WC_Order $wc_order, Gateway $gateway ) {
		( new Split_Legacy_Service( $gateway ) )->ensure_legacy_settings_are_removed();

		$split_settings = $gateway->settings[ Split_Settings_Service::SETTING_NAME ];

		if ( null === $split_settings || '' === $split_settings || !is_array($split_settings) || empty($split_settings) ) {
			return $payment_data;
		}

		$settings = array_map(
			function( $setting_array ) use ( $gateway ) {
				$setting = ( new Split_Payment_Method_Object_Setting_Array_To_Data_Adapter( $setting_array ) )->adapt( $gateway );
				return $setting;
			}, $split_settings
		);

		( new Split_Gateway_Log_Service() )->log( $gateway, $settings );

		$payment_data['split'] = array();
		foreach ( $settings as $setting ) {
			$payment_data['split'][] = ( new Split_Payment_Method_Object_Setting_Data_To_Array_Adapter( $setting ) )->adapt_to_asaas_api();
		}

		return $payment_data;
	}

	public function add_split_info_order_note( $result, $order_id ) {
		$wc_order = wc_get_order( $order_id );

		$order_payment_method = $wc_order->get_payment_method();
		$asaas_gateways_ids   = array_keys( WC_Asaas::get_instance()->get_gateways() );
		if ( ! in_array( $order_payment_method, $asaas_gateways_ids, true ) ) {
			return $result;
		}

		( new Split_WC_Order_Service() )->add_split_info_note( $wc_order );

		return $result;
	}
}
