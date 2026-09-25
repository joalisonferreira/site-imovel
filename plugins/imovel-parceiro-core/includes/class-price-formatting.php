<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Price_Formatting {
    private $rent_status_terms = array( 'rent', 'rental', 'aluguel', 'locacao', 'locação', 'arrendamento', 'lease' );

    public function __construct() {
        add_filter( 'houzez_before_submit_property', array( $this, 'sanitize_price_fields' ) );
        add_filter( 'houzez_before_update_property', array( $this, 'sanitize_price_fields' ) );
        add_action( 'houzez_after_property_submit', array( $this, 'save_additional_price_meta' ), 20, 1 );
        add_action( 'houzez_after_property_update', array( $this, 'save_additional_price_meta' ), 20, 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_inline_styles' ), 20 );
    }

    public static function is_rental_property( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return false;
        }

        $terms = wp_get_post_terms( $property_id, 'property_status', array( 'fields' => 'all' ) );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return false;
        }

        foreach ( $terms as $term ) {
            $slug = self::normalize_term_text( $term->slug );
            $name = self::normalize_term_text( $term->name );

            foreach ( array( $slug, $name ) as $value ) {
                if ( '' === $value ) {
                    continue;
                }

                foreach ( array( 'rent', 'rental', 'aluguel', 'locacao', 'arrendamento', 'lease' ) as $needle ) {
                    if ( false !== strpos( $value, $needle ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function sanitize_price_fields( $property ) {
        $property_price = $this->normalize_required_numeric_post_value( 'property_price', __( 'O campo de preco de venda ou aluguel e obrigatorio.', 'imovel-parceiro-core' ) );
        $_POST['prop_price'] = $property_price;

        $property_sec_price = $this->normalize_optional_numeric_post_value( 'property_sec_price', __( 'O segundo preco informado e invalido.', 'imovel-parceiro-core' ) );
        if ( '' !== $property_sec_price ) {
            $_POST['prop_sec_price'] = $property_sec_price;
        } else {
            $_POST['prop_sec_price'] = '';
        }

        $this->normalize_optional_numeric_post_value( 'fave_valor-do-condominio', __( 'O valor do condominio informado e invalido.', 'imovel-parceiro-core' ) );
        $this->normalize_optional_numeric_post_value( 'fave_valor-do-iptu', __( 'O valor do IPTU informado e invalido.', 'imovel-parceiro-core' ) );

        $this->clear_post_value( 'property_price_prefix' );
        $this->clear_post_value( 'prop_price_prefix' );
        $this->clear_post_value( 'property_price_postfix' );
        $this->clear_post_value( 'property_price_placeholder' );
        $this->clear_post_value( 'prop_price_placeholder' );
        $this->clear_post_value( 'show_price_placeholder' );
        $this->clear_post_value( 'prop_label' );

        return $property;
    }

    public function save_additional_price_meta( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return;
        }

        $condominio = isset( $_POST['fave_valor-do-condominio'] ) ? sanitize_text_field( wp_unslash( $_POST['fave_valor-do-condominio'] ) ) : '';
        $iptu = isset( $_POST['fave_valor-do-iptu'] ) ? sanitize_text_field( wp_unslash( $_POST['fave_valor-do-iptu'] ) ) : '';

        if ( '' !== $condominio ) {
            update_post_meta( $property_id, 'fave_valor-do-condominio', $condominio );
        } else {
            delete_post_meta( $property_id, 'fave_valor-do-condominio' );
        }

        if ( '' !== $iptu ) {
            update_post_meta( $property_id, 'fave_valor-do-iptu', $iptu );
        } else {
            delete_post_meta( $property_id, 'fave_valor-do-iptu' );
        }
    }

    public function maybe_enqueue_inline_styles() {
        if ( ! function_exists( 'is_singular' ) ) {
            return;
        }

        if ( ! is_singular( 'property' ) && ! ( function_exists( 'houzez_is_dashboard' ) && houzez_is_dashboard() ) ) {
            return;
        }

        wp_add_inline_style(
            'imovel-parceiro-core',
            '.imovel-parceiro-hidden-price-field{display:none !important;}'
        );
    }

    private function normalize_required_numeric_post_value( $key, $required_message ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            wp_die( esc_html( $required_message ) );
        }

        $raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        $value = $this->parse_localized_number( $raw );

        if ( false === $value || '' === $value ) {
            wp_die( esc_html( $required_message ) );
        }

        $_POST[ $key ] = $value;

        return $value;
    }

    private function normalize_optional_numeric_post_value( $key, $invalid_message ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            return '';
        }

        $raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        if ( '' === trim( $raw ) ) {
            $_POST[ $key ] = '';
            return '';
        }

        $value = $this->parse_localized_number( $raw );
        if ( false === $value ) {
            wp_die( esc_html( $invalid_message ) );
        }

        $_POST[ $key ] = $value;
        return $value;
    }

    private function clear_post_value( $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            $_POST[ $key ] = '';
        }
    }

    private function parse_localized_number( $raw ) {
        $value = trim( (string) $raw );
        if ( '' === $value ) {
            return '';
        }

        if ( preg_match( '/[^0-9\.,\s]/', $value ) ) {
            return false;
        }

        $value = preg_replace( '/\s+/', '', $value );
        $last_comma = strrpos( $value, ',' );
        $last_dot = strrpos( $value, '.' );
        $decimal_pos = max( false === $last_comma ? -1 : $last_comma, false === $last_dot ? -1 : $last_dot );

        $int_part = $value;
        $decimal_part = '';

        if ( $decimal_pos > -1 ) {
            $raw_decimal = preg_replace( '/\D+/', '', substr( $value, $decimal_pos + 1 ) );
            if ( '' !== $raw_decimal && strlen( $raw_decimal ) <= 2 ) {
                $int_part = substr( $value, 0, $decimal_pos );
                $decimal_part = substr( $raw_decimal, 0, 2 );
            }
        }

        $int_part = preg_replace( '/\D+/', '', $int_part );
        if ( '' === $int_part ) {
            $int_part = '0';
        }

        $int_part = ltrim( $int_part, '0' );
        if ( '' === $int_part ) {
            $int_part = '0';
        }

        if ( '' !== $decimal_part ) {
            return $int_part . '.' . $decimal_part;
        }

        return $int_part;
    }

    private static function normalize_term_text( $value ) {
        $value = remove_accents( (string) $value );
        $value = strtolower( $value );
        return preg_replace( '/[^a-z0-9]+/i', '', $value );
    }
}

new Imovel_Parceiro_Price_Formatting();