<?php

namespace WC_Asaas\Split\Repository;

use WC_Asaas\Gateway\Gateway;

class Split_Legacy_Repository {

	const LEGACY_WALLET_COUNT_KEY = 'wallets';
	const SPLIT_WALLET_KEY        = 'split_wallet';

	public function has_legacy_split_quantity_option( Gateway $gateway ) {
		return isset($gateway->settings[ self::LEGACY_WALLET_COUNT_KEY ]) && null !== $gateway->settings[ self::LEGACY_WALLET_COUNT_KEY ] && '' !== $gateway->settings[ self::LEGACY_WALLET_COUNT_KEY ];
	}

	public function has_legacy_split_wallets( Gateway $gateway ) {
		$split_wallets = $gateway->settings[ self::SPLIT_WALLET_KEY ];

		return is_array( $split_wallets ) && isset( $split_wallets[ self::SPLIT_WALLET_KEY ] );
	}

	public function truncate_legacy_wallets( Gateway $gateway ) {
		$gateway->update_option( self::SPLIT_WALLET_KEY, null );
	}

	public function remove_legacy_split_count_option( Gateway $gateway ) {
		$option_key = $gateway->get_option_key();

		$options = get_option( $option_key, array() );
		unset( $options[ self::LEGACY_WALLET_COUNT_KEY ] );

		update_option( $option_key, $options );
	}
}
