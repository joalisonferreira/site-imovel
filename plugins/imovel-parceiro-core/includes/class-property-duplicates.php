<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Property_Duplicates {
    const OPTION_KEY = 'imovel_parceiro_property_duplicates_settings';

    public function __construct() {
        add_action( 'init', array( $this, 'handle_dashboard_request' ) );
        add_filter( 'houzez_before_submit_property', array( $this, 'validate_before_save' ) );
        add_filter( 'houzez_before_update_property', array( $this, 'validate_before_save' ) );

        // Block the native "Duplicate property" action while the rule is enabled.
        add_action( 'wp_ajax_houzez_property_clone', array( $this, 'block_clone_when_enabled' ), 1 );

        // Hide the "Duplicate" button in the dashboard property list.
        add_action( 'wp_footer', array( $this, 'maybe_hide_clone_button' ) );
    }

    public function block_clone_when_enabled() {
        if ( empty( self::get_settings()['enabled'] ) ) {
            return;
        }

        wp_send_json_error( array( 'message' => __( 'A duplicação de imóveis está desativada enquanto o bloqueio de imóveis duplicados está ativo.', 'imovel-parceiro-core' ) ) );
    }

    public function maybe_hide_clone_button() {
        if ( empty( self::get_settings()['enabled'] ) ) {
            return;
        }

        echo '<style>.clone-property{display:none !important;}</style>';
    }

    public static function get_settings() {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $settings = wp_parse_args( $settings, array( 'enabled' => 1 ) );
        $settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;

        return $settings;
    }

    public function handle_dashboard_request() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( ! isset( $_POST['imovel_duplicates_action'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_imovel_duplicates_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_imovel_duplicates_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'imovel_duplicates_update' ) ) {
            wp_die( esc_html__( 'Operação não autorizada.', 'imovel-parceiro-core' ) );
        }

        if ( 'save' !== sanitize_key( wp_unslash( $_POST['imovel_duplicates_action'] ) ) ) {
            return;
        }

        $settings = self::get_settings();
        $settings['enabled'] = ! empty( $_POST['imovel_duplicates_enabled'] ) ? 1 : 0;
        update_option( self::OPTION_KEY, $settings, false );

        $this->redirect_with_notice( 'updated', __( 'Configuração de imóveis duplicados salva.', 'imovel-parceiro-core' ) );
    }

    public static function render_dashboard_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();
        $notice_type = isset( $_GET['imovel_duplicates_notice'] ) ? sanitize_key( wp_unslash( $_GET['imovel_duplicates_notice'] ) ) : '';
        $notice_text = isset( $_GET['imovel_duplicates_message'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_duplicates_message'] ) ) : '';
        ?>
        <div class="imovel-parceiro-admin-section" style="padding:16px; border:1px solid #e6e6e6; border-radius:10px; background:#fff; margin-top:16px;">
            <h4 style="margin:0 0 10px;"><?php esc_html_e( 'Imóveis duplicados', 'imovel-parceiro-core' ); ?></h4>
            <p style="margin:0 0 14px; color:#666;"><?php esc_html_e( 'Bloqueia o registro de imóveis com título, endereço, localização, coordenadas ou código já cadastrados.', 'imovel-parceiro-core' ); ?></p>

            <?php if ( $notice_type && $notice_text ) : ?>
                <div style="margin-bottom:12px; padding:10px 12px; border-radius:8px; border:1px solid <?php echo 'updated' === $notice_type ? '#badbcc' : '#f5c2c7'; ?>; background:<?php echo 'updated' === $notice_type ? '#d1e7dd' : '#f8d7da'; ?>; color:<?php echo 'updated' === $notice_type ? '#0f5132' : '#842029'; ?>;">
                    <?php echo esc_html( $notice_text ); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'imovel_duplicates_update', '_imovel_duplicates_nonce' ); ?>
                <input type="hidden" name="imovel_duplicates_action" value="save" />

                <div style="margin-bottom:14px;">
                    <label class="control control--checkbox" style="font-weight:600;">
                        <input type="checkbox" name="imovel_duplicates_enabled" value="1" <?php checked( (int) $settings['enabled'], 1 ); ?> />
                        <?php esc_html_e( 'Ativar bloqueio de imóveis duplicados', 'imovel-parceiro-core' ); ?>
                        <span class="control__indicator"></span>
                    </label>
                </div>

                <p style="margin:0 0 12px; color:#666; font-size:13px;"><?php esc_html_e( 'A regra é aplicada no cadastro e na edição de imóveis. Endereços e títulos distintos não são bloqueados.', 'imovel-parceiro-core' ); ?></p>

                <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Salvar configuração', 'imovel-parceiro-core' ); ?></button>
            </form>
        </div>
        <?php
    }

    public function validate_before_save( $property ) {
        if ( empty( self::get_settings()['enabled'] ) ) {
            return $property;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return $property;
        }

        $current_property_id = $this->get_current_property_id();
        $candidate = $this->build_candidate_signature();

        if ( ! $this->has_minimum_data( $candidate ) ) {
            return $property;
        }

        if ( $this->find_duplicate_property_id( $candidate, $current_property_id, $user_id ) ) {
            wp_die( esc_html__( 'Ja existe um imovel cadastrado com os mesmos dados principais. Revise o endereco, o titulo ou o codigo do imovel antes de enviar.', 'imovel-parceiro-core' ) );
        }

        return $property;
    }

    private function get_current_property_id() {
        foreach ( array( 'id', 'prop_id', 'draft_property_id', 'property_id' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $post_id = absint( $_POST[ $key ] );
                if ( $post_id && 'property' === get_post_type( $post_id ) ) {
                    return $post_id;
                }
            }
        }

        return 0;
    }

    private function build_candidate_signature() {
        $raw_title = '';
        if ( isset( $_POST['property_title'] ) ) {
            $raw_title = sanitize_text_field( wp_unslash( $_POST['property_title'] ) );
        } elseif ( isset( $_POST['prop_title'] ) ) {
            $raw_title = sanitize_text_field( wp_unslash( $_POST['prop_title'] ) );
        }

        $address_parts = array();
        foreach ( array( 'fave_property_map_address', 'fave_property_address' ) as $key ) {
            if ( ! isset( $_POST[ $key ] ) ) {
                continue;
            }

            $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
            if ( '' !== $value ) {
                $address_parts[] = $value;
            }
        }

        foreach ( array( 'property_address', 'address' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                if ( '' !== $value ) {
                    $address_parts[] = $value;
                }
            }
        }

        $address_parts = array_values( array_unique( array_filter( $address_parts ) ) );

        $location_parts = array();
        foreach ( array( 'property_country', 'property_state', 'property_city', 'property_area', 'postal_code' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                if ( '' !== $value ) {
                    $location_parts[] = $value;
                }
            }
        }

        $property_code = '';
        if ( isset( $_POST['property_id'] ) && ! $this->is_property_post_id( $_POST['property_id'] ) ) {
            $property_code = sanitize_text_field( wp_unslash( $_POST['property_id'] ) );
        }

        $latitude = '';
        $longitude = '';
        if ( isset( $_POST['houzez_geolocation_lat'] ) ) {
            $latitude = trim( (string) wp_unslash( $_POST['houzez_geolocation_lat'] ) );
        }
        if ( isset( $_POST['houzez_geolocation_long'] ) ) {
            $longitude = trim( (string) wp_unslash( $_POST['houzez_geolocation_long'] ) );
        }

        return array(
            'title' => $this->normalize_text( $raw_title ),
            'address' => $this->normalize_text( implode( ' ', $address_parts ) ),
            'location' => $this->normalize_text( implode( ' ', $location_parts ) ),
            'property_code' => $this->normalize_text( $property_code ),
            'lat' => $this->normalize_coordinate( $latitude ),
            'lng' => $this->normalize_coordinate( $longitude ),
        );
    }

    private function is_property_post_id( $value ) {
        $post_id = absint( $value );
        return $post_id > 0 && 'property' === get_post_type( $post_id );
    }

    private function has_minimum_data( $candidate ) {
        return ! empty( $candidate['title'] ) || ! empty( $candidate['address'] ) || ! empty( $candidate['property_code'] ) || ( ! empty( $candidate['lat'] ) && ! empty( $candidate['lng'] ) );
    }

    private function normalize_text( $value ) {
        $value = remove_accents( (string) $value );
        $value = strtolower( $value );
        $value = preg_replace( '/[^a-z0-9]+/i', '', $value );

        return is_string( $value ) ? $value : '';
    }

    private function normalize_coordinate( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '';
        }

        if ( strpos( $value, ',' ) !== false ) {
            $parts = array_map( 'trim', explode( ',', $value ) );
            $value = $parts[0];
        }

        if ( ! is_numeric( $value ) ) {
            return '';
        }

        return number_format( (float) $value, 6, '.', '' );
    }

    private function find_duplicate_property_id( $candidate, $current_property_id, $user_id ) {
        $query = new WP_Query( array(
            'post_type' => 'property',
            'post_status' => array( 'publish', 'pending', 'draft', 'private' ),
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'suppress_filters' => true,
            'post__not_in' => $current_property_id ? array( $current_property_id ) : array(),
        ) );

        if ( empty( $query->posts ) ) {
            return 0;
        }

        foreach ( $query->posts as $post_id ) {
            $post_id = (int) $post_id;
            if ( $current_property_id && $post_id === $current_property_id ) {
                continue;
            }

            $existing_title = $this->normalize_text( get_the_title( $post_id ) );
            $existing_address = $this->normalize_text( (string) get_post_meta( $post_id, 'fave_property_map_address', true ) . ' ' . (string) get_post_meta( $post_id, 'fave_property_address', true ) );
            $existing_code = $this->normalize_text( (string) get_post_meta( $post_id, 'fave_property_id', true ) );
            $existing_location = $this->normalize_text( (string) get_post_meta( $post_id, 'fave_property_country', true ) . ' ' . (string) get_post_meta( $post_id, 'fave_property_state', true ) . ' ' . (string) get_post_meta( $post_id, 'fave_property_city', true ) . ' ' . (string) get_post_meta( $post_id, 'fave_property_area', true ) . ' ' . (string) get_post_meta( $post_id, 'fave_property_zip', true ) );
            $existing_lat = $this->normalize_coordinate( (string) get_post_meta( $post_id, 'houzez_geolocation_lat', true ) );
            $existing_lng = $this->normalize_coordinate( (string) get_post_meta( $post_id, 'houzez_geolocation_long', true ) );

            $same_code = $candidate['property_code'] && $existing_code && $candidate['property_code'] === $existing_code;
            $same_coordinates = $candidate['lat'] && $candidate['lng'] && $candidate['lat'] === $existing_lat && $candidate['lng'] === $existing_lng;
            $same_address = $candidate['address'] && $existing_address && false !== strpos( $existing_address, $candidate['address'] );
            $same_title = $candidate['title'] && $existing_title && $candidate['title'] === $existing_title;
            $same_location = $candidate['location'] && $existing_location && $candidate['location'] === $existing_location;

            if ( $same_code || ( $same_title && ( $same_address || $same_location || $same_coordinates ) ) || ( $same_address && ( $same_coordinates || $same_location ) ) ) {
                return $post_id;
            }
        }

        return 0;
    }

    private function redirect_with_notice( $type, $message ) {
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/' );

        $redirect = add_query_arg(
            array(
                'imovel_admin_area' => 'gestao',
                'imovel_admin_section' => 'duplicados',
                'imovel_duplicates_notice' => sanitize_key( $type ),
                'imovel_duplicates_message' => wp_strip_all_tags( $message ),
            ),
            $dashboard_url
        );

        wp_safe_redirect( $redirect );
        exit;
    }
}

new Imovel_Parceiro_Property_Duplicates();
