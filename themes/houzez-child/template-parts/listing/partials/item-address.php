<?php
/**
 * Override do tema filho: esconde a linha de endereço do card quando não há
 * endereço a exibir (evita o ícone de pin vazio).
 *
 * Arquivo pai: houzez/template-parts/listing/partials/item-address.php
 * Todos os modelos de card (item-v1/v2/v4/v7, list e half-map) incluem este
 * partial via get_template_part(), que prioriza o tema filho.
 */
$address_composer = houzez_option( 'listing_address_composer' );
$enabled_data     = isset( $address_composer['enabled'] ) ? $address_composer['enabled'] : array();
$temp_array       = array();

if ( $enabled_data ) {
	unset( $enabled_data['placebo'] );
	foreach ( $enabled_data as $key => $value ) {

		if ( $key == 'address' ) {
			$map_address = houzez_get_listing_data( 'property_map_address' );

			if ( $map_address != '' ) {
				$temp_array[] = $map_address;
			}

		} else if ( $key == 'streat-address' ) {
			$property_address = houzez_get_listing_data( 'property_address' );

			if ( $property_address != '' ) {
				$temp_array[] = $property_address;
			}

		} else if ( $key == 'zip-code' ) {
			$zip_code = houzez_get_listing_data( 'property_zip' );

			if ( $zip_code != '' ) {
				$temp_array[] = $zip_code;
			}

		} else if ( $key == 'country' ) {
			$country = houzez_taxonomy_simple( 'property_country' );

			if ( $country != '' ) {
				$temp_array[] = $country;
			}

		} else if ( $key == 'state' ) {
			$state = houzez_taxonomy_simple( 'property_state' );

			if ( $state != '' ) {
				$temp_array[] = $state;
			}

		} else if ( $key == 'city' ) {
			$city = houzez_taxonomy_simple( 'property_city' );

			if ( $city != '' ) {
				$temp_array[] = $city;
			}

		} else if ( $key == 'area' ) {
			$area = houzez_taxonomy_simple( 'property_area' );

			if ( $area != '' ) {
				$temp_array[] = $area;
			}

		}
	}

	$result = join( ', ', $temp_array );

	// Sem conteúdo: não renderiza nada (sem <address> vazio).
	if ( trim( $result ) === '' ) {
		return;
	}

	echo '<address class="item-address mb-2">';
	echo '<i class="houzez-icon icon-pin me-1" aria-hidden="true"></i>';
	echo '<span>' . $result . '</span>';
	echo '</address>';
}
