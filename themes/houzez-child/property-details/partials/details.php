<?php
// Child override of houzez/property-details/partials/details.php.
// Difference from parent: custom field values go through
// imovel_parceiro_format_price_display(), so monetary values stored in
// canonical form (1600.00) render in pt-BR format (1.600,00).
global $hide_fields;
$prop_id = houzez_get_listing_data('property_id');
$prop_price = houzez_get_listing_data('property_price');
$prop_size = houzez_get_listing_data('property_size');
$land_area = houzez_get_listing_data('property_land');
$bedrooms = houzez_get_listing_data('property_bedrooms');
$rooms = houzez_get_listing_data('property_rooms');
$bathrooms = houzez_get_listing_data('property_bathrooms');
$year_built = houzez_get_listing_data('property_year');
$garage = houzez_get_listing_data('property_garage');
$property_status = houzez_taxonomy_simple('property_status');
$property_type = houzez_taxonomy_simple('property_type');
$garage_size = houzez_get_listing_data('property_garage_size');
$additional_features = get_post_meta( get_the_ID(), 'additional_features', true);
$columns = houzez_option('prop_details_cols', '2');

// Set column class based on the number of columns
$column_class = 'col-md-6'; // default
if($columns == 'list-1-cols') {
    $column_class = 'col-md-12';
} elseif($columns == 'list-3-cols') {
    $column_class = 'col-xl-4 col-lg-6 col-md-6 col-sm-12';
}
?>
<div class="detail-wrap">
    <ul class="row list-lined list-unstyled" role="list">
		<?php
        if( !empty( $prop_id ) && $hide_fields['prop_id'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_prop_id', 'Property ID').'</strong> 
	                    <span>'.houzez_propperty_id_prefix($prop_id).'</span>
                    </div>
                </li>';
        }

        if( $prop_price != "" && $hide_fields['sale_rent_price'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_price', 'Price'). '</strong> 
	                    <span>'.houzez_listing_price().'</span>
                    </div>
                </li>';
        }

        if( !empty( $prop_size ) && $hide_fields['area_size'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_prop_size', 'Property Size'). '</strong> 
	                    <span>'.houzez_property_size( 'after' ).'</span>
                    </div>
                </li>';
        }

        if( !empty( $land_area ) && $hide_fields['land_area'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_land', 'Land Area'). '</strong> 
	                    <span>'.houzez_property_land_area( 'after' ).'</span>
                    </div>
                </li>';
        }
        if( $bedrooms != "" && $hide_fields['bedrooms'] != 1 ) {
            $bedrooms_label = ($bedrooms > 1 ) ? houzez_option('spl_bedrooms', 'Bedrooms') : houzez_option('spl_bedroom', 'Bedroom');

            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.esc_attr($bedrooms_label).'</strong> 
	                    <span>'.esc_attr( $bedrooms ).'</span>
                    </div>
                </li>';
        }
        if( $rooms != "" && ( isset($hide_fields['rooms']) && $hide_fields['rooms'] != 1 ) ) {
            $rooms_label = ($rooms > 1 ) ? houzez_option('spl_rooms', 'Rooms') : houzez_option('spl_room', 'Room');

            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
                        <strong>'.esc_attr($rooms_label).'</strong> 
                        <span>'.esc_attr( $rooms ).'</span>
                    </div>
                </li>';
        }
        if( $bathrooms != "" && $hide_fields['bathrooms'] != 1 ) {

            $bath_label = ($bathrooms > 1 ) ? houzez_option('spl_bathrooms', 'Bathrooms') : houzez_option('spl_bathroom', 'Bathroom');
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.esc_attr($bath_label).'</strong> 
	                    <span>'.esc_attr( $bathrooms ).'</span>
                    </div>
                </li>';
        }
        if( $garage != "" && $hide_fields['garages'] != 1 ) {

            $garage_label = ($garage > 1 ) ? houzez_option('spl_garages', 'Garages') : houzez_option('spl_garage', 'Garage');
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.esc_attr($garage_label).'</strong> 
	                    <span>'.esc_attr( $garage ).'</span>
                    </div>
                </li>';
        }
        if( !empty( $garage_size ) && $hide_fields['garages'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_garage_size', 'Garage Size').'</strong> 
	                    <span>'.esc_attr( $garage_size ).'</span>
                    </div>
                </li>';
        }
        if( !empty( $year_built ) && $hide_fields['year_built'] != 1 ) {
            echo '<li class="'.$column_class.'">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_year_built', 'Year Built').'</strong> 
	                    <span>'.esc_attr( $year_built ).'</span>
                    </div>
                </li>';
        }
        if( !empty( $property_type ) && ($hide_fields['prop_type']) != 1 ) {
            echo '<li class="'.$column_class.' prop_type">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_prop_type', 'Property Type').'</strong> 
	                    <span>'.esc_attr( $property_type ).'</span>
                    </div>
                </li>';
        }
        if( !empty( $property_status ) && ($hide_fields['prop_status']) != 1 ) {
            echo '<li class="'.$column_class.' prop_status">
                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
	                    <strong>'.houzez_option('spl_prop_status', 'Property Status').'</strong> 
	                    <span>'.esc_attr( $property_status ).'</span>
                    </div>
                </li>';
        }

        //Custom Fields
        if(class_exists('Houzez_Fields_Builder')) {
        $fields_array = Houzez_Fields_Builder::get_form_fields(); 

            if(!empty($fields_array)) {
                foreach ( $fields_array as $value ) {

                    $field_type = $value->type;
                    $meta_type = true;

                    if( $field_type == 'checkbox_list' || $field_type == 'multiselect' ) {
                        $meta_type = false;
                    }

                    $data_value = get_post_meta( get_the_ID(), 'fave_'.$value->field_id, $meta_type );
                    $field_title = $value->label;
                    $field_id = houzez_clean_20($value->field_id);
                    
                    $field_title = houzez_wpml_translate_single_string($field_title);

                    if( $meta_type == true ) {
                        $data_value = houzez_wpml_translate_single_string($data_value);
                    } else {
                        $data_value = houzez_array_to_comma($data_value);
                    }

                    $data_value = imovel_parceiro_format_price_display( $value->field_id, $data_value );

                    if( $field_type == "url" ) {

                        if(!empty($data_value) && $hide_fields[$field_id] != 1) {
                            echo '<li class="'.$column_class.' '.esc_attr($field_id).'">
                                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
                                        <strong>'.esc_attr($field_title).'</strong> 
                                        <span><a href="'.esc_url($data_value).'" target="_blank">'.esc_attr( $data_value ).'</a></span>
                                    </div>
                                  </li>';
                        } 

                    } else {
                        if(!empty($data_value) && $hide_fields[$field_id] != 1) {
                            echo '<li class="'.$column_class.' '.esc_attr($field_id).'">
                                    <div class="list-lined-item w-100 d-flex justify-content-between py-2">
                                        <strong>'.esc_attr($field_title).'</strong> 
                                        <span>'.esc_attr( $data_value ).'</span>
                                    </div>
                                  </li>';
                        }    
                    }
                    
                }
            }
        }
        ?>
	</ul>
</div>

<?php if( !empty( $additional_features[0]['fave_additional_feature_title'] ) && $hide_fields['additional_details'] != 1 ) { ?>
	<div class="block-title-wrap">
		<h3><?php echo houzez_option('sps_additional_details', 'Additional details'); ?></h3>
	</div><!-- block-title-wrap -->
	<ul class="row list-lined list-unstyled" role="list">
		<?php
        foreach( $additional_features as $ad_del ):

            $feature_title = isset( $ad_del['fave_additional_feature_title'] ) ? $ad_del['fave_additional_feature_title'] : '';
            $feature_value = isset( $ad_del['fave_additional_feature_value'] ) ? $ad_del['fave_additional_feature_value'] : '';

            if( $feature_value != "" ) { 
                echo '<li class="'.$column_class.'">
					<div class="list-lined-item w-100 d-flex justify-content-between py-2">
						<strong>'.esc_attr( $feature_title ).'</strong> <span>'.esc_attr( $feature_value ).'</span>
					</div>
				</li>';
            }
        endforeach;
        ?>
	</ul>	
<?php } ?>
