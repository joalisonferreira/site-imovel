<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps Houzez packages and their persistent WooCommerce subscription products aligned.
 */
class Imovel_Parceiro_Houzez_WooCommerce_Subscriptions {
    const PACKAGE_PRODUCT_META = '_imovel_parceiro_subscription_product_id';
    const PACKAGE_BASEERP_META = '_imovel_parceiro_baseerp_id';
    const PACKAGE_CYCLE_META = '_imovel_parceiro_asaas_billing_cycle';
    const PRODUCT_PACKAGE_META = '_imovel_parceiro_houzez_package_id';
    const FREE_PLAN_META = '_imovel_parceiro_free_plan';
    const FREE_VALIDITY_META = '_imovel_parceiro_free_validity';
    const FREE_VALIDITY_UNIT_META = '_imovel_parceiro_free_validity_unit';

    public function __construct() {
        add_action( 'save_post_houzez_packages', array( $this, 'sync_package' ), 100, 3 );
        add_action( 'updated_post_meta', array( $this, 'sync_after_package_meta_change' ), 20, 4 );
        add_action( 'added_post_meta', array( $this, 'sync_after_package_meta_change' ), 20, 4 );
        add_action( 'before_delete_post', array( $this, 'delete_linked_product' ) );
        add_action( 'add_meta_boxes_houzez_packages', array( $this, 'add_package_metabox' ) );
        add_action( 'save_post_houzez_packages', array( $this, 'save_package_metabox' ), 20, 3 );
        add_filter( 'rwmb_meta_boxes', array( $this, 'replace_package_frequency_fields' ), 100 );
        add_action( 'rwmb_after_save_post', array( $this, 'save_package_billing_cycle' ), 5 );

        // This replaces Houzez Woo Addon's temporary product creation for package purchases.
        add_action( 'wp_ajax_houzez_woo_pay_package', array( $this, 'add_package_to_cart' ), 0 );
        add_action( 'wp_ajax_nopriv_houzez_woo_pay_package', array( $this, 'add_package_to_cart' ), 0 );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'allow_one_managed_package_per_order' ), 20, 3 );

        // Free plans (and every other plan) must be acquired through WooCommerce.
        // Block Houzez's native "free membership package" endpoint.
        add_action( 'wp_ajax_houzez_free_membership_package', array( $this, 'block_native_free_package' ), 1 );
        add_action( 'wp_ajax_nopriv_houzez_free_membership_package', array( $this, 'block_native_free_package' ), 1 );

        add_action( 'woocommerce_subscription_status_active', array( $this, 'activate_houzez_membership' ) );
        add_action( 'woocommerce_subscription_status_changed', array( $this, 'sync_membership_after_status_change' ), 20, 4 );
        // Asaas rejects endDate <= nextDueDate; drop it so the purchase never
        // fails -- expiry is enforced via pending-cancel + Houzez validity.
        add_filter( 'woocommerce_asaas_subscription_payment_data', array( $this, 'fix_asaas_end_date' ), 20, 5 );
        // F4 (CRO): resumo do plano no checkout + próximos passos no obrigado.
        add_action( 'woocommerce_checkout_before_order_review', array( $this, 'render_checkout_plan_summary' ), 20 );
        add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou_next_steps' ), 20 );
        // BaseERP issues NFe (products). Our plans are services billed via
        // Asaas NFS-e, so BaseERP must never attempt them (it fails forever,
        // e.g. "externalReference em formato inválido", spamming order notes).
        add_action( 'woocommerce_payment_complete', array( $this, 'skip_baseerp_for_subscription_orders' ), 20, 1 );
        add_filter( 'baseerp_request_args', array( $this, 'fix_baseerp_external_reference_type' ), 20, 2 );
    }

    public function fix_asaas_end_date( $data, $order = null, $subscription = null, $item = null, $gateway = null ) {
        if ( ! is_array( $data ) || empty( $data['endDate'] ) || empty( $data['nextDueDate'] ) ) {
            return $data;
        }
        if ( strtotime( (string) $data['endDate'] ) <= strtotime( (string) $data['nextDueDate'] ) ) {
            unset( $data['endDate'] );
        }
        return $data;
    }

    /**
     * Whether the order contains one of our subscription package products.
     */
    public static function order_has_subscription_product( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return false;
        }
        foreach ( $order->get_items() as $item ) {
            $product_id = absint( $item->get_product_id() );
            if ( $product_id && get_post_meta( $product_id, self::PRODUCT_PACKAGE_META, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * BaseERP fires on every paid order (woocommerce_payment_complete, prio 10)
     * and retries 5x. Subscription orders are services (Asaas NFS-e), so cancel
     * its scheduled attempts to avoid permanent "externalReference inválido"
     * failures and order-note spam.
     */
    public function skip_baseerp_for_subscription_orders( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! self::order_has_subscription_product( $order ) ) {
            return;
        }
        for ( $retry = 1; $retry <= 5; $retry++ ) {
            as_unschedule_all_actions(
                'baseerp_generate_invoice',
                array( 'order_id' => absint( $order_id ), 'retry_count' => $retry ),
                'baseerp_actions'
            );
        }
        $order->add_order_note( __( 'BaseERP ignorado: pedido de assinatura (serviço). Nota fiscal emitida via Asaas (NFS-e).', 'imovel-parceiro-core' ) );
    }

    /**
     * BaseERP API rejects a numeric externalReference ("formato inválido").
     * Force string typing for every order that still goes through BaseERP.
     */
    public function fix_baseerp_external_reference_type( $args, $order = null ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }
        if ( isset( $args['externalReference'] ) ) {
            $args['externalReference'] = (string) $args['externalReference'];
        }
        if ( isset( $args['number'] ) ) {
            $args['number'] = (string) $args['number'];
        }
        return $args;
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    public static function billing_cycles() {
        return array(
            'monthly' => array( 'label' => __( 'Mensal', 'imovel-parceiro-core' ), 'interval' => 1, 'period' => 'Month' ),
            'biweekly' => array( 'label' => __( 'Quinzenal', 'imovel-parceiro-core' ), 'interval' => 2, 'period' => 'Week' ),
            'bimonthly' => array( 'label' => __( 'Bimestral', 'imovel-parceiro-core' ), 'interval' => 2, 'period' => 'Month' ),
            'quarterly' => array( 'label' => __( 'Trimestral', 'imovel-parceiro-core' ), 'interval' => 3, 'period' => 'Month' ),
            'semiannually' => array( 'label' => __( 'Semestral', 'imovel-parceiro-core' ), 'interval' => 6, 'period' => 'Month' ),
            'yearly' => array( 'label' => __( 'Anual', 'imovel-parceiro-core' ), 'interval' => 1, 'period' => 'Year' ),
        );
    }

    public static function billing_cycle_for_package( $package_id ) {
        $cycle = get_post_meta( $package_id, self::PACKAGE_CYCLE_META, true );
        $cycles = self::billing_cycles();
        if ( isset( $cycles[ $cycle ] ) ) {
            return $cycle;
        }
        $interval = absint( get_post_meta( $package_id, 'fave_billing_unit', true ) );
        $period = strtolower( (string) get_post_meta( $package_id, 'fave_billing_time_unit', true ) );
        foreach ( $cycles as $key => $settings ) {
            if ( $interval === $settings['interval'] && strtolower( $settings['period'] ) === $period ) {
                return $key;
            }
        }
        return 'monthly';
    }

    public function replace_package_frequency_fields( $meta_boxes ) {
        foreach ( $meta_boxes as &$meta_box ) {
            if ( empty( $meta_box['post_types'] ) || ! in_array( 'houzez_packages', (array) $meta_box['post_types'], true ) || empty( $meta_box['fields'] ) ) {
                continue;
            }
            foreach ( $meta_box['fields'] as $index => $field ) {
                if ( isset( $field['id'] ) && 'fave_billing_time_unit' === $field['id'] ) {
                    $meta_box['fields'][ $index ] = array(
                        'id' => self::PACKAGE_CYCLE_META,
                        'name' => __( 'Frequencia de cobranca', 'imovel-parceiro-core' ),
                        'type' => 'select',
                        'options' => wp_list_pluck( self::billing_cycles(), 'label' ),
                        'std' => 'monthly',
                        'columns' => 6,
                        'desc' => __( 'Ciclos aceitos pelo gateway Asaas.', 'imovel-parceiro-core' ),
                    );
                } elseif ( isset( $field['id'] ) && 'fave_billing_unit' === $field['id'] ) {
                    unset( $meta_box['fields'][ $index ] );
                }
            }

            $existing_ids = wp_list_pluck( $meta_box['fields'], 'id' );
            if ( ! in_array( self::FREE_PLAN_META, $existing_ids, true ) ) {
                $meta_box['fields'][] = array(
                    'id' => self::FREE_PLAN_META,
                    'name' => __( 'Plano gratuito', 'imovel-parceiro-core' ),
                    'type' => 'checkbox',
                    'std' => 0,
                    'columns' => 6,
                    'desc' => __( 'Marque para oferecer este plano gratuitamente, com validade definida e sem renovacao automatica.', 'imovel-parceiro-core' ),
                );
                $meta_box['fields'][] = array(
                    'id' => self::FREE_VALIDITY_META,
                    'name' => __( 'Validade do plano gratuito', 'imovel-parceiro-core' ),
                    'type' => 'number',
                    'min' => 1,
                    'std' => 1,
                    'columns' => 6,
                    'desc' => __( 'Quanto tempo o plano gratuito fica valido apos a contratacao.', 'imovel-parceiro-core' ),
                );
                $meta_box['fields'][] = array(
                    'id' => self::FREE_VALIDITY_UNIT_META,
                    'name' => __( 'Unidade da validade', 'imovel-parceiro-core' ),
                    'type' => 'select',
                    'options' => array(
                        'day' => __( 'Dias', 'imovel-parceiro-core' ),
                        'week' => __( 'Semanas', 'imovel-parceiro-core' ),
                        'month' => __( 'Meses', 'imovel-parceiro-core' ),
                        'year' => __( 'Anos', 'imovel-parceiro-core' ),
                    ),
                    'std' => 'day',
                    'columns' => 6,
                    'desc' => __( 'Unidade usada pela validade do plano gratuito.', 'imovel-parceiro-core' ),
                );
            }

            $meta_box['fields'] = array_values( $meta_box['fields'] );
        }
        unset( $meta_box );
        return $meta_boxes;
    }

    public function save_package_billing_cycle( $package_id ) {
        if ( 'houzez_packages' !== get_post_type( $package_id ) ) {
            return;
        }
        $cycles = self::billing_cycles();
        $cycle = get_post_meta( $package_id, self::PACKAGE_CYCLE_META, true );
        if ( ! isset( $cycles[ $cycle ] ) ) {
            return;
        }
        update_post_meta( $package_id, 'fave_billing_unit', $cycles[ $cycle ]['interval'] );
        update_post_meta( $package_id, 'fave_billing_time_unit', $cycles[ $cycle ]['period'] );
    }

    public function add_package_metabox() {
        add_meta_box( 'imovel-parceiro-package-subscription', __( 'Assinatura WooCommerce', 'imovel-parceiro-core' ), array( $this, 'render_package_metabox' ), 'houzez_packages', 'side', 'default' );
    }

    public function render_package_metabox( $post ) {
        wp_nonce_field( 'imovel_parceiro_package_subscription', 'imovel_parceiro_package_subscription_nonce' );
        $baseerp_id = get_post_meta( $post->ID, self::PACKAGE_BASEERP_META, true );
        $product_id = absint( get_post_meta( $post->ID, self::PACKAGE_PRODUCT_META, true ) );
        ?>
        <p>
            <label for="imovel_parceiro_baseerp_id"><strong><?php esc_html_e( 'ID do produto (BaseERP)', 'imovel-parceiro-core' ); ?></strong></label>
            <input class="widefat" id="imovel_parceiro_baseerp_id" name="imovel_parceiro_baseerp_id" type="number" min="0" value="<?php echo esc_attr( $baseerp_id ); ?>" />
        </p>
        <p><?php esc_html_e( 'Este valor sera gravado no campo BaseERP do produto de assinatura.', 'imovel-parceiro-core' ); ?></p>
        <?php if ( $product_id ) : ?>
            <p><a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php printf( esc_html__( 'Produto vinculado: #%d', 'imovel-parceiro-core' ), $product_id ); ?></a></p>
        <?php endif; ?>
        <?php
    }

    public function save_package_metabox( $post_id, $post, $update ) {
        if ( ! isset( $_POST['imovel_parceiro_package_subscription_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['imovel_parceiro_package_subscription_nonce'] ) ), 'imovel_parceiro_package_subscription' ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $baseerp_id = isset( $_POST['imovel_parceiro_baseerp_id'] ) ? absint( $_POST['imovel_parceiro_baseerp_id'] ) : '';
        update_post_meta( $post_id, self::PACKAGE_BASEERP_META, $baseerp_id );
    }

    public function sync_after_package_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( 'houzez_packages' !== get_post_type( $post_id ) || in_array( $meta_key, array( self::PACKAGE_PRODUCT_META, self::PRODUCT_PACKAGE_META ), true ) ) {
            return;
        }
        $this->sync_package( $post_id, get_post( $post_id ), true );
    }

    /**
     * Whether the package was explicitly flagged as a free plan by the admin.
     */
    public static function is_free_package( $package_id ) {
        $value = get_post_meta( absint( $package_id ), self::FREE_PLAN_META, true );

        return in_array( (string) $value, array( '1', 'yes', 'on', 'true' ), true );
    }

    /**
     * Validity configured for a free plan as array( value, unit ).
     */
    public static function free_plan_validity( $package_id ) {
        $package_id = absint( $package_id );

        $value = absint( get_post_meta( $package_id, self::FREE_VALIDITY_META, true ) );
        if ( $value < 1 ) {
            $value = 1;
        }

        $unit = strtolower( (string) get_post_meta( $package_id, self::FREE_VALIDITY_UNIT_META, true ) );
        if ( ! in_array( $unit, array( 'day', 'week', 'month', 'year' ), true ) ) {
            $unit = 'day';
        }

        return array( 'value' => $value, 'unit' => $unit );
    }

    /**
     * Stop automatic renewal on the active subscriptions of a free plan, so the
     * plan ends after its validity and the user is left without a plan.
     */
    private function disable_renewal_for_free_package( $package_id ) {
        static $done = array();

        $package_id = absint( $package_id );
        if ( isset( $done[ $package_id ] ) || ! function_exists( 'wcs_get_subscriptions' ) ) {
            return;
        }
        $done[ $package_id ] = true;

        $product_id = absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) );
        if ( ! $product_id ) {
            return;
        }

        $subscriptions = wcs_get_subscriptions(
            array(
                'product_id' => $product_id,
                'subscription_status' => array( 'active' ),
                'subscriptions_per_page' => 200,
            )
        );

        foreach ( $subscriptions as $subscription ) {
            if ( $subscription && $subscription->can_be_updated_to( 'pending-cancel' ) ) {
                $subscription->update_status( 'pending-cancel', __( 'Plano gratuito com validade definida: a renovacao automatica foi desativada.', 'imovel-parceiro-core' ) );
            }
        }
    }

    public function sync_package( $package_id, $post = null, $update = false ) {
        if ( wp_is_post_revision( $package_id ) || wp_is_post_autosave( $package_id ) || ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Subscription' ) ) {
            return;
        }
        $post = $post ? $post : get_post( $package_id );
        if ( ! $post || 'auto-draft' === $post->post_status ) {
            return;
        }

        $product_id = absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) );
        $product = $product_id ? wc_get_product( $product_id ) : false;
        if ( ! $product || ! $product->is_type( 'subscription' ) ) {
            $product = new WC_Product_Subscription();
        }

        $is_free = self::is_free_package( $package_id );

        if ( $is_free ) {
            // Free plan: priced at zero, billed once per validity period.
            // Length stays 0 (open-ended): the Asaas API rejects an endDate
            // equal to nextDueDate ("A data end deve ser posterior à data do
            // próximo pagamento"), which is exactly what length=1 produces.
            // Expiry is enforced via pending-cancel (disable_renewal_for_free_package)
            // plus the Houzez package_activation validity.
            $validity = self::free_plan_validity( $package_id );
            $period = $this->subscription_period( $validity['unit'] );
            $interval = max( 1, absint( $validity['value'] ) );
            $length = 0;
            $price = '0';
        } else {
            $period = $this->subscription_period( get_post_meta( $package_id, 'fave_billing_time_unit', true ) );
            $interval = max( 1, absint( get_post_meta( $package_id, 'fave_billing_unit', true ) ) );
            $length = 0;
            $price = wc_format_decimal( str_replace( ',', '.', (string) get_post_meta( $package_id, 'fave_package_price', true ) ) );
        }

        $product->set_name( $post->post_title );
        $product->set_status( 'publish' === $post->post_status ? 'publish' : 'draft' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_regular_price( $price );
        $product->set_price( $price );
        $product->update_meta_data( '_subscription_price', $price );
        $product->update_meta_data( '_subscription_period', $period );
        $product->update_meta_data( '_subscription_period_interval', $interval );
        $product->update_meta_data( '_subscription_length', $length );
        $product->update_meta_data( '_subscription_limit', 'active' );
        $product->update_meta_data( '_subscription_one_time_shipping', 'no' );
        $product->update_meta_data( '_wc_min_qty_product', 1 );
        $product->update_meta_data( '_wc_max_qty_product', 1 );
        $product->update_meta_data( self::PRODUCT_PACKAGE_META, $package_id );
        $product->update_meta_data( '_houzez_package_id', $package_id );
        $product->update_meta_data( 'baseerp_id', get_post_meta( $package_id, self::PACKAGE_BASEERP_META, true ) );
        $product_id = $product->save();
        wp_set_object_terms( $product_id, array( $this->subscription_category_id() ), 'product_cat', false );
        update_post_meta( $package_id, self::PACKAGE_PRODUCT_META, $product_id );

        if ( $is_free ) {
            $this->disable_renewal_for_free_package( $package_id );
        }
    }

    private function subscription_period( $houzez_period ) {
        $periods = array( 'day' => 'day', 'week' => 'week', 'month' => 'month', 'year' => 'year' );
        $key = strtolower( trim( (string) $houzez_period ) );
        return isset( $periods[ $key ] ) ? $periods[ $key ] : 'month';
    }

    private function subscription_category_id() {
        $term = get_term_by( 'name', 'Assinaturas', 'product_cat' );
        if ( ! $term ) {
            $term = wp_insert_term( 'Assinaturas', 'product_cat' );
            return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
        }
        return (int) $term->term_id;
    }

    public function delete_linked_product( $post_id ) {
        if ( 'houzez_packages' !== get_post_type( $post_id ) ) {
            return;
        }
        $product_id = absint( get_post_meta( $post_id, self::PACKAGE_PRODUCT_META, true ) );
        if ( $product_id && get_post_meta( $product_id, self::PRODUCT_PACKAGE_META, true ) == $post_id ) {
            if ( function_exists( 'wcs_get_subscriptions_for_product' ) && wcs_get_subscriptions_for_product( $product_id, 'ids', array( 'limit' => 1 ) ) ) {
                wp_update_post( array( 'ID' => $product_id, 'post_status' => 'draft' ) );
                return;
            }
            wp_delete_post( $product_id, true );
        }
    }

    public function add_package_to_cart() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Faca login para contratar uma assinatura.', 'imovel-parceiro-core' ) ), 401 );
        }
        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        if ( $package_id && class_exists( 'Imovel_Parceiro_Package_Access' ) && ! Imovel_Parceiro_Package_Access::user_can_access( $package_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Este plano nao esta disponivel para o seu tipo de conta.', 'imovel-parceiro-core' ) ), 403 );
        }
        if ( class_exists( 'Imovel_Parceiro_Profile_Guard' ) ) {
            $ipc_missing = Imovel_Parceiro_Profile_Guard::missing_fields();
            if ( ! empty( $ipc_missing ) ) {
                wp_send_json_error( array( 'message' => Imovel_Parceiro_Profile_Guard::incomplete_message( $ipc_missing ) ), 403 );
            }
        }
        $product_id = $package_id ? absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) ) : 0;
        $product = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( ! $package_id || 'houzez_packages' !== get_post_type( $package_id ) || 'publish' !== get_post_status( $package_id ) || 'yes' !== get_post_meta( $package_id, 'fave_package_visible', true ) || ! $product || ! $product->is_type( 'subscription' ) || 'publish' !== $product->get_status() || (int) get_post_meta( $product_id, self::PRODUCT_PACKAGE_META, true ) !== $package_id || ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => __( 'Assinatura indisponivel.', 'imovel-parceiro-core' ) ), 400 );
        }
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( get_post_meta( $item['product_id'], self::PRODUCT_PACKAGE_META, true ) ) {
                WC()->cart->remove_cart_item( $key );
            }
        }
        $cart_key = WC()->cart->add_to_cart( $product_id, 1 );
        $cart_key ? wp_send_json_success( array( 'cart_key' => $cart_key, 'checkout_url' => wc_get_checkout_url() ) ) : wp_send_json_error( array( 'message' => __( 'Nao foi possivel adicionar a assinatura ao carrinho.', 'imovel-parceiro-core' ) ), 400 );
    }

    /**
     * Block Houzez's native free-package acquisition. Free plans are real
     * WooCommerce subscription products here, so the purchase must happen in
     * WooCommerce like any other plan. Sends the user back to the plans page.
     */
    public function block_native_free_package() {
        $plans_url = class_exists( 'Imovel_Parceiro_Subscriptions' ) ? Imovel_Parceiro_Subscriptions::plans_url() : home_url( '/' );
        echo esc_url_raw( $plans_url );
        wp_die();
    }

    public function allow_one_managed_package_per_order( $valid, $product_id, $quantity ) {
        if ( ! $valid || ! get_post_meta( $product_id, self::PRODUCT_PACKAGE_META, true ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
            return $valid;
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( get_post_meta( $item['product_id'], self::PRODUCT_PACKAGE_META, true ) && (int) $item['product_id'] !== (int) $product_id ) {
                wc_add_notice( __( 'Apenas uma assinatura pode ser comprada por pedido.', 'imovel-parceiro-core' ), 'error' );
                return false;
            }
        }
        return $valid;
    }

    /**
     * F4: resumo do plano dentro do checkout Woo (antes do resumo do pedido).
     * Só renderiza quando o carrinho tem um produto de assinatura gerenciado.
     */
    public function render_checkout_plan_summary() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }
        $package_id = 0;
        $product = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            $pid = absint( get_post_meta( $item['product_id'], self::PRODUCT_PACKAGE_META, true ) );
            if ( $pid ) {
                $package_id = $pid;
                $product = isset( $item['data'] ) ? $item['data'] : false;
                break;
            }
        }
        if ( ! $package_id ) {
            return;
        }

        $plan_name = get_the_title( $package_id );
        $cycle_key = self::billing_cycle_for_package( $package_id );
        $cycles = self::billing_cycles();
        $cycle_label = isset( $cycles[ $cycle_key ] ) ? $cycles[ $cycle_key ]['label'] : '';
        $price_html = $product ? wc_price( $product->get_price() ) : '';
        ?>
        <div class="ipc-checkout-plan" role="region" aria-label="<?php esc_attr_e( 'Resumo da assinatura', 'imovel-parceiro-core' ); ?>">
            <div class="ipc-checkout-plan__head">
                <span class="ipc-checkout-plan__badge"><?php esc_html_e( 'Assinatura', 'imovel-parceiro-core' ); ?></span>
                <strong class="ipc-checkout-plan__name"><?php echo esc_html( $plan_name ? $plan_name : __( 'Plano', 'imovel-parceiro-core' ) ); ?></strong>
            </div>
            <ul class="ipc-checkout-plan__meta">
                <?php if ( $cycle_label ) : ?>
                    <li><span><?php esc_html_e( 'Cobrança', 'imovel-parceiro-core' ); ?></span><strong><?php echo esc_html( $cycle_label ); ?></strong></li>
                <?php endif; ?>
                <?php if ( '' !== $price_html ) : ?>
                    <li><span><?php esc_html_e( 'Valor', 'imovel-parceiro-core' ); ?></span><strong><?php echo wp_kses_post( $price_html ); ?></strong></li>
                <?php endif; ?>
                <li><span><?php esc_html_e( 'Renovação', 'imovel-parceiro-core' ); ?></span><strong><?php esc_html_e( 'Automática. Cancele quando quiser.', 'imovel-parceiro-core' ); ?></strong></li>
            </ul>
        </div>
        <?php
    }

    /**
     * F4: próximos passos após a compra (só para pedidos com assinatura).
     */
    public function render_thankyou_next_steps( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_customer_id() !== (int) get_current_user_id() ) {
            return;
        }
        $has_plan = false;
        foreach ( $order->get_items() as $item ) {
            if ( get_post_meta( $item->get_product_id(), self::PRODUCT_PACKAGE_META, true ) ) {
                $has_plan = true;
                break;
            }
        }
        if ( ! $has_plan ) {
            return;
        }

        $submit_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard_submit.php' ) : home_url( '/' );
        $verification_url = '';
        if ( class_exists( 'Imovel_Parceiro_Verification_Notifications' ) ) {
            $verification_url = Imovel_Parceiro_Verification_Notifications::user_verification_url( get_current_user_id() );
        }
        if ( ! $verification_url && function_exists( 'houzez_get_template_link_2' ) ) {
            $profile_link = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );
            $verification_url = $profile_link ? add_query_arg( 'hpage', 'verification', $profile_link ) : home_url( '/' );
        }
        $is_verified = ( 'approved' === get_user_meta( get_current_user_id(), 'houzez_verification_status', true ) );
        ?>
        <div class="ipc-thankyou-steps" role="region" aria-label="<?php esc_attr_e( 'Próximos passos', 'imovel-parceiro-core' ); ?>">
            <h3 class="ipc-thankyou-steps__title"><?php esc_html_e( 'Assinatura ativa! O que fazer agora?', 'imovel-parceiro-core' ); ?></h3>
            <div class="ipc-thankyou-steps__actions">
                <a href="<?php echo esc_url( $submit_url ); ?>" class="button alt"><?php esc_html_e( 'Anunciar meu 1º imóvel', 'imovel-parceiro-core' ); ?></a>
                <?php if ( ! $is_verified ) : ?>
                    <a href="<?php echo esc_url( $verification_url ); ?>" class="button"><?php esc_html_e( 'Completar verificação', 'imovel-parceiro-core' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function active_package_for_user( $user_id ) {
        if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
            return 0;
        }
        foreach ( wcs_get_users_subscriptions( absint( $user_id ) ) as $subscription ) {
            if ( ! $subscription->has_status( array( 'active', 'pending-cancel' ) ) ) {
                continue;
            }
            foreach ( $subscription->get_items() as $item ) {
                $package_id = absint( get_post_meta( $item->get_product_id(), self::PRODUCT_PACKAGE_META, true ) );
                if ( $package_id ) {
                    return $package_id;
                }
            }
        }
        return 0;
    }

    /**
     * Assinaturas de plano do usuário que ainda aguardam pagamento (ex.: boleto
     * emitido mas não confirmado). Enquanto existirem, o plano Houzez NÃO está
     * liberado (a liberação ocorre apenas em activate_houzez_membership, no
     * status active) e o usuário não deve contratar outra assinatura.
     *
     * @param int $user_id ID do usuário (ou da agência, quando aplicável).
     * @return array Lista de arrays com chaves subscription, package_id e payment.
     */
    public static function pending_payment_subscriptions_for_user( $user_id ) {
        $found = array();
        if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
            return $found;
        }
        $subscriptions = wcs_get_users_subscriptions( absint( $user_id ) );
        if ( ! is_array( $subscriptions ) ) {
            return $found;
        }
        foreach ( $subscriptions as $subscription ) {
            if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'has_status' ) ) {
                continue;
            }
            if ( ! $subscription->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
                continue;
            }
            if ( method_exists( $subscription, 'needs_payment' ) && ! $subscription->needs_payment() ) {
                continue;
            }
            $package_id = 0;
            if ( method_exists( $subscription, 'get_items' ) ) {
                foreach ( $subscription->get_items() as $item ) {
                    if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
                        continue;
                    }
                    $candidate = absint( get_post_meta( $item->get_product_id(), self::PRODUCT_PACKAGE_META, true ) );
                    if ( $candidate ) {
                        $package_id = $candidate;
                        break;
                    }
                }
            }
            if ( ! $package_id ) {
                continue;
            }
            $found[] = array(
                'subscription' => $subscription,
                'package_id'   => $package_id,
                'payment'      => self::pending_payment_info( $subscription ),
            );
        }
        return $found;
    }

    /**
     * Detalhes de pagamento pendente de uma assinatura (URLs do boleto, QR do
     * Pix e vencimento).
     *
     * @param object $subscription WC_Subscription.
     * @return array Com chaves is_boleto, is_pix, pay_url, ticket_url,
     *               due_date, pix_qr, pix_payload e pix_expires.
     */
    public static function pending_payment_info( $subscription ) {
        $info = array(
            'is_boleto'   => false,
            'is_pix'      => false,
            'pay_url'     => '',
            'ticket_url'  => '',
            'due_date'    => '',
            'pix_qr'      => '',
            'pix_payload' => '',
            'pix_expires' => '',
        );
        if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_payment_method' ) ) {
            return $info;
        }
        if ( 'asaas-ticket' === $subscription->get_payment_method() ) {
            $info['is_boleto'] = true;
        }
        if ( 'asaas-pix' === $subscription->get_payment_method() ) {
            $info['is_pix'] = true;
        }

        $parent = null;
        if ( method_exists( $subscription, 'get_parent_id' ) && function_exists( 'wc_get_order' ) ) {
            $parent_id = absint( $subscription->get_parent_id() );
            if ( $parent_id ) {
                $parent = wc_get_order( $parent_id );
            }
        }

        // Pedido mais recente ainda aguardando pagamento (ex.: renovação
        // manual). Tem prioridade sobre o pedido pai, pois carrega o QR
        // do ciclo atual e não o do primeiro pagamento.
        // ATENÇÃO: o 1º parâmetro é $return_fields ('ids'|'all'), o tipo de
        // pedido vai no 2º ('any' = parent+renewal+switch+resubscribe).
        $target = $parent;
        if ( method_exists( $subscription, 'get_related_orders' ) && function_exists( 'wc_get_order' ) ) {
            foreach ( (array) $subscription->get_related_orders( 'ids', array( 'any' ) ) as $related ) {
                // Defesa extra: se algum filtro devolver objetos em vez de IDs.
                if ( is_object( $related ) && method_exists( $related, 'needs_payment' ) ) {
                    $related_order = $related;
                } else {
                    $related_order = wc_get_order( absint( $related ) );
                }
                if ( ! $related_order || ! method_exists( $related_order, 'needs_payment' ) || ! $related_order->needs_payment() ) {
                    continue;
                }
                if ( ! $target || (int) $related_order->get_id() > (int) $target->get_id() ) {
                    $target = $related_order;
                }
            }
        }

        if ( $target ) {
            if ( method_exists( $target, 'needs_payment' ) && method_exists( $target, 'get_checkout_payment_url' ) && $target->needs_payment() ) {
                $info['pay_url'] = $target->get_checkout_payment_url();
            }
            self::extract_asaas_meta( $target, $info );
        } elseif ( $parent ) {
            self::extract_asaas_meta( $parent, $info );
        }

        return $info;
    }

    /**
     * Lê o meta __ASAAS_ORDER do gateway woo-asaas (JSON do pagamento) e
     * preenche boleto (bankSlipUrl) ou Pix (payload + encodedImage) no $info.
     *
     * @param object $order WC_Order.
     * @param array  $info  Array por referência.
     */
    private static function extract_asaas_meta( $order, &$info ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
            return;
        }
        // Meta __ASAAS_ORDER do gateway woo-asaas: JSON do pagamento com
        // bankSlipUrl (boleto), billingType (BOLETO/PIX) e dueDate (Y-m-d);
        // no Pix inclui ainda payload (copia-e-cola), encodedImage (QR em
        // base64) e expirationDate.
        $raw = $order->get_meta( '__ASAAS_ORDER' );
        if ( '' === (string) $raw ) {
            return;
        }
        $data = json_decode( (string) $raw );
        if ( ! is_object( $data ) ) {
            return;
        }
        if ( isset( $data->billingType ) && 'BOLETO' === strtoupper( (string) $data->billingType ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['is_boleto'] = true;
        }
        if ( isset( $data->billingType ) && 'PIX' === strtoupper( (string) $data->billingType ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['is_pix'] = true;
        }
        if ( isset( $data->bankSlipUrl ) && '' !== (string) $data->bankSlipUrl ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['ticket_url'] = esc_url_raw( (string) $data->bankSlipUrl ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        }
        if ( isset( $data->dueDate ) && '' !== (string) $data->dueDate ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['due_date'] = sanitize_text_field( (string) $data->dueDate ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        }
        if ( isset( $data->payload ) && '' !== (string) $data->payload ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['pix_payload'] = (string) $data->payload; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        }
        if ( isset( $data->encodedImage ) && '' !== (string) $data->encodedImage ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['pix_qr'] = 'data:image/jpeg;base64,' . (string) $data->encodedImage; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        }
        if ( isset( $data->expirationDate ) && '' !== (string) $data->expirationDate ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
            $info['pix_expires'] = sanitize_text_field( (string) $data->expirationDate ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        }
    }

    /**
     * Renderiza o painel "Pagamento pendente" (QR do Pix + copia-e-cola ou
     * link do boleto) para a lista de pending_payment_subscriptions_for_user().
     *
     * @param array $pending_list Lista de arrays com subscription, package_id e payment.
     */
    public static function render_pending_payments( $pending_list ) {
        if ( empty( $pending_list ) || ! is_array( $pending_list ) ) {
            return;
        }
        foreach ( $pending_list as $item ) {
            if ( ! is_array( $item ) || empty( $item['subscription'] ) || empty( $item['payment'] ) ) {
                continue;
            }
            $subscription = $item['subscription'];
            $payment      = $item['payment'];
            $plan_title   = ! empty( $item['package_id'] ) ? get_the_title( absint( $item['package_id'] ) ) : '';
            $amount       = method_exists( $subscription, 'get_total' ) ? wc_price( $subscription->get_total() ) : '';
            ?>
            <div class="houzez-membership mb-4">
                <div class="membership-inner">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 style="margin:0;"><?php esc_html_e( 'Pagamento pendente', 'imovel-parceiro-core' ); ?></h5>
                        <span class="dashboard-label bg-warning"><?php esc_html_e( 'Aguardando pagamento', 'imovel-parceiro-core' ); ?></span>
                    </div>
                    <?php if ( $plan_title ) : ?>
                        <p class="mb-1"><strong><?php echo esc_html( $plan_title ); ?></strong><?php echo $amount ? ' — ' . wp_kses_post( $amount ) : ''; ?></p>
                    <?php elseif ( $amount ) : ?>
                        <p class="mb-1"><strong><?php echo wp_kses_post( $amount ); ?></strong></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $payment['due_date'] ) ) : ?>
                        <p class="mb-3 text-muted"><?php esc_html_e( 'Vencimento:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment['due_date'] ) ) ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $payment['is_pix'] ) && ! empty( $payment['pix_qr'] ) ) : ?>
                        <div class="d-flex flex-column align-items-center text-center" style="gap:12px;">
                            <img src="<?php echo esc_attr( $payment['pix_qr'] ); ?>" alt="QR Code Pix" width="220" height="220" style="width:220px;height:220px;border:1px solid #e2e8f0;border-radius:12px;" />
                            <?php if ( ! empty( $payment['pix_payload'] ) ) : ?>
                                <input type="text" readonly value="<?php echo esc_attr( $payment['pix_payload'] ); ?>" onclick="this.select();" style="width:100%;font-size:12px;" />
                                <button type="button" class="btn btn-primary-outlined" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='<?php echo esc_js( __( 'Código copiado!', 'imovel-parceiro-core' ) ); ?>';"><?php esc_html_e( 'Copiar código Pix', 'imovel-parceiro-core' ); ?></button>
                            <?php endif; ?>
                            <?php if ( ! empty( $payment['pix_expires'] ) ) : ?>
                                <p class="mb-0 text-muted" style="font-size:13px;"><?php esc_html_e( 'QR válido até:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $payment['pix_expires'] ) ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ( ! empty( $payment['is_boleto'] ) && ! empty( $payment['ticket_url'] ) ) : ?>
                        <p><a href="<?php echo esc_url( $payment['ticket_url'] ); ?>" target="_blank" rel="noopener" class="btn btn-primary"><?php esc_html_e( 'Ver boleto', 'imovel-parceiro-core' ); ?></a></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $payment['pay_url'] ) ) : ?>
                        <p class="mt-3 mb-0"><a href="<?php echo esc_url( $payment['pay_url'] ); ?>" class="btn btn-primary"><?php esc_html_e( 'Pagar agora', 'imovel-parceiro-core' ); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }

    public function activate_houzez_membership( $subscription ) {
        $user_id = absint( $subscription->get_user_id() );
        $package_id = self::active_package_for_user( $user_id );
        if ( $user_id && $package_id && function_exists( 'houzez_update_membership_package' ) ) {
            // Houzez subtracts posted listings from this value. Normalize legacy
            // packages with an empty limit before delegating to its API.
            if ( '' === get_post_meta( $package_id, 'fave_package_listings', true ) ) {
                update_post_meta( $package_id, 'fave_package_listings', 0 );
            }
            if ( '' === get_post_meta( $package_id, 'fave_package_featured_listings', true ) ) {
                update_post_meta( $package_id, 'fave_package_featured_listings', 0 );
            }
            houzez_update_membership_package( $user_id, $package_id );
            update_user_meta( $user_id, 'houzez_subscription_detail_status', 'active' );
            $this->sync_subscription_invoice( $subscription, $package_id );
        }
    }

    private function sync_subscription_invoice( $subscription, $package_id ) {
        $subscription_id = absint( $subscription->get_id() );
        if ( ! $subscription_id ) {
            return;
        }
        $invoices = get_posts(
            array(
                'post_type' => 'houzez_invoice',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'meta_key' => '_imovel_parceiro_subscription_id',
                'meta_value' => $subscription_id,
                'fields' => 'ids',
            )
        );
        $invoice_id = ! empty( $invoices ) ? absint( $invoices[0] ) : wp_insert_post(
            array(
                'post_type' => 'houzez_invoice',
                'post_status' => 'publish',
                'post_title' => sprintf( 'Subscription invoice %d', $subscription_id ),
                'post_author' => $subscription->get_user_id(),
            )
        );
        if ( ! $invoice_id || is_wp_error( $invoice_id ) ) {
            return;
        }

        $total = wc_format_decimal( $subscription->get_total() );
        $tax = wc_format_decimal( $subscription->get_total_tax() );
        $date = $subscription->get_date( 'start', 'site' );
        if ( $date ) {
            wp_update_post( array( 'ID' => $invoice_id, 'post_date' => $date, 'post_date_gmt' => get_gmt_from_date( $date ) ) );
        }
        $invoice_meta = array(
            'invoice_billion_for' => get_the_title( $package_id ),
            'invoice_billing_type' => 'Recurring',
            'invoice_item_id' => $package_id,
            'invoice_item_price' => $total,
            'invoice_tax' => $tax,
            'invoice_purchase_date' => $date,
            'invoice_buyer_id' => $subscription->get_user_id(),
            'invoice_payment_method' => $subscription->get_payment_method_title(),
        );
        update_post_meta( $invoice_id, '_houzez_invoice_meta', $invoice_meta );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_buyer', $subscription->get_user_id() );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_type', 'Recurring' );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_for', get_the_title( $package_id ) );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_item_id', $package_id );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_price', $total );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_tax', $tax );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_date', $date );
        update_post_meta( $invoice_id, 'HOUZEZ_invoice_payment_method', $subscription->get_payment_method_title() );
        update_post_meta( $invoice_id, 'invoice_payment_status', 1 );
        update_post_meta( $invoice_id, '_imovel_parceiro_subscription_id', $subscription_id );
    }

    public function sync_membership_after_status_change( $subscription_id, $old_status, $new_status, $subscription ) {
        $user_id = absint( $subscription->get_user_id() );
        if ( ! $user_id || 'active' === $new_status ) {
            return;
        }
        $package_id = absint( get_user_meta( $user_id, 'package_id', true ) );
        $product_id = $package_id ? absint( get_post_meta( $package_id, self::PACKAGE_PRODUCT_META, true ) ) : 0;
        if ( $product_id && ! self::active_package_for_user( $user_id ) ) {
            update_user_meta( $user_id, 'houzez_subscription_detail_status', 'expired' );
            delete_user_meta( $user_id, 'package_id' );
            delete_user_meta( $user_id, 'houzez_membership_id' );
        }
    }
}

Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::instance();
