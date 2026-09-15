<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$dashboard_url = houzez_get_template_link_2( 'template/user_dashboard.php' );
$current_section = isset( $_GET['imovel_admin_section'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_section'] ) ) : '';
$current_action = isset( $_GET['imovel_admin_action'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_action'] ) ) : 'list';
$current_id = isset( $_GET['imovel_admin_id'] ) ? absint( wp_unslash( $_GET['imovel_admin_id'] ) ) : 0;
$current_page = max( 1, isset( $_GET['imovel_admin_paged'] ) ? absint( wp_unslash( $_GET['imovel_admin_paged'] ) ) : 1 );
$per_page = isset( $_GET['imovel_admin_per_page'] ) ? absint( wp_unslash( $_GET['imovel_admin_per_page'] ) ) : 20;
if ( $per_page < 1 || ! in_array( $per_page, array( 10, 25, 50, 100 ), true ) ) {
    $per_page = 20;
}

$entity_configs = array(
    'agents' => array(
        'label' => __( 'Corretores', 'imovel-parceiro-core' ),
        'post_type' => 'houzez_agent',
        'description' => __( 'Gerencie corretores no dashboard.', 'imovel-parceiro-core' ),
        'icon' => 'users',
        'color' => 'bg-blue-50 text-blue-600',
    ),
    'agencies' => array(
        'label' => __( 'Imobiliárias', 'imovel-parceiro-core' ),
        'singular' => __( 'Imobiliária', 'imovel-parceiro-core' ),
        'title_create' => __( 'Nova Imobiliária', 'imovel-parceiro-core' ),
        'title_edit' => __( 'Editar Imobiliária', 'imovel-parceiro-core' ),
        'post_type' => 'houzez_agency',
        'description' => __( 'Gerencie imobiliárias cadastradas na plataforma.', 'imovel-parceiro-core' ),
        'icon' => 'briefcase',
        'color' => 'bg-indigo-50 text-indigo-600',
    ),
    'packages' => array(
        'label' => __( 'Planos', 'imovel-parceiro-core' ),
        'post_type' => 'houzez_packages',
        'description' => __( 'Gerencie os planos disponíveis.', 'imovel-parceiro-core' ),
        'icon' => 'package',
        'color' => 'bg-violet-50 text-violet-600',
    ),
    'reviews' => array(
        'label' => __( 'Avaliações', 'imovel-parceiro-core' ),
        'post_type' => 'houzez_reviews',
        'description' => __( 'Gerencie avaliações cadastradas.', 'imovel-parceiro-core' ),
        'icon' => 'star',
        'color' => 'bg-amber-50 text-amber-600',
    ),
    'testimonials' => array(
        'label' => __( 'Depoimentos', 'imovel-parceiro-core' ),
        'post_type' => 'houzez_testimonials',
        'description' => __( 'Gerencie depoimentos cadastrados.', 'imovel-parceiro-core' ),
        'icon' => 'quote',
        'color' => 'bg-sky-50 text-sky-600',
    ),
    'coupons' => array(
        'label' => __( 'Cupons', 'imovel-parceiro-core' ),
        'singular' => __( 'Cupom', 'imovel-parceiro-core' ),
        'title_create' => __( 'Novo Cupom', 'imovel-parceiro-core' ),
        'title_edit' => __( 'Editar Cupom', 'imovel-parceiro-core' ),
        'post_type' => 'shop_coupon',
        'description' => __( 'Gerencie os cupons de desconto da loja.', 'imovel-parceiro-core' ),
        'icon' => 'percent',
        'color' => 'bg-pink-50 text-pink-600',
    ),
    'approval' => array(
        'label' => __( 'Aprovações de imóveis', 'imovel-parceiro-core' ),
        'post_type' => 'property',
        'description' => __( 'Acompanhe imóveis pendentes de aprovação.', 'imovel-parceiro-core' ),
        'icon' => 'clipboard-check',
        'color' => 'bg-emerald-50 text-emerald-600',
    ),
    'verification_requests' => array(
        'label' => __( 'Verificação de usuários', 'imovel-parceiro-core' ),
        'post_type' => '',
        'description' => __( 'Gerencie solicitações de verificação de usuários.', 'imovel-parceiro-core' ),
        'icon' => 'badge-check',
        'color' => 'bg-rose-50 text-rose-600',
    ),
);

$is_hub = ! isset( $_GET['imovel_admin_section'] );

if ( empty( $current_section ) ) {
    $current_section = 'agents';
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( ! isset( $_POST['_imovel_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_imovel_admin_nonce'] ) ), 'imovel_admin_update' ) ) {
        wp_die( esc_html__( 'Operação não autorizada.', 'imovel-parceiro-core' ) );
    }

    // Handle verification request actions
    if ( isset( $_POST['imovel_admin_verification_action'] ) && 'verification_requests' === ( isset( $_POST['imovel_admin_section'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_section'] ) ) : '' ) ) {
        $verification_action = sanitize_key( wp_unslash( $_POST['imovel_admin_verification_action'] ) );
        $user_id = absint( wp_unslash( $_POST['imovel_admin_id'] ) );
        $notes = isset( $_POST['imovel_admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['imovel_admin_notes'] ) ) : '';

        if ( in_array( $verification_action, array( 'reject', 'additional_info', 'revoke' ), true ) && '' === trim( $notes ) ) {
            wp_die( esc_html__( 'Informe uma justificativa para esta ação.', 'imovel-parceiro-core' ) );
        }

        if ( $user_id && class_exists( 'Houzez_User_Verification' ) && isset( $GLOBALS['houzez_user_verification'] ) ) {
            $verification_data = get_user_meta( $user_id, 'houzez_verification_data', true );
            
            if ( $verification_data ) {
                if ( 'approve' === $verification_action ) {
                    $verification_data['status'] = 'approved';
                    $verification_data['processed_on'] = current_time( 'mysql' );
                    update_user_meta( $user_id, 'houzez_verification_data', $verification_data );
                    update_user_meta( $user_id, 'houzez_verification_status', 'approved' );
                } elseif ( 'reject' === $verification_action ) {
                    $verification_data['status'] = 'rejected';
                    $verification_data['processed_on'] = current_time( 'mysql' );
                    $verification_data['rejection_reason'] = $notes;
                    update_user_meta( $user_id, 'houzez_verification_data', $verification_data );
                    update_user_meta( $user_id, 'houzez_verification_status', 'rejected' );
                } elseif ( 'additional_info' === $verification_action ) {
                    $verification_data['status'] = 'additional_info_required';
                    $verification_data['processed_on'] = current_time( 'mysql' );
                    $verification_data['additional_info_request'] = $notes;
                    update_user_meta( $user_id, 'houzez_verification_data', $verification_data );
                    update_user_meta( $user_id, 'houzez_verification_status', 'additional_info_required' );
                } elseif ( 'revoke' === $verification_action ) {
                    $verification_data['status'] = 'pending';
                    $verification_data['processed_on'] = current_time( 'mysql' );
                    $verification_data['revoke_reason'] = $notes;
                    update_user_meta( $user_id, 'houzez_verification_data', $verification_data );
                    update_user_meta( $user_id, 'houzez_verification_status', 'pending' );
                }

                if ( class_exists( 'Imovel_Parceiro_Verification_Notifications' ) ) {
                    Imovel_Parceiro_Verification_Notifications::notify_dashboard_decision( $user_id, $verification_action, $notes, $verification_data );
                }
            }
        }

        $redirect_url = add_query_arg(
            array(
                'imovel_admin_section' => 'verification_requests',
                'imovel_admin_action' => 'list',
            ),
            $dashboard_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    $section = sanitize_key( wp_unslash( $_POST['imovel_admin_section'] ) );
    $entity = isset( $entity_configs[ $section ] ) ? $entity_configs[ $section ] : null;

    if ( $entity ) {
        $post_id = absint( wp_unslash( $_POST['imovel_admin_id'] ) );
        $post_title = sanitize_text_field( wp_unslash( $_POST['imovel_admin_title'] ) );
        $post_content = isset( $_POST['imovel_admin_content'] ) ? wp_kses_post( wp_unslash( $_POST['imovel_admin_content'] ) ) : '';
        $post_status = isset( $_POST['imovel_admin_status'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_status'] ) ) : 'draft';

        $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => $post_status,
            'post_type'    => $entity['post_type'],
        );

        if ( $post_id ) {
            if ( $entity['post_type'] !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
                wp_die( esc_html__( 'Registro inválido para esta seção.', 'imovel-parceiro-core' ) );
            }
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_data['post_author'] = get_current_user_id();
            $post_id = wp_insert_post( $post_data );
        }

        if ( 'agents' === $section && $post_id ) {
            update_post_meta( $post_id, 'fave_agent_email', isset( $_POST['imovel_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['imovel_admin_email'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agent_position', isset( $_POST['imovel_admin_position'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_position'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agent_company', isset( $_POST['imovel_admin_company'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_company'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agent_mobile', isset( $_POST['imovel_admin_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_mobile'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agent_website', isset( $_POST['imovel_admin_website'] ) ? esc_url_raw( wp_unslash( $_POST['imovel_admin_website'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agent_des', isset( $_POST['imovel_admin_description'] ) ? wp_kses_post( wp_unslash( $_POST['imovel_admin_description'] ) ) : '' );
        }

        if ( 'agencies' === $section && $post_id ) {
            update_post_meta( $post_id, 'fave_agency_email', isset( $_POST['imovel_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['imovel_admin_email'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agency_phone', isset( $_POST['imovel_admin_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_phone'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agency_mobile', isset( $_POST['imovel_admin_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_mobile'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agency_web', isset( $_POST['imovel_admin_website'] ) ? esc_url_raw( wp_unslash( $_POST['imovel_admin_website'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agency_address', isset( $_POST['imovel_admin_address'] ) ? wp_kses_post( wp_unslash( $_POST['imovel_admin_address'] ) ) : '' );
            update_post_meta( $post_id, 'fave_agency_des', isset( $_POST['imovel_admin_description'] ) ? wp_kses_post( wp_unslash( $_POST['imovel_admin_description'] ) ) : '' );
        }

        if ( 'packages' === $section && $post_id ) {
            $billing_cycles = class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) ? Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::billing_cycles() : array();
            $billing_cycle = isset( $_POST['imovel_admin_billing_cycle'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_billing_cycle'] ) ) : 'monthly';
            if ( ! isset( $billing_cycles[ $billing_cycle ] ) ) {
                $billing_cycle = 'monthly';
            }
            update_post_meta( $post_id, 'fave_package_price', isset( $_POST['imovel_admin_price'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_price'] ) ) : '' );
            update_post_meta( $post_id, 'fave_package_listings', isset( $_POST['imovel_admin_listings'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_listings'] ) ) : '' );
            update_post_meta( $post_id, 'fave_package_featured_listings', isset( $_POST['imovel_admin_featured_listings'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_featured_listings'] ) ) : '' );
            $ipc_popular = isset( $_POST['imovel_admin_package_popular'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_package_popular'] ) ) : 'no';
            if ( ! in_array( $ipc_popular, array( 'yes', 'no' ), true ) ) { $ipc_popular = 'no'; }
            update_post_meta( $post_id, 'fave_package_popular', $ipc_popular );
            $ipc_visible = isset( $_POST['imovel_admin_package_visible'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_package_visible'] ) ) : ( 'publish' === $post_status ? 'yes' : 'no' );
            if ( ! in_array( $ipc_visible, array( 'yes', 'no' ), true ) ) { $ipc_visible = 'yes'; }
            update_post_meta( $post_id, 'fave_package_visible', $ipc_visible );
            update_post_meta( $post_id, 'fave_unlimited_listings', ! empty( $_POST['imovel_admin_unlimited_listings'] ) ? '1' : '0' );
            update_post_meta( $post_id, 'fave_package_images', isset( $_POST['imovel_admin_images'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_images'] ) ) : '' );
            update_post_meta( $post_id, 'fave_unlimited_images', ! empty( $_POST['imovel_admin_unlimited_images'] ) ? '1' : '0' );
            $is_free = ! empty( $_POST['imovel_admin_free_plan'] ) ? '1' : '0';
            update_post_meta( $post_id, '_imovel_parceiro_free_plan', $is_free );
            $free_validity = isset( $_POST['imovel_admin_free_validity'] ) ? absint( wp_unslash( $_POST['imovel_admin_free_validity'] ) ) : 1;
            if ( $free_validity < 1 ) { $free_validity = 1; }
            update_post_meta( $post_id, '_imovel_parceiro_free_validity', $free_validity );
            $free_unit = isset( $_POST['imovel_admin_free_validity_unit'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_free_validity_unit'] ) ) : 'day';
            if ( ! in_array( $free_unit, array( 'day', 'week', 'month', 'year' ), true ) ) { $free_unit = 'day'; }
            update_post_meta( $post_id, '_imovel_parceiro_free_validity_unit', $free_unit );
            update_post_meta( $post_id, '_imovel_parceiro_asaas_billing_cycle', $billing_cycle );
            update_post_meta( $post_id, 'fave_billing_unit', $billing_cycles[ $billing_cycle ]['interval'] );
            update_post_meta( $post_id, 'fave_billing_time_unit', $billing_cycles[ $billing_cycle ]['period'] );
            update_post_meta( $post_id, '_imovel_parceiro_baseerp_id', isset( $_POST['imovel_admin_baseerp_id'] ) ? absint( $_POST['imovel_admin_baseerp_id'] ) : '' );
            if ( class_exists( 'Imovel_Parceiro_Package_Access' ) ) {
                $allowed_roles = isset( $_POST['imovel_admin_allowed_roles'] ) ? (array) wp_unslash( $_POST['imovel_admin_allowed_roles'] ) : array();
                Imovel_Parceiro_Package_Access::save_allowed_roles( $post_id, $allowed_roles );
            }
            if ( class_exists( 'Imovel_Parceiro_Package_Extras' ) ) {
                $max_agents       = isset( $_POST['imovel_admin_max_agents'] ) ? absint( wp_unslash( $_POST['imovel_admin_max_agents'] ) ) : 0;
                $price_on_request = ! empty( $_POST['imovel_admin_price_on_request'] );
                Imovel_Parceiro_Package_Extras::save_package_fields( $post_id, $max_agents, $price_on_request );
            }
            if ( class_exists( 'Imovel_Parceiro_Package_Access' ) ) {
                $is_custom    = ! empty( $_POST['imovel_admin_custom_plan'] );
                $custom_users = isset( $_POST['imovel_admin_custom_users'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_custom_users'] ) ) : '';
                Imovel_Parceiro_Package_Access::save_custom_plan( $post_id, $is_custom, $custom_users );
            }
        }

        if ( 'reviews' === $section && $post_id ) {
            $stars = absint( wp_unslash( $_POST['imovel_admin_stars'] ) );
            update_post_meta( $post_id, 'review_stars', $stars );
            update_post_meta( $post_id, 'property_review_rating', isset( $_POST['imovel_admin_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_rating'] ) ) : '' );
        }

        if ( 'testimonials' === $section && $post_id ) {
            update_post_meta( $post_id, 'fave_testimonial_position', isset( $_POST['imovel_admin_position'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_position'] ) ) : '' );
            update_post_meta( $post_id, 'fave_testimonial_company', isset( $_POST['imovel_admin_company'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_company'] ) ) : '' );
            update_post_meta( $post_id, 'fave_testimonial_website', isset( $_POST['imovel_admin_website'] ) ? esc_url_raw( wp_unslash( $_POST['imovel_admin_website'] ) ) : '' );
        }

        if ( 'coupons' === $section && $post_id ) {
            $ipc_discount_type = isset( $_POST['imovel_admin_discount_type'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_discount_type'] ) ) : 'percent';
            if ( ! in_array( $ipc_discount_type, array( 'percent', 'recurring_percent' ), true ) ) {
                $ipc_discount_type = 'percent';
            }
            update_post_meta( $post_id, 'discount_type', $ipc_discount_type );
            update_post_meta( $post_id, 'coupon_amount', isset( $_POST['imovel_admin_coupon_amount'] ) ? wc_format_decimal( wp_unslash( $_POST['imovel_admin_coupon_amount'] ) ) : '0' );
            $ipc_date_expires = isset( $_POST['imovel_admin_date_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_date_expires'] ) ) : '';
            update_post_meta( $post_id, 'date_expires', '' !== $ipc_date_expires ? strtotime( $ipc_date_expires ) : '' );
            update_post_meta( $post_id, 'usage_limit', isset( $_POST['imovel_admin_usage_limit'] ) ? absint( wp_unslash( $_POST['imovel_admin_usage_limit'] ) ) : 0 );
            wp_update_post( array(
                'ID'           => $post_id,
                'post_excerpt' => isset( $_POST['imovel_admin_description'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_description'] ) ) : '',
            ) );
        }

        if ( 'approval' === $section && $post_id ) {
            if ( 'publish' === $post_status ) {
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
                if ( class_exists( 'Imovel_Parceiro_Admin_Action_Notifications' ) ) {
                    Imovel_Parceiro_Admin_Action_Notifications::notify_property_owner( $post_id, true );
                }
            }
        }

        $redirect_url = add_query_arg(
            array(
                'imovel_admin_section' => $section,
                'imovel_admin_action'  => 'list',
            ),
            $dashboard_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }
}

if ( 'approve' === $current_action && $current_id ) {
    $post = get_post( $current_id );

    if ( $post && 'property' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
        $context = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ? Imovel_Parceiro_Owner_Workflow::get_property_owner_context( $post->ID ) : array();
        if ( ! empty( $context ) && ( 'aprovada' !== $context['documentation_status'] || 'aprovado' !== $context['approval_status'] ) ) {
            wp_die( esc_html__( 'A documentação do imóvel precisa ser aprovada antes da publicação.', 'imovel-parceiro-core' ) );
        }
        wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ) );
        if ( class_exists( 'Imovel_Parceiro_Admin_Action_Notifications' ) ) {
            Imovel_Parceiro_Admin_Action_Notifications::notify_property_owner( $post->ID, true );
        }
    }

    $redirect_url = add_query_arg(
        array(
            'imovel_admin_section' => $current_section,
            'imovel_admin_action'  => 'list',
        ),
        $dashboard_url
    );

    wp_safe_redirect( $redirect_url );
    exit;
}

$ipc_deletable_sections = array( 'agents', 'agencies', 'packages', 'reviews', 'testimonials', 'coupons' );

if ( 'delete' === $current_action && $current_id && in_array( $current_section, $ipc_deletable_sections, true ) ) {
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( 'agents' === $current_section ) {
        if ( current_user_can( 'delete_users' ) && ! user_can( $current_id, 'manage_options' ) && wp_verify_nonce( $nonce, 'imovel_admin_delete_user_' . $current_id ) ) {
            // wp_delete_user() lives in wp-admin/includes/user.php, which is
            // not loaded on the front end. Load it on demand to avoid a fatal.
            if ( ! function_exists( 'wp_delete_user' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user( $current_id );
        }
    } else {
        $post = get_post( $current_id );
        $entity = isset( $entity_configs[ $current_section ] ) ? $entity_configs[ $current_section ] : null;

        if ( $post && $entity && $post->post_type === $entity['post_type'] && current_user_can( 'delete_post', $post->ID ) && wp_verify_nonce( $nonce, 'imovel_admin_delete_' . $post->ID ) ) {
            wp_delete_post( $post->ID, true );
        }
    }

    $redirect_url = add_query_arg(
        array(
            'imovel_admin_section' => $current_section,
            'imovel_admin_action'  => 'list',
        ),
        $dashboard_url
    );

    wp_safe_redirect( $redirect_url );
    exit;
}

$selected_entity = isset( $entity_configs[ $current_section ] ) ? $entity_configs[ $current_section ] : $entity_configs['agents'];
$verification_status_filter = isset( $_GET['imovel_admin_verification_status'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_verification_status'] ) ) : '';
$verification_requests = array();
$verification_total_pages = 1;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$info_required_count = 0;

if ( 'verification_requests' === $current_section ) {
    if ( class_exists( 'Houzez_User_Verification' ) && isset( $GLOBALS['houzez_user_verification'] ) ) {
        $verification_requests = $GLOBALS['houzez_user_verification']->get_verification_requests( $verification_status_filter );

        usort(
            $verification_requests,
            function( $a, $b ) {
                $a_date = isset( $a['verification_data']['submitted_on'] ) ? strtotime( $a['verification_data']['submitted_on'] ) : 0;
                $b_date = isset( $b['verification_data']['submitted_on'] ) ? strtotime( $b['verification_data']['submitted_on'] ) : 0;

                if ( isset( $a['verification_data']['additional_info_submitted_on'] ) ) {
                    $a_additional_date = strtotime( $a['verification_data']['additional_info_submitted_on'] );
                    if ( $a_additional_date > $a_date ) {
                        $a_date = $a_additional_date;
                    }
                }

                if ( isset( $b['verification_data']['additional_info_submitted_on'] ) ) {
                    $b_additional_date = strtotime( $b['verification_data']['additional_info_submitted_on'] );
                    if ( $b_additional_date > $b_date ) {
                        $b_date = $b_additional_date;
                    }
                }

                return $b_date - $a_date;
            }
        );

        // Count requests by status
        foreach ( $verification_requests as $request ) {
            $verification_data = $request['verification_data'];
            $status = isset( $verification_data['status'] ) ? $verification_data['status'] : '';
            if ( 'pending' === $status ) {
                $pending_count++;
            } elseif ( 'approved' === $status ) {
                $approved_count++;
            } elseif ( 'rejected' === $status ) {
                $rejected_count++;
            } elseif ( 'additional_info_required' === $status ) {
                $info_required_count++;
            }
        }
    }

    $verification_total_items = count( $verification_requests );
    $verification_total_pages = max( 1, (int) ceil( $verification_total_items / $per_page ) );
    if ( $current_page > $verification_total_pages ) {
        $current_page = $verification_total_pages;
    }
    $verification_offset = ( $current_page - 1 ) * $per_page;
    $verification_requests = array_slice( $verification_requests, $verification_offset, $per_page );
} else {
    $base_query_args = array(
        'post_type'      => $selected_entity['post_type'],
        'posts_per_page' => $per_page,
        'paged'          => $current_page,
        'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( 'approval' === $current_section ) {
        $base_query_args['post_status'] = array( 'pending' );
    }

    $items_query = new WP_Query( $base_query_args );
    $items = $items_query->posts;
    $total_pages = max( 1, (int) $items_query->max_num_pages );
}

$edit_post = null;
if ( 'edit' === $current_action && $current_id ) {
    $edit_post = get_post( $current_id );
}

$is_creating = ( 'edit' === $current_action && ! $edit_post );
if ( $is_creating ) {
    $edit_post = (object) array(
        'ID'           => 0,
        'post_title'   => '',
        'post_content' => '',
        'post_status'  => 'publish',
    );
}

$ipc_launcher_groups = array(
    'Pessoas' => array( 'agents', 'agencies', 'verification_requests' ),
    'Planos & conteúdo' => array( 'packages', 'coupons', 'reviews', 'testimonials' ),
    'Imóveis' => array( 'approval' ),
);

$ipc_entity_counts = array();
foreach ( $entity_configs as $ipc_key => $ipc_config ) {
    if ( empty( $ipc_config['post_type'] ) ) {
        continue;
    }
    $ipc_type_counts = wp_count_posts( $ipc_config['post_type'] );
    $ipc_entity_counts[ $ipc_key ] = array(
        (int) ( isset( $ipc_type_counts->publish ) ? $ipc_type_counts->publish : 0 ),
        (int) ( isset( $ipc_type_counts->pending ) ? $ipc_type_counts->pending : 0 ),
    );
}
?>

<div class="imovel-parceiro-admin-section">
    <?php if ( $is_hub ) : ?>
        <div class="mb-6">
            <h4 class="text-xl font-bold tracking-tight text-slate-900"><?php esc_html_e( 'Central de Cadastros', 'imovel-parceiro-core' ); ?></h4>
            <p class="mt-1 text-sm text-slate-500" style="margin-top:4px;"><?php esc_html_e( 'Acesse rapidamente cada módulo. Use os atalhos acima para operação, documentação, marca d\'água e inatividade.', 'imovel-parceiro-core' ); ?></p>
        </div>

        <?php foreach ( $ipc_launcher_groups as $ipc_group_label => $ipc_group_keys ) : ?>
            <div class="mb-6">
                <h5 class="mb-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-400"><?php echo esc_html( $ipc_group_label ); ?></h5>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ( $ipc_group_keys as $key ) : ?>
                        <?php
                        if ( ! isset( $entity_configs[ $key ] ) ) {
                            continue;
                        }
                        $config = $entity_configs[ $key ];
                        $counts = isset( $ipc_entity_counts[ $key ] ) ? $ipc_entity_counts[ $key ] : array( 0, 0 );
                        ?>
                        <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $key, 'imovel_admin_action' => 'list' ), $dashboard_url ) ); ?>" class="ipc-fade-up group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl <?php echo esc_attr( $config['color'] ); ?>">
                                    <?php echo houzez_dash_icon( $config['icon'], 'h-5 w-5' ); ?>
                                </div>
                                <?php if ( $counts[0] > 0 || $counts[1] > 0 ) : ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                                        <?php echo esc_html( number_format_i18n( $counts[0] ) ); ?>
                                        <?php if ( $counts[1] > 0 ) : ?>
                                            <span class="text-amber-600">+<?php echo esc_html( number_format_i18n( $counts[1] ) ); ?> pend.</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-4 font-semibold text-slate-900"><?php echo esc_html( $config['label'] ); ?></p>
                            <p class="mt-0.5 text-sm text-slate-500"><?php echo esc_html( $config['description'] ); ?></p>
                            <span class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 transition-colors group-hover:text-indigo-500">
                                <?php esc_html_e( 'Abrir', 'imovel-parceiro-core' ); ?>
                                <?php echo houzez_dash_icon( 'arrow-right', 'h-4 w-4' ); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ( ! $is_hub ) : ?>

    <?php if ( 'edit' === $current_action && $edit_post ) : ?>
        <div class="mt-5 rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <h5><?php
                $ipc_title_key = 'title_' . ( $is_creating ? 'create' : 'edit' );
                if ( ! empty( $selected_entity[ $ipc_title_key ] ) ) {
                    echo esc_html( $selected_entity[ $ipc_title_key ] );
                } else {
                    printf( esc_html( $is_creating ? __( 'Novo %s', 'imovel-parceiro-core' ) : __( 'Editar %s', 'imovel-parceiro-core' ) ), esc_html( isset( $selected_entity['singular'] ) ? $selected_entity['singular'] : $selected_entity['label'] ) );
                }
            ?></h5>
            <form method="post">
                <input type="hidden" name="imovel_admin_section" value="<?php echo esc_attr( $current_section ); ?>" />
                <input type="hidden" name="imovel_admin_id" value="<?php echo esc_attr( $edit_post->ID ); ?>" />
                <?php wp_nonce_field( 'imovel_admin_update', '_imovel_admin_nonce' ); ?>

                <?php
                $edit_meta = array();
                if ( 'agents' === $current_section ) {
                    $edit_meta = array(
                        'email' => get_post_meta( $edit_post->ID, 'fave_agent_email', true ),
                        'position' => get_post_meta( $edit_post->ID, 'fave_agent_position', true ),
                        'company' => get_post_meta( $edit_post->ID, 'fave_agent_company', true ),
                        'license' => get_post_meta( $edit_post->ID, 'fave_agent_license', true ),
                        'tax_no' => get_post_meta( $edit_post->ID, 'fave_agent_tax_no', true ),
                        'mobile' => get_post_meta( $edit_post->ID, 'fave_agent_mobile', true ),
                        'office_num' => get_post_meta( $edit_post->ID, 'fave_agent_office_num', true ),
                        'fax' => get_post_meta( $edit_post->ID, 'fave_agent_fax', true ),
                        'skype' => get_post_meta( $edit_post->ID, 'fave_agent_skype', true ),
                        'website' => get_post_meta( $edit_post->ID, 'fave_agent_website', true ),
                        'address' => get_post_meta( $edit_post->ID, 'fave_agent_address', true ),
                        'service_area' => get_post_meta( $edit_post->ID, 'fave_agent_service_area', true ),
                        'specialties' => get_post_meta( $edit_post->ID, 'fave_agent_specialties', true ),
                        'description' => get_post_meta( $edit_post->ID, 'fave_agent_des', true ),
                    );
                } elseif ( 'agencies' === $current_section ) {
                    $edit_meta = array(
                        'email' => get_post_meta( $edit_post->ID, 'fave_agency_email', true ),
                        'phone' => get_post_meta( $edit_post->ID, 'fave_agency_phone', true ),
                        'mobile' => get_post_meta( $edit_post->ID, 'fave_agency_mobile', true ),
                        'website' => get_post_meta( $edit_post->ID, 'fave_agency_web', true ),
                        'license' => get_post_meta( $edit_post->ID, 'fave_agency_licenses', true ),
                        'tax_no' => get_post_meta( $edit_post->ID, 'fave_agency_tax_no', true ),
                        'fax' => get_post_meta( $edit_post->ID, 'fave_agency_fax', true ),
                        'language' => get_post_meta( $edit_post->ID, 'fave_agency_language', true ),
                        'address' => get_post_meta( $edit_post->ID, 'fave_agency_address', true ),
                        'description' => get_post_meta( $edit_post->ID, 'fave_agency_des', true ),
                    );
                } elseif ( 'packages' === $current_section ) {
                    $edit_meta = array(
                        'price' => get_post_meta( $edit_post->ID, 'fave_package_price', true ),
                        'listings' => get_post_meta( $edit_post->ID, 'fave_package_listings', true ),
                        'featured_listings' => get_post_meta( $edit_post->ID, 'fave_package_featured_listings', true ),
                        'billing_cycle' => class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) ? Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::billing_cycle_for_package( $edit_post->ID ) : 'monthly',
                        'baseerp_id' => get_post_meta( $edit_post->ID, '_imovel_parceiro_baseerp_id', true ),
                        'allowed_roles' => class_exists( 'Imovel_Parceiro_Package_Access' ) ? Imovel_Parceiro_Package_Access::allowed_roles( $edit_post->ID ) : array(),
                        'max_agents' => class_exists( 'Imovel_Parceiro_Package_Extras' ) ? Imovel_Parceiro_Package_Extras::max_agents( $edit_post->ID ) : 0,
                        'price_on_request' => class_exists( 'Imovel_Parceiro_Package_Extras' ) ? Imovel_Parceiro_Package_Extras::price_on_request( $edit_post->ID ) : false,
                        'custom_plan' => class_exists( 'Imovel_Parceiro_Package_Access' ) ? Imovel_Parceiro_Package_Access::is_custom_plan( $edit_post->ID ) : false,
                        'custom_users' => class_exists( 'Imovel_Parceiro_Package_Access' ) ? implode( ', ', Imovel_Parceiro_Package_Access::custom_user_ids( $edit_post->ID ) ) : '',
                        'tax' => get_post_meta( $edit_post->ID, 'fave_package_tax', true ),
                        'images' => get_post_meta( $edit_post->ID, 'fave_package_images', true ),
                        'popular' => get_post_meta( $edit_post->ID, 'fave_package_popular', true ),
                        'visible' => get_post_meta( $edit_post->ID, 'fave_package_visible', true ),
                        'unlimited_listings' => get_post_meta( $edit_post->ID, 'fave_unlimited_listings', true ),
                        'unlimited_images' => get_post_meta( $edit_post->ID, 'fave_unlimited_images', true ),
                        'free_plan' => class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) ? ( '1' === (string) get_post_meta( $edit_post->ID, '_imovel_parceiro_free_plan', true ) || Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $edit_post->ID ) ) : false,
                        'free_validity' => get_post_meta( $edit_post->ID, '_imovel_parceiro_free_validity', true ),
                        'free_validity_unit' => get_post_meta( $edit_post->ID, '_imovel_parceiro_free_validity_unit', true ),
                    );
                    if ( '' === $edit_meta['visible'] ) { $edit_meta['visible'] = 'publish' === $edit_post->post_status ? 'yes' : 'no'; }
                    if ( '' === $edit_meta['popular'] ) { $edit_meta['popular'] = 'no'; }
                    if ( '' === $edit_meta['free_validity'] ) { $edit_meta['free_validity'] = '1'; }
                    if ( '' === $edit_meta['free_validity_unit'] ) { $edit_meta['free_validity_unit'] = 'day'; }
                } elseif ( 'reviews' === $current_section ) {
                    $edit_meta = array(
                        'stars' => get_post_meta( $edit_post->ID, 'review_stars', true ),
                        'rating' => get_post_meta( $edit_post->ID, 'property_review_rating', true ),
                    );
                } elseif ( 'testimonials' === $current_section ) {
                    $edit_meta = array(
                        'position' => get_post_meta( $edit_post->ID, 'fave_testimonial_position', true ),
                        'company' => get_post_meta( $edit_post->ID, 'fave_testimonial_company', true ),
                        'website' => get_post_meta( $edit_post->ID, 'fave_testimonial_website', true ),
                    );
                } elseif ( 'coupons' === $current_section ) {
                    $ipc_coupon_expires = get_post_meta( $edit_post->ID, 'date_expires', true );
                    $edit_meta = array(
                        'discount_type' => get_post_meta( $edit_post->ID, 'discount_type', true ),
                        'coupon_amount' => get_post_meta( $edit_post->ID, 'coupon_amount', true ),
                        'date_expires' => $ipc_coupon_expires ? date_i18n( 'Y-m-d', (int) $ipc_coupon_expires ) : '',
                        'usage_limit' => get_post_meta( $edit_post->ID, 'usage_limit', true ),
                        'description' => $edit_post->post_excerpt,
                    );
                }
                ?>

                <div style="margin-bottom:12px;">
                    <label for="imovel_admin_title" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Título', 'imovel-parceiro-core' ); ?></label>
                    <input type="text" id="imovel_admin_title" name="imovel_admin_title" value="<?php echo esc_attr( $edit_post->post_title ); ?>" style="width:100%; padding:8px;" />
                </div>

                <?php if ( 'agents' === $current_section ) : ?>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_email" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></label>
                        <input type="email" id="imovel_admin_email" name="imovel_admin_email" value="<?php echo esc_attr( $edit_meta['email'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_position" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Cargo', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_position" name="imovel_admin_position" value="<?php echo esc_attr( $edit_meta['position'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_company" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Empresa', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_company" name="imovel_admin_company" value="<?php echo esc_attr( $edit_meta['company'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_mobile" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Celular', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_mobile" name="imovel_admin_mobile" value="<?php echo esc_attr( $edit_meta['mobile'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_website" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Website', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_website" name="imovel_admin_website" value="<?php echo esc_attr( $edit_meta['website'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_description" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Descrição', 'imovel-parceiro-core' ); ?></label>
                        <textarea id="imovel_admin_description" name="imovel_admin_description" rows="6" style="width:100%; padding:8px;"><?php echo esc_textarea( $edit_meta['description'] ); ?></textarea>
                    </div>
                <?php elseif ( 'agencies' === $current_section ) : ?>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_email" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></label>
                        <input type="email" id="imovel_admin_email" name="imovel_admin_email" value="<?php echo esc_attr( $edit_meta['email'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_phone" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Telefone', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_phone" name="imovel_admin_phone" value="<?php echo esc_attr( $edit_meta['phone'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_mobile" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Celular', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_mobile" name="imovel_admin_mobile" value="<?php echo esc_attr( $edit_meta['mobile'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_website" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Website', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_website" name="imovel_admin_website" value="<?php echo esc_attr( $edit_meta['website'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_address" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Endereço', 'imovel-parceiro-core' ); ?></label>
                        <textarea id="imovel_admin_address" name="imovel_admin_address" rows="4" style="width:100%; padding:8px;"><?php echo esc_textarea( $edit_meta['address'] ); ?></textarea>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_description" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Descrição', 'imovel-parceiro-core' ); ?></label>
                        <textarea id="imovel_admin_description" name="imovel_admin_description" rows="6" style="width:100%; padding:8px;"><?php echo esc_textarea( $edit_meta['description'] ); ?></textarea>
                    </div>
                <?php elseif ( 'packages' === $current_section ) : ?>
                    <style>
                        .ipc-plan-form .ipc-field label{font-size:13px;font-weight:600;color:#334155;display:block;margin-bottom:6px}
                        .ipc-plan-form .ipc-field input[type=text],.ipc-plan-form .ipc-field input[type=number],.ipc-plan-form .ipc-field select{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;font-size:16px;transition:border-color .15s,box-shadow .15s}
                        .ipc-plan-form .ipc-field input:focus,.ipc-plan-form .ipc-field select:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
                        .ipc-plan-form .ipc-field input:disabled{opacity:.5;background:#f1f5f9}
                        .ipc-plan-form .ipc-hint{color:#64748b;font-size:12px;margin:6px 0 0;line-height:1.4}
                        .ipc-plan-form .ipc-card{border:1px solid #eef2f7;border-radius:16px;background:#fff;padding:14px}
                        .ipc-plan-form .ipc-card-title{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:0 0 12px;display:flex;align-items:center;gap:8px}
                        .ipc-plan-form .ipc-switch{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;cursor:pointer;transition:border-color .15s;font-size:13px;font-weight:600;color:#334155;flex-wrap:wrap}
                        .ipc-plan-form .ipc-switch:has(input:checked){border-color:#6366f1;background:#eef2ff}
                        .ipc-plan-form .ipc-switch input{accent-color:#6366f1;width:16px;height:16px;flex-shrink:0}
                        .ipc-plan-form .ipc-switch small{font-weight:400;color:#64748b;font-size:12px}
                        @media(min-width:768px){.ipc-plan-form .ipc-card{padding:18px}.ipc-plan-form .ipc-field input[type=text],.ipc-plan-form .ipc-field input[type=number],.ipc-plan-form .ipc-field select{font-size:14px}}
                    </style>
                    <div class="ipc-plan-form space-y-4 md:space-y-5">
                        <!-- Grupo: Valores -->
                        <div class="ipc-card">
                            <p class="ipc-card-title"><span class="h-7 w-7 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center text-sm">R$</span> <?php esc_html_e( 'Valores e limites', 'imovel-parceiro-core' ); ?></p>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="ipc-field">
                                    <label for="imovel_admin_price"><?php esc_html_e( 'Preço', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_admin_price" name="imovel_admin_price" value="<?php echo esc_attr( $edit_meta['price'] ); ?>" placeholder="Ex.: 99.90" inputmode="decimal" />
                                </div>
                                <div class="ipc-field">
                                    <label for="imovel_admin_featured_listings"><?php esc_html_e( 'Destaques', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_admin_featured_listings" name="imovel_admin_featured_listings" value="<?php echo esc_attr( $edit_meta['featured_listings'] ); ?>" placeholder="Ex.: 5" inputmode="numeric" />
                                </div>
                            </div>
                            <p class="ipc-hint" style="margin-bottom:12px"><?php esc_html_e( 'Use ponto como separador decimal. Destaques = imóveis em destaque inclusos.', 'imovel-parceiro-core' ); ?></p>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="ipc-field">
                                    <label for="imovel_admin_listings"><?php esc_html_e( 'Imóveis', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_admin_listings" name="imovel_admin_listings" value="<?php echo esc_attr( $edit_meta['listings'] ); ?>" placeholder="Ex.: 20" inputmode="numeric" <?php echo ! empty( $edit_meta['unlimited_listings'] ) && '1' === (string) $edit_meta['unlimited_listings'] ? 'disabled' : ''; ?> />
                                </div>
                                <div class="ipc-field">
                                    <label for="imovel_admin_images"><?php esc_html_e( 'Imagens / imóvel', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_admin_images" name="imovel_admin_images" value="<?php echo esc_attr( $edit_meta['images'] ); ?>" placeholder="Ex.: 15" inputmode="numeric" <?php echo ! empty( $edit_meta['unlimited_images'] ) && '1' === (string) $edit_meta['unlimited_images'] ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 mt-3">
                                <label class="ipc-switch">
                                    <input type="checkbox" name="imovel_admin_unlimited_listings" value="1" <?php checked( $edit_meta['unlimited_listings'], '1' ); ?> onchange="this.closest('.ipc-card').querySelector('#imovel_admin_listings').disabled=this.checked" />
                                    <?php esc_html_e( 'Imóveis ilimitados', 'imovel-parceiro-core' ); ?>
                                </label>
                                <label class="ipc-switch">
                                    <input type="checkbox" name="imovel_admin_unlimited_images" value="1" <?php checked( $edit_meta['unlimited_images'], '1' ); ?> onchange="this.closest('.ipc-card').querySelector('#imovel_admin_images').disabled=this.checked" />
                                    <?php esc_html_e( 'Imagens ilimitadas', 'imovel-parceiro-core' ); ?>
                                </label>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 mt-3">
                                <div class="ipc-field">
                                    <label for="imovel_admin_package_popular"><?php esc_html_e( 'É popular / destaque?', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_admin_package_popular" name="imovel_admin_package_popular">
                                        <option value="no" <?php selected( $edit_meta['popular'], 'no' ); ?>><?php esc_html_e( 'Não', 'imovel-parceiro-core' ); ?></option>
                                        <option value="yes" <?php selected( $edit_meta['popular'], 'yes' ); ?>><?php esc_html_e( 'Sim', 'imovel-parceiro-core' ); ?></option>
                                    </select>
                                </div>
                                <div class="ipc-field">
                                    <label for="imovel_admin_package_visible"><?php esc_html_e( 'É visível?', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_admin_package_visible" name="imovel_admin_package_visible">
                                        <option value="yes" <?php selected( $edit_meta['visible'], 'yes' ); ?>><?php esc_html_e( 'Sim', 'imovel-parceiro-core' ); ?></option>
                                        <option value="no" <?php selected( $edit_meta['visible'], 'no' ); ?>><?php esc_html_e( 'Não', 'imovel-parceiro-core' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <p class="ipc-hint" style="margin-top:8px"><?php esc_html_e( 'Popular exibe selo de destaque. Oculto não aparece para contratação.', 'imovel-parceiro-core' ); ?></p>
                        </div>

                        <!-- Grupo: Cobrança e gratuidade -->
                        <div class="ipc-card" style="background:linear-gradient(180deg,#fff 0%,#f8fafc 100%)">
                            <p class="ipc-card-title"><span class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">◷</span> <?php esc_html_e( 'Cobrança e gratuidade', 'imovel-parceiro-core' ); ?></p>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="ipc-field">
                                    <label for="imovel_admin_billing_cycle"><?php esc_html_e( 'Cobrança', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_admin_billing_cycle" name="imovel_admin_billing_cycle">
                                        <?php foreach ( Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::billing_cycles() as $cycle_key => $cycle ) : ?>
                                            <option value="<?php echo esc_attr( $cycle_key ); ?>" <?php selected( $edit_meta['billing_cycle'], $cycle_key ); ?>><?php echo esc_html( $cycle['label'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ipc-field">
                                    <label for="imovel_admin_baseerp_id"><?php esc_html_e( 'Código BaseERP', 'imovel-parceiro-core' ); ?></label>
                                    <input type="number" min="0" id="imovel_admin_baseerp_id" name="imovel_admin_baseerp_id" value="<?php echo esc_attr( $edit_meta['baseerp_id'] ); ?>" placeholder="<?php esc_attr_e( 'Digite o codigo do plano e atualize no baseERP', 'imovel-parceiro-core' ); ?>" />
                                </div>
                            </div>
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/60 p-3 sm:p-4">
                                <label class="ipc-switch" style="background:#fff;border-color:#f59e0b">
                                    <input type="checkbox" name="imovel_admin_free_plan" value="1" <?php checked( $edit_meta['free_plan'] ); ?> onchange="document.getElementById('ipc-free-validity-row').style.display=this.checked?'grid':'none'" />
                                    <span style="font-weight:700;color:#92400e"><?php esc_html_e( 'Plano gratuito', 'imovel-parceiro-core' ); ?></span>
                                </label>
                                <p class="ipc-hint" style="margin:8px 0 0"><?php esc_html_e( 'Quando ativo, o preço vira R$ 0 e a assinatura é criada sem renovação.', 'imovel-parceiro-core' ); ?></p>
                                <div id="ipc-free-validity-row" class="grid grid-cols-2 gap-3 sm:gap-4 mt-3" style="display:<?php echo ! empty( $edit_meta['free_plan'] ) ? 'grid' : 'none'; ?>">
                                    <div class="ipc-field">
                                        <label for="imovel_admin_free_validity_unit"><?php esc_html_e( 'Período do plano', 'imovel-parceiro-core' ); ?></label>
                                        <select id="imovel_admin_free_validity_unit" name="imovel_admin_free_validity_unit">
                                            <option value="day" <?php selected( $edit_meta['free_validity_unit'], 'day' ); ?>><?php esc_html_e( 'Dias', 'imovel-parceiro-core' ); ?></option>
                                            <option value="week" <?php selected( $edit_meta['free_validity_unit'], 'week' ); ?>><?php esc_html_e( 'Semanas', 'imovel-parceiro-core' ); ?></option>
                                            <option value="month" <?php selected( $edit_meta['free_validity_unit'], 'month' ); ?>><?php esc_html_e( 'Meses', 'imovel-parceiro-core' ); ?></option>
                                            <option value="year" <?php selected( $edit_meta['free_validity_unit'], 'year' ); ?>><?php esc_html_e( 'Anos', 'imovel-parceiro-core' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="ipc-field">
                                        <label for="imovel_admin_free_validity"><?php esc_html_e( 'Validade', 'imovel-parceiro-core' ); ?></label>
                                        <input type="number" min="1" id="imovel_admin_free_validity" name="imovel_admin_free_validity" value="<?php echo esc_attr( $edit_meta['free_validity'] ); ?>" placeholder="Ex.: 30" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grupo: Permissões -->
                        <?php if ( class_exists( 'Imovel_Parceiro_Package_Access' ) ) : $ipc_allowed_roles = (array) $edit_meta['allowed_roles']; ?>
                        <div class="ipc-card">
                            <p class="ipc-card-title"><span class="h-7 w-7 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-sm">◐</span> <?php esc_html_e( 'Visibilidade por tipo de conta', 'imovel-parceiro-core' ); ?></p>
                            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                <?php foreach ( Imovel_Parceiro_Package_Access::selectable_roles() as $ipc_role_key => $ipc_role_label ) : ?>
                                    <label class="ipc-switch">
                                        <input type="checkbox" name="imovel_admin_allowed_roles[]" value="<?php echo esc_attr( $ipc_role_key ); ?>" <?php checked( in_array( $ipc_role_key, $ipc_allowed_roles, true ) ); ?> />
                                        <?php echo esc_html( $ipc_role_label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="ipc-hint" style="margin-top:10px"><?php esc_html_e( 'Deixe tudo desmarcado para exibir o plano a todos os tipos.', 'imovel-parceiro-core' ); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ( class_exists( 'Imovel_Parceiro_Package_Extras' ) ) : ?>
                        <div class="ipc-card">
                            <p class="ipc-card-title"><span class="h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">⚙</span> <?php esc_html_e( 'Extras', 'imovel-parceiro-core' ); ?></p>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="ipc-field">
                                    <label for="imovel_admin_max_agents"><?php esc_html_e( 'Corretores', 'imovel-parceiro-core' ); ?></label>
                                    <input type="number" min="0" id="imovel_admin_max_agents" name="imovel_admin_max_agents" value="<?php echo esc_attr( $edit_meta['max_agents'] ); ?>" placeholder="0 = ∞" />
                                </div>
                                <div class="ipc-field">
                                    <label style="visibility:hidden" class="hidden sm:block">&nbsp;</label>
                                    <label class="ipc-switch" style="width:100%">
                                        <input type="checkbox" name="imovel_admin_price_on_request" value="1" <?php checked( $edit_meta['price_on_request'] ); ?> />
                                        <?php esc_html_e( 'Sob consulta', 'imovel-parceiro-core' ); ?>
                                    </label>
                                </div>
                            </div>
                            <p class="ipc-hint" style="margin-top:8px"><?php esc_html_e( 'Corretores: 0 = ilimitado (imobiliárias). Sob consulta oculta o preço.', 'imovel-parceiro-core' ); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ( class_exists( 'Imovel_Parceiro_Package_Access' ) ) : ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label class="ipc-switch" style="background:#fff">
                                <input type="checkbox" name="imovel_admin_custom_plan" value="1" <?php checked( $edit_meta['custom_plan'] ); ?> onchange="document.getElementById('ipc-custom-users-row').style.display=this.checked?'block':'none'" />
                                <span style="font-size:13px;font-weight:700;color:#334155"><?php esc_html_e( 'Plano personalizado (oculto para os demais usuários)', 'imovel-parceiro-core' ); ?></span>
                            </label>
                            <div id="ipc-custom-users-row" style="display:<?php echo ! empty( $edit_meta['custom_plan'] ) ? 'block' : 'none'; ?>;margin-top:12px">
                                <div class="ipc-field">
                                    <label for="imovel_admin_custom_users"><?php esc_html_e( 'Usuários autorizados', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_admin_custom_users" name="imovel_admin_custom_users" value="<?php echo esc_attr( $edit_meta['custom_users'] ); ?>" placeholder="Ex.: 73, corretor2@imovelparceiro.com.br" />
                                    <p class="ipc-hint"><?php esc_html_e( 'Informe IDs ou e-mails separados por vírgula. O plano só aparecerá para esses usuários (e para o admin).', 'imovel-parceiro-core' ); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ( 'reviews' === $current_section ) : ?>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_stars" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Estrelas', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_admin_stars" name="imovel_admin_stars" min="1" max="5" value="<?php echo esc_attr( $edit_meta['stars'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_rating" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Avaliação', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_rating" name="imovel_admin_rating" value="<?php echo esc_attr( $edit_meta['rating'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                <?php elseif ( 'testimonials' === $current_section ) : ?>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_position" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Cargo', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_position" name="imovel_admin_position" value="<?php echo esc_attr( $edit_meta['position'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_company" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Empresa', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_company" name="imovel_admin_company" value="<?php echo esc_attr( $edit_meta['company'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_website" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Website', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_admin_website" name="imovel_admin_website" value="<?php echo esc_attr( $edit_meta['website'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                <?php elseif ( 'coupons' === $current_section ) : ?>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_discount_type" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Tipo de desconto', 'imovel-parceiro-core' ); ?></label>
                        <select id="imovel_admin_discount_type" name="imovel_admin_discount_type" style="width:100%; padding:8px;">
                            <option value="percent" <?php selected( $edit_meta['discount_type'], 'percent' ); ?>><?php esc_html_e( 'Desconto em porcentagem', 'imovel-parceiro-core' ); ?></option>
                            <option value="recurring_percent" <?php selected( $edit_meta['discount_type'], 'recurring_percent' ); ?>><?php esc_html_e( '% de desconto sobre o produto recorrente', 'imovel-parceiro-core' ); ?></option>
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_coupon_amount" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Valor do desconto (%)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" step="0.01" min="0" id="imovel_admin_coupon_amount" name="imovel_admin_coupon_amount" value="<?php echo esc_attr( $edit_meta['coupon_amount'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_date_expires" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Data de validade', 'imovel-parceiro-core' ); ?></label>
                        <input type="date" id="imovel_admin_date_expires" name="imovel_admin_date_expires" value="<?php echo esc_attr( $edit_meta['date_expires'] ); ?>" style="width:100%; padding:8px;" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_usage_limit" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Limite de uso (em itens)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" min="0" id="imovel_admin_usage_limit" name="imovel_admin_usage_limit" value="<?php echo esc_attr( $edit_meta['usage_limit'] ); ?>" style="width:100%; padding:8px;" />
                        <p style="color:#666; font-size:12px; margin:4px 0 0;"><?php esc_html_e( 'Quantidade máxima de vezes que o cupom pode ser utilizado. Deixe 0 para ilimitado.', 'imovel-parceiro-core' ); ?></p>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label for="imovel_admin_description" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Descrição', 'imovel-parceiro-core' ); ?></label>
                        <textarea id="imovel_admin_description" name="imovel_admin_description" rows="4" style="width:100%; padding:8px;"><?php echo esc_textarea( $edit_meta['description'] ); ?></textarea>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom:12px;">
                    <label for="imovel_admin_status" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></label>
                    <select id="imovel_admin_status" name="imovel_admin_status" style="width:100%; padding:8px;">
                        <option value="draft" <?php selected( $edit_post->post_status, 'draft' ); ?>><?php esc_html_e( 'Rascunho', 'imovel-parceiro-core' ); ?></option>
                        <option value="pending" <?php selected( $edit_post->post_status, 'pending' ); ?>><?php esc_html_e( 'Pendente', 'imovel-parceiro-core' ); ?></option>
                        <option value="publish" <?php selected( $edit_post->post_status, 'publish' ); ?>><?php esc_html_e( 'Publicado', 'imovel-parceiro-core' ); ?></option>
                    </select>
                </div>

                <div style="margin-bottom:12px;">
                    <label for="imovel_admin_content" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Conteúdo', 'imovel-parceiro-core' ); ?></label>
                    <textarea id="imovel_admin_content" name="imovel_admin_content" rows="8" style="width:100%; padding:8px;"><?php echo esc_textarea( $edit_post->post_content ); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><?php echo esc_html( $is_creating ? __( 'Criar', 'imovel-parceiro-core' ) : __( 'Salvar alterações', 'imovel-parceiro-core' ) ); ?></button>
            </form>
        </div>
    <?php elseif ( 'view' === $current_action && 'verification_requests' === $current_section && $current_id ) : ?>
        <div class="mt-5 rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <h5><?php esc_html_e( 'Detalhes da Verificação de Usuário', 'imovel-parceiro-core' ); ?></h5>
            <?php
            $user = get_userdata( $current_id );
            $verification_data = array();
            if ( $user && class_exists( 'Houzez_User_Verification' ) && isset( $GLOBALS['houzez_user_verification'] ) ) {
                $verification_data = $GLOBALS['houzez_user_verification']->get_verification_data( $current_id );
            }

            if ( $user && ! empty( $verification_data ) ) {
                $status = isset( $verification_data['status'] ) ? $verification_data['status'] : '';
                ?>
                <div style="margin-bottom:20px;">
                    <h6><?php esc_html_e( 'Informações do Usuário', 'imovel-parceiro-core' ); ?></h6>
                    <p><strong><?php esc_html_e( 'Nome de usuário:', 'imovel-parceiro-core' ); ?></strong> <?php echo $user ? esc_html( $user->display_name ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Email:', 'imovel-parceiro-core' ); ?></strong> <?php echo $user ? esc_html( $user->user_email ) : '-'; ?></p>
                </div>

                <div style="margin-bottom:20px;">
                    <h6><?php esc_html_e( 'Informações de Verificação', 'imovel-parceiro-core' ); ?></h6>
                    <p><strong><?php esc_html_e( 'Nome completo:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['full_name'] ) ? esc_html( $verification_data['full_name'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Tipo de documento:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['document_type'] ) ? esc_html( $verification_data['document_type'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Número do documento:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['document_number'] ) ? esc_html( $verification_data['document_number'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Data de nascimento:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['date_of_birth'] ) ? esc_html( $verification_data['date_of_birth'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Endereço:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['address'] ) ? esc_html( $verification_data['address'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Cidade:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['city'] ) ? esc_html( $verification_data['city'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Estado:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['state'] ) ? esc_html( $verification_data['state'] ) : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'CEP:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['zip'] ) ? esc_html( $verification_data['zip'] ) : '-'; ?></p>
                </div>

                <div style="margin-bottom:20px;">
                    <h6><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></h6>
                    <p>
                        <?php
                        $status_label = '';
                        $status_color = '#999';
                        if ( 'pending' === $status ) {
                            $status_label = __( 'Pendente', 'imovel-parceiro-core' );
                            $status_color = '#856404';
                        } elseif ( 'approved' === $status ) {
                            $status_label = __( 'Aprovado', 'imovel-parceiro-core' );
                            $status_color = '#0f5132';
                        } elseif ( 'rejected' === $status ) {
                            $status_label = __( 'Rejeitado', 'imovel-parceiro-core' );
                            $status_color = '#842029';
                        } elseif ( 'additional_info_required' === $status ) {
                            $status_label = __( 'Informação adicional necessária', 'imovel-parceiro-core' );
                            $status_color = '#664d03';
                        }
                        ?>
                        <span style="color:<?php echo esc_attr( $status_color ); ?>; font-weight:600;">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e( 'Data de submissão:', 'imovel-parceiro-core' ); ?></strong> <?php echo isset( $verification_data['submitted_on'] ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' \à H:i', strtotime( $verification_data['submitted_on'] ) ) ) : '-'; ?></p>
                </div>

                <div>
                    <h6 style="margin-top:24px; margin-bottom:12px;"><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></h6>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field( 'imovel_admin_update', '_imovel_admin_nonce' ); ?>
                            <input type="hidden" name="imovel_admin_section" value="verification_requests" />
                            <input type="hidden" name="imovel_admin_id" value="<?php echo absint( $current_id ); ?>" />
                            <button type="submit" name="imovel_admin_verification_action" value="approve" style="width:100%; background-color:#007bff; color:#fff; border:none; padding:12px; border-radius:4px; cursor:pointer; font-size:14px; font-weight:600;">
                                <?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?>
                            </button>
                        </form>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field( 'imovel_admin_update', '_imovel_admin_nonce' ); ?>
                            <input type="hidden" name="imovel_admin_section" value="verification_requests" />
                            <input type="hidden" name="imovel_admin_id" value="<?php echo absint( $current_id ); ?>" />
                            <button type="submit" name="imovel_admin_verification_action" value="reject" style="width:100%; background-color:#fff; color:#dc3545; border:2px solid #dc3545; padding:10px; border-radius:4px; cursor:pointer; font-size:14px; font-weight:600;">
                                <span style="margin-right:6px;">✕</span><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?>
                            </button>
                        </form>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field( 'imovel_admin_update', '_imovel_admin_nonce' ); ?>
                            <input type="hidden" name="imovel_admin_section" value="verification_requests" />
                            <input type="hidden" name="imovel_admin_id" value="<?php echo absint( $current_id ); ?>" />
                            <button type="submit" name="imovel_admin_verification_action" value="additional_info" style="width:100%; background-color:#fff; color:#0d6efd; border:2px solid #0d6efd; padding:10px; border-radius:4px; cursor:pointer; font-size:14px; font-weight:600;">
                                <span style="margin-right:6px;">ℹ</span><?php esc_html_e( 'Solicitar informações', 'imovel-parceiro-core' ); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div>
                    <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'list' ), $dashboard_url ) ); ?>" class="btn btn-secondary" style="margin-right:8px; margin-top:12px;">
                        <?php esc_html_e( 'Voltar', 'imovel-parceiro-core' ); ?>
                    </a>
                </div>
            <?php } else { ?>
                <p><?php esc_html_e( 'Usuário não encontrado.', 'imovel-parceiro-core' ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'list' ), $dashboard_url ) ); ?>" class="btn btn-secondary">
                    <?php esc_html_e( 'Voltar', 'imovel-parceiro-core' ); ?>
                </a>
            <?php } ?>
        </div>
    <?php else : ?>
        <div class="mt-5 rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                <h5 style="margin:0;"><?php echo esc_html( $selected_entity['label'] ); ?></h5>
                <?php if ( 'verification_requests' !== $current_section ) : ?>
                    <?php if ( 'agents' === $current_section ) : ?>
                        <button type="button" class="btn btn-primary" id="imovel-user-new-btn"><?php esc_html_e( 'Novo usuário', 'imovel-parceiro-core' ); ?></button>
                    <?php else : ?>
                        <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'edit', 'imovel_admin_id' => 0 ), $dashboard_url ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Novo item', 'imovel-parceiro-core' ); ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ( 'verification_requests' === $current_section ) : ?>
                <!-- Houzez Dashboard Layout for Verification Requests -->
                <script>
                    // Store nonce value for AJAX operations
                    window.imovelAdminNonce = '<?php echo wp_create_nonce( 'imovel_admin_update' ); ?>';
                </script>
                <div class="houzez-dashboard" style="margin-top:20px;">
                    <!-- Status summary badges -->
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="badge bg-secondary"><?php echo esc_html( sprintf( __( '%s total de solicitações', 'imovel-parceiro-core' ), $verification_total_items ?? 0 ) ); ?></span>
                        <span class="badge bg-warning text-dark"><?php echo esc_html( sprintf( __( '%s aguardando revisão', 'imovel-parceiro-core' ), $pending_count ) ); ?></span>
                        <span class="badge bg-success"><?php echo esc_html( sprintf( __( '%s aprovados', 'imovel-parceiro-core' ), $approved_count ) ); ?></span>
                        <span class="badge bg-info text-dark"><?php echo esc_html( sprintf( __( '%s informações necessárias', 'imovel-parceiro-core' ), $info_required_count ) ); ?></span>
                    </div>

                    <!-- Main Content Card -->
                    <div class="houzez-main-card" style="border:1px solid #e6e6e6; border-radius:8px; background:#fff; overflow:hidden;">
                        <div class="houzez-card-header" style="padding:20px; border-bottom:1px solid #e6e6e6; background:#f8f9fa; display:flex; justify-content:space-between; align-items:center;">
                            <h2 style="margin:0; font-size:18px; font-weight:bold;">
                                <i class="dashicons dashicons-admin-users" style="margin-right:8px;"></i>
                                <?php esc_html_e( 'Gestão de Verificação', 'imovel-parceiro-core' ); ?>
                            </h2>
                            <div class="houzez-status-badge" style="padding:8px 12px; border-radius:20px; font-size:12px; font-weight:bold; background:<?php echo $pending_count > 0 ? '#fff3cd' : '#d1e7dd'; ?>; color:<?php echo $pending_count > 0 ? '#856404' : '#0f5132'; ?>;">
                                <?php echo $pending_count > 0 ? sprintf( esc_html__( '%d Pendentes', 'imovel-parceiro-core' ), $pending_count ) : esc_html__( 'Todos revisados', 'imovel-parceiro-core' ); ?>
                            </div>
                        </div>

                        <div class="houzez-card-body" style="padding:20px;">
                            <!-- Filter Section -->
                            <div class="tablenav top" style="margin-bottom:20px;">
                                <div class="alignleft actions">
                                    <form method="get" class="filter-form" style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <input type="hidden" name="imovel_admin_section" value="<?php echo esc_attr( $current_section ); ?>" />
                                        <input type="hidden" name="imovel_admin_action" value="list" />
                                        <div class="hz-filter-group" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                            <select name="imovel_admin_verification_status" class="filter-select" style="padding:8px; border:1px solid #e6e6e6; border-radius:4px;">
                                                <option value="" <?php selected( $verification_status_filter, '' ); ?>><?php esc_html_e( 'Todos os pedidos', 'imovel-parceiro-core' ); ?></option>
                                                <option value="pending" <?php selected( $verification_status_filter, 'pending' ); ?>><?php esc_html_e( 'Pendente', 'imovel-parceiro-core' ); ?></option>
                                                <option value="approved" <?php selected( $verification_status_filter, 'approved' ); ?>><?php esc_html_e( 'Aprovado', 'imovel-parceiro-core' ); ?></option>
                                                <option value="rejected" <?php selected( $verification_status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejeitado', 'imovel-parceiro-core' ); ?></option>
                                                <option value="additional_info_required" <?php selected( $verification_status_filter, 'additional_info_required' ); ?>><?php esc_html_e( 'Aguardando informações adicionais', 'imovel-parceiro-core' ); ?></option>
                                            </select>
                                            <button type="submit" class="button action" style="padding:8px 16px;">
                                                <span class="dashicons dashicons-filter" style="font-size:14px; width:14px; height:14px; margin-top:3px;"></span>
                                                <?php esc_html_e( 'Aplicar filtro', 'imovel-parceiro-core' ); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="tablenav-pages one-page">
                                    <span class="displaying-num">
                                        <?php echo esc_html( sprintf( _n( 'Item %d', 'Itens %d', $verification_total_items, 'imovel-parceiro-core' ), $verification_total_items ) ); ?>
                                    </span>
                                </div>
                                <br class="clear">
                            </div>

                            <!-- Verification Table -->
                            <table class="ipc-table table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Usuário', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Nome completo', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Tipo de documento', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Data de envio', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( empty( $verification_requests ) ) : ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4"><?php esc_html_e( 'Nenhuma solicitação de verificação encontrada.', 'imovel-parceiro-core' ); ?></td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $verification_requests as $request ) :
                                            $user = isset( $request['user_data'] ) ? $request['user_data'] : null;
                                            $verification_data = isset( $request['verification_data'] ) ? $request['verification_data'] : array();
                                            $status = isset( $verification_data['status'] ) ? $verification_data['status'] : '';

                                            $status_label = __( 'Desconhecido', 'imovel-parceiro-core' );
                                            $status_badge = 'secondary';
                                            switch ( $status ) {
                                                case 'pending': $status_label = __( 'Pendente', 'imovel-parceiro-core' ); $status_badge = 'warning text-dark'; break;
                                                case 'approved': $status_label = __( 'Aprovado', 'imovel-parceiro-core' ); $status_badge = 'success'; break;
                                                case 'rejected': $status_label = __( 'Rejeitado', 'imovel-parceiro-core' ); $status_badge = 'danger'; break;
                                                case 'additional_info_required': $status_label = __( 'Informação adicional necessária', 'imovel-parceiro-core' ); $status_badge = 'info text-dark'; break;
                                            }

                                            $verification_document_url = '';
                                            if ( $user ) {
                                                $doc_path = isset( $verification_data['document_path'] ) ? $verification_data['document_path'] : '';
                                                if ( ! empty( $doc_path ) ) {
                                                    $verification_document_url = add_query_arg(
                                                        array(
                                                            'action'  => 'houzez_secure_document',
                                                            'user_id' => (int) $user->ID,
                                                            'file'    => basename( $doc_path ),
                                                        ),
                                                        admin_url( 'admin-ajax.php' )
                                                    );
                                                } elseif ( isset( $verification_data['document_url'] ) && ! empty( $verification_data['document_url'] ) ) {
                                                    $verification_document_url = $verification_data['document_url'];
                                                } elseif ( isset( $verification_data['document_id'] ) && ! empty( $verification_data['document_id'] ) ) {
                                                    $verification_document_url = wp_get_attachment_url( (int) $verification_data['document_id'] );
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php echo get_avatar( $user ? $user->ID : 0, 40 ); ?>
                                                    <div>
                                                        <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . ( $user ? $user->ID : 0 ) ) ); ?>" target="_blank" style="font-weight:600; color:#7a1e2b; text-decoration:none;"><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Usuário', 'imovel-parceiro-core' ); ?></a>
                                                        <div class="text-muted" style="font-size:12px;"><?php echo $user ? esc_html( $user->user_email ) : '-'; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo isset( $verification_data['full_name'] ) ? esc_html( $verification_data['full_name'] ) : '-'; ?></td>
                                            <td>
                                                <?php if ( ! empty( $verification_document_url ) ) : ?>
                                                    <a href="<?php echo esc_url( $verification_document_url ); ?>" class="view-document-btn" data-document-url="<?php echo esc_url( $verification_document_url ); ?>" data-document-title="<?php echo esc_attr( isset( $verification_data['document_type'] ) ? $verification_data['document_type'] : '' ); ?>" style="cursor:pointer; display:inline-block; padding:4px 10px; background:#f1f5f9; border:1px solid #dbe2ea; border-radius:999px; font-size:12px; font-weight:600; color:#334155; text-decoration:none;">
                                                        <?php echo isset( $verification_data['document_type'] ) ? esc_html( $verification_data['document_type'] ) : '-'; ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="badge bg-secondary"><?php echo isset( $verification_data['document_type'] ) ? esc_html( $verification_data['document_type'] ) : '-'; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                            <td class="text-muted"><?php echo isset( $verification_data['submitted_on'] ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $verification_data['submitted_on'] ) ) ) : '-'; ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if ( 'pending' === $status ) : ?>
                                                        <button type="button" class="btn btn-success btn-sm approve-request" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm reject-request" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-outline-primary btn-sm request-info-btn" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Info', 'imovel-parceiro-core' ); ?></button>
                                                    <?php elseif ( 'approved' === $status ) : ?>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm revoke-approval" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Revogar', 'imovel-parceiro-core' ); ?></button>
                                                    <?php elseif ( 'rejected' === $status ) : ?>
                                                        <button type="button" class="btn btn-success btn-sm approve-request" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                    <?php elseif ( 'additional_info_required' === $status ) : ?>
                                                        <button type="button" class="btn btn-success btn-sm approve-request" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm reject-request" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-outline-primary btn-sm request-info-btn" data-user-id="<?php echo esc_attr( $user ? $user->ID : 0 ); ?>"><?php esc_html_e( 'Atualizar', 'imovel-parceiro-core' ); ?></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ( $verification_total_pages > 1 ) : ?>
                                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                                    <?php
                                    $pagination_base = add_query_arg(
                                        array(
                                            'imovel_admin_section' => $current_section,
                                            'imovel_admin_action' => 'list',
                                            'imovel_admin_per_page' => $per_page,
                                            'imovel_admin_paged' => '%#%',
                                            'imovel_admin_verification_status' => $verification_status_filter,
                                        ),
                                        $dashboard_url
                                    );
                                    echo paginate_links(
                                        array(
                                            'base' => $pagination_base,
                                            'format' => '',
                                            'current' => $current_page,
                                            'total' => $verification_total_pages,
                                            'prev_text' => '«',
                                            'next_text' => '»',
                                        )
                                    );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Modals for Verification Actions -->
                <!-- Rejection Modal -->
                <div id="rejection-modal" class="houzez-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                    <div class="houzez-modal-content" style="background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:90%; max-width:500px;">
                        <div class="houzez-modal-header" style="padding:20px; border-bottom:1px solid #e6e6e6; display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px; font-weight:bold;"><?php esc_html_e( 'Rejeitar solicitação de verificação', 'imovel-parceiro-core' ); ?></h3>
                            <span class="close" style="cursor:pointer; font-size:24px; font-weight:bold; color:#999;">&times;</span>
                        </div>
                        <div class="houzez-modal-body" style="padding:20px;">
                            <form id="rejection-form">
                                <input type="hidden" id="rejection-user-id" name="user_id" value="">
                                <input type="hidden" name="action_type" value="reject">
                                <input type="hidden" name="action" value="houzez_process_verification">
                                <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'houzez_admin_verification_nonce' ) ); ?>">
                                
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label for="rejection-reason" style="display:block; font-weight:bold; margin-bottom:8px;"><?php esc_html_e( 'Motivo da rejeição:', 'imovel-parceiro-core' ); ?></label>
                                    <textarea id="rejection-reason" name="rejection_reason" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Por favor, forneça um motivo para rejeitar esta solicitação de verificação...', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:8px; border:1px solid #e6e6e6; border-radius:4px; font-family:monospace;"></textarea>
                                    <p class="description" style="margin:8px 0 0 0; font-size:12px; color:#666;"><?php esc_html_e( 'Esse motivo será incluído no e-mail de rejeição enviado ao usuário.', 'imovel-parceiro-core' ); ?></p>
                                </div>
                            </form>
                        </div>
                        <div class="houzez-modal-footer" style="padding:20px; border-top:1px solid #e6e6e6; display:flex; justify-content:flex-end; gap:8px;">
                            <button type="button" class="button button-secondary cancel-rejection" style="padding:8px 16px;"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                            <button type="submit" form="rejection-form" class="button button-primary" style="padding:8px 16px;"><?php esc_html_e( 'Confirmar rejeição', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Additional Info Modal -->
                <div id="additional-info-modal" class="houzez-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                    <div class="houzez-modal-content" style="background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:90%; max-width:500px;">
                        <div class="houzez-modal-header" style="padding:20px; border-bottom:1px solid #e6e6e6; display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px; font-weight:bold;"><?php esc_html_e( 'Solicitar informações adicionais', 'imovel-parceiro-core' ); ?></h3>
                            <span class="close" style="cursor:pointer; font-size:24px; font-weight:bold; color:#999;">&times;</span>
                        </div>
                        <div class="houzez-modal-body" style="padding:20px;">
                            <form id="additional-info-form">
                                <input type="hidden" id="additional-info-user-id" name="user_id" value="">
                                <input type="hidden" name="action_type" value="request_info">
                                <input type="hidden" name="action" value="houzez_process_verification">
                                <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'houzez_admin_verification_nonce' ) ); ?>">
                                
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label for="additional-info" style="display:block; font-weight:bold; margin-bottom:8px;"><?php esc_html_e( 'Especifique as informações adicionais necessárias:', 'imovel-parceiro-core' ); ?></label>
                                    <textarea id="additional-info" name="additional_info" rows="4" class="widefat" required placeholder="<?php esc_attr_e( 'Por favor, especifique quais informações adicionais são necessárias do usuário...', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:8px; border:1px solid #e6e6e6; border-radius:4px; font-family:monospace;"></textarea>
                                    <p class="description" style="margin:8px 0 0 0; font-size:12px; color:#666;"><?php esc_html_e( 'Deixe claro qual documentação ou informação adicional é necessária do usuário.', 'imovel-parceiro-core' ); ?></p>
                                </div>
                            </form>
                        </div>
                        <div class="houzez-modal-footer" style="padding:20px; border-top:1px solid #e6e6e6; display:flex; justify-content:flex-end; gap:8px;">
                            <button type="button" class="button button-secondary cancel-additional-info" style="padding:8px 16px;"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                            <button type="submit" form="additional-info-form" class="button button-primary" style="padding:8px 16px;"><?php esc_html_e( 'Enviar solicitação', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Revoke Approval Modal -->
                <div id="revoke-modal" class="houzez-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                    <div class="houzez-modal-content" style="background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:90%; max-width:500px;">
                        <div class="houzez-modal-header" style="padding:20px; border-bottom:1px solid #e6e6e6; display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px; font-weight:bold;"><?php esc_html_e( 'Revogar verificação', 'imovel-parceiro-core' ); ?></h3>
                            <span class="close" style="cursor:pointer; font-size:24px; font-weight:bold; color:#999;">&times;</span>
                        </div>
                        <div class="houzez-modal-body" style="padding:20px;">
                            <form id="revoke-form">
                                <input type="hidden" id="revoke-user-id" name="user_id" value="">
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label for="revoke-reason" style="display:block; font-weight:bold; margin-bottom:8px;"><?php esc_html_e( 'Motivo da revogação:', 'imovel-parceiro-core' ); ?></label>
                                    <textarea id="revoke-reason" name="revoke_reason" rows="4" class="widefat" required placeholder="<?php esc_attr_e( 'Informe o motivo pelo qual a verificação está sendo revogada...', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:8px; border:1px solid #e6e6e6; border-radius:4px; font-family:monospace;"></textarea>
                                    <p class="description" style="margin:8px 0 0 0; font-size:12px; color:#666;"><?php esc_html_e( 'Esse motivo será incluído no e-mail enviado ao usuário.', 'imovel-parceiro-core' ); ?></p>
                                </div>
                            </form>
                        </div>
                        <div class="houzez-modal-footer" style="padding:20px; border-top:1px solid #e6e6e6; display:flex; justify-content:flex-end; gap:8px;">
                            <button type="button" class="button button-secondary cancel-revoke" style="padding:8px 16px;"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                            <button type="submit" form="revoke-form" class="button button-primary" style="padding:8px 16px;"><?php esc_html_e( 'Confirmar revogação', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Document Viewer Modal -->
                <div id="document-viewer-modal" class="houzez-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
                    <div class="houzez-modal-content" style="background:#fff; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,0.3); width:90%; height:90vh; max-width:1000px; display:flex; flex-direction:column;">
                        <div class="houzez-modal-header" style="padding:20px; border-bottom:1px solid #e6e6e6; display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px; font-weight:bold;" id="document-title"><?php esc_html_e( 'Visualizar Documento', 'imovel-parceiro-core' ); ?></h3>
                            <span class="close" style="cursor:pointer; font-size:24px; font-weight:bold; color:#999;">&times;</span>
                        </div>
                        <div class="houzez-modal-body" style="padding:20px; flex:1; overflow:auto;">
                            <div id="document-container" style="text-align:center; min-height:300px; display:flex; align-items:center; justify-content:center;">
                                <p><?php esc_html_e( 'Carregando documento...', 'imovel-parceiro-core' ); ?></p>
                            </div>
                        </div>
                        <div class="houzez-modal-footer" style="padding:20px; border-top:1px solid #e6e6e6; display:flex; justify-content:flex-end; gap:8px;">
                            <a id="document-download-link" href="#" class="button button-primary" target="_blank" download style="padding:8px 16px; text-decoration:none; display:inline-block;">
                                <span class="dashicons dashicons-download" style="vertical-align:middle; margin-right:4px;"></span>
                                <?php esc_html_e( 'Baixar', 'imovel-parceiro-core' ); ?>
                            </a>
                            <button type="button" class="button button-secondary close-document-viewer" style="padding:8px 16px;"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <script>
                // Modal functionality
                const rejectionModal = document.getElementById('rejection-modal');
                const additionalInfoModal = document.getElementById('additional-info-modal');
                const revokeModal = document.getElementById('revoke-modal');
                const documentViewerModal = document.getElementById('document-viewer-modal');
                const rejectionForm = document.getElementById('rejection-form');
                const additionalInfoForm = document.getElementById('additional-info-form');
                const revokeForm = document.getElementById('revoke-form');

                // Open modals
                document.querySelectorAll('.reject-request').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.getElementById('rejection-user-id').value = this.dataset.userId;
                        rejectionModal.style.display = 'flex';
                    });
                });

                document.querySelectorAll('.request-info-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.getElementById('additional-info-user-id').value = this.dataset.userId;
                        additionalInfoModal.style.display = 'flex';
                    });
                });

                // Close modals
                document.querySelectorAll('.close').forEach(btn => {
                    btn.addEventListener('click', function() {
                        this.closest('.houzez-modal').style.display = 'none';
                    });
                });

                document.querySelectorAll('.cancel-rejection, .cancel-additional-info, .cancel-revoke').forEach(btn => {
                    btn.addEventListener('click', function() {
                        this.closest('.houzez-modal').style.display = 'none';
                    });
                });

                // Submit forms via AJAX
                rejectionForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitVerificationAction(this);
                });

                additionalInfoForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitVerificationAction(this);
                });

                function submitVerificationAction(form) {
                    const formData = new FormData(form);
                    const userId = formData.get('user_id');
                    const actionType = formData.get('action_type');
                    const notes = actionType === 'reject' ? formData.get('rejection_reason') : formData.get('additional_info');

                    // Create a hidden form and submit via POST
                    const hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.style.display = 'none';

                    const nonceField = document.createElement('input');
                    nonceField.type = 'hidden';
                    nonceField.name = '_imovel_admin_nonce';
                    nonceField.value = window.imovelAdminNonce || '';

                    const sectionField = document.createElement('input');
                    sectionField.type = 'hidden';
                    sectionField.name = 'imovel_admin_section';
                    sectionField.value = 'verification_requests';

                    const idField = document.createElement('input');
                    idField.type = 'hidden';
                    idField.name = 'imovel_admin_id';
                    idField.value = userId;

                    const actionField = document.createElement('input');
                    actionField.type = 'hidden';
                    actionField.name = 'imovel_admin_verification_action';
                    actionField.value = actionType === 'reject' ? 'reject' : 'additional_info';

                    const notesField = document.createElement('input');
                    notesField.type = 'hidden';
                    notesField.name = 'imovel_admin_notes';
                    notesField.value = notes;

                    hiddenForm.appendChild(nonceField);
                    hiddenForm.appendChild(sectionField);
                    hiddenForm.appendChild(idField);
                    hiddenForm.appendChild(actionField);
                    hiddenForm.appendChild(notesField);

                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                }

                // Approve action (direct submission)
                document.querySelectorAll('.approve-request').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.dataset.userId;
                        
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const nonceField = document.createElement('input');
                        nonceField.type = 'hidden';
                        nonceField.name = '_imovel_admin_nonce';
                        nonceField.value = window.imovelAdminNonce || '';

                        const sectionField = document.createElement('input');
                        sectionField.type = 'hidden';
                        sectionField.name = 'imovel_admin_section';
                        sectionField.value = 'verification_requests';

                        const idField = document.createElement('input');
                        idField.type = 'hidden';
                        idField.name = 'imovel_admin_id';
                        idField.value = userId;

                        const actionField = document.createElement('input');
                        actionField.type = 'hidden';
                        actionField.name = 'imovel_admin_verification_action';
                        actionField.value = 'approve';

                        form.appendChild(nonceField);
                        form.appendChild(sectionField);
                        form.appendChild(idField);
                        form.appendChild(actionField);

                        document.body.appendChild(form);
                        form.submit();
                    });
                });

                // Revoke action (opens justification modal)
                document.querySelectorAll('.revoke-approval').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.getElementById('revoke-user-id').value = this.dataset.userId;
                        revokeModal.style.display = 'flex';
                    });
                });

                revokeForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const userId = document.getElementById('revoke-user-id').value;
                    const reason = document.getElementById('revoke-reason').value.trim();
                    if (!reason) {
                        document.getElementById('revoke-reason').focus();
                        return;
                    }

                    const hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.style.display = 'none';

                    const fields = {
                        _imovel_admin_nonce: window.imovelAdminNonce || '',
                        imovel_admin_section: 'verification_requests',
                        imovel_admin_id: userId,
                        imovel_admin_verification_action: 'revoke',
                        imovel_admin_notes: reason
                    };
                    Object.keys(fields).forEach(function(name) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = fields[name];
                        hiddenForm.appendChild(input);
                    });

                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                });

                // Document Viewer Functionality
                document.querySelectorAll('.view-document-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const docUrl = this.dataset.documentUrl || '';
                        const docLabel = this.dataset.documentTitle || '';
                        if (!docUrl) {
                            alert('<?php esc_attr_e( 'Documento indisponível.', 'imovel-parceiro-core' ); ?>');
                            return;
                        }

                        const container = document.getElementById('document-container');
                        const title = document.getElementById('document-title');
                        const downloadLink = document.getElementById('document-download-link');

                        title.textContent = docLabel ? docLabel : '<?php esc_html_e( 'Visualizar Documento', 'imovel-parceiro-core' ); ?>';
                        downloadLink.href = docUrl;

                        const fileExt = (docUrl.split('.').pop() || '').toLowerCase();
                        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                            // Image file
                            container.innerHTML = '<img src="' + docUrl + '" style="max-width:100%; max-height:100%; object-fit:contain;">';
                        } else if (fileExt === 'pdf') {
                            // PDF file
                            container.innerHTML = '<iframe src="' + docUrl + '" style="width:100%; height:70vh; border:none;"></iframe>';
                        } else {
                            // Other formats - show in iframe with download option
                            container.innerHTML = '<iframe src="' + docUrl + '" style="width:100%; height:70vh; border:none;"></iframe>';
                        }

                        // Show modal
                        documentViewerModal.style.display = 'flex';
                    });
                });

                // Close document viewer modal
                document.querySelectorAll('.close-document-viewer').forEach(btn => {
                    btn.addEventListener('click', function() {
                        documentViewerModal.style.display = 'none';
                    });
                });

                // Close modals when clicking outside
                document.querySelectorAll('.houzez-modal').forEach(modal => {
                    modal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                        }
                    });
                });
                </script>

            <?php else : ?>
                <?php if ( 'agents' === $current_section ) : ?>
                    <?php
                    $user_search = isset( $_GET['imovel_user_search'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_user_search'] ) ) : '';
                    $users_query_args = array(
                        'number'  => $per_page,
                        'paged'   => $current_page,
                        'orderby' => 'user_registered',
                        'order'   => 'DESC',
                    );
                    if ( '' !== $user_search ) {
                        $users_query_args['search'] = '*' . $user_search . '*';
                        $users_query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
                    }
                    $wp_users_query = new WP_User_Query( $users_query_args );
                    $wp_users = $wp_users_query->get_results();
                    $users_total = (int) $wp_users_query->get_total();
                    $users_total_pages = max( 1, (int) ceil( $users_total / max( 1, $per_page ) ) );
                    $role_labels = array(
                        'houzez_agent' => __( 'Corretor', 'imovel-parceiro-core' ),
                        'houzez_owner' => __( 'Proprietário', 'imovel-parceiro-core' ),
                        'houzez_seller' => __( 'Vendedor', 'imovel-parceiro-core' ),
                        'subscriber' => __( 'Cliente', 'imovel-parceiro-core' ),
                        'administrator' => __( 'Administrador', 'imovel-parceiro-core' ),
                        'editor' => __( 'Editor', 'imovel-parceiro-core' ),
                    );
                    ?>
                    <form method="get" style="margin-bottom:14px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <input type="hidden" name="imovel_admin_section" value="agents" />
                        <input type="hidden" name="imovel_admin_action" value="list" />
                        <input type="text" name="imovel_user_search" value="<?php echo esc_attr( $user_search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nome, login ou e-mail', 'imovel-parceiro-core' ); ?>" style="padding:8px; border:1px solid #d9d9d9; border-radius:6px; flex:1 1 200px; min-width:0;" />
                        <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Buscar', 'imovel-parceiro-core' ); ?></button>
                        <label for="imovel_user_per_page" style="font-weight:600; margin:0 0 0 8px;"><?php esc_html_e( 'Itens por página', 'imovel-parceiro-core' ); ?></label>
                        <select id="imovel_user_per_page" name="imovel_admin_per_page" style="padding:8px;">
                            <?php foreach ( array( 10, 25, 50, 100 ) as $page_size ) : ?>
                                <option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( $per_page, $page_size ); ?>><?php echo esc_html( $page_size ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Aplicar', 'imovel-parceiro-core' ); ?></button>
                    </form>

                    <div style="overflow-x:auto;">
                        <table class="ipc-table table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Usuário', 'imovel-parceiro-core' ); ?></th>
                                    <th><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></th>
                                    <th><?php esc_html_e( 'Tipo de conta', 'imovel-parceiro-core' ); ?></th>
                                    <th><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $wp_users ) ) : ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4"><?php esc_html_e( 'Nenhum usuário encontrado.', 'imovel-parceiro-core' ); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ( $wp_users as $user_item ) :
                                        $role_label = __( 'Sem papel', 'imovel-parceiro-core' );
                                        $role_key = '';
                                        foreach ( (array) $user_item->roles as $rk ) {
                                            if ( isset( $role_labels[ $rk ] ) ) {
                                                $role_label = $role_labels[ $rk ];
                                                $role_key = $rk;
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php echo get_avatar( $user_item->ID, 40 ); ?>
                                                <div>
                                                    <strong style="font-weight:600; color:#1f2937;"><?php echo esc_html( $user_item->display_name ); ?></strong>
                                                    <div class="text-muted" style="font-size:12px;">@<?php echo esc_html( $user_item->user_login ); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html( $user_item->user_email ); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo esc_html( $role_label ); ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-sm imovel-user-edit-btn"
                                                data-user-id="<?php echo esc_attr( $user_item->ID ); ?>"
                                                data-display-name="<?php echo esc_attr( $user_item->display_name ); ?>"
                                                data-first-name="<?php echo esc_attr( get_user_meta( $user_item->ID, 'first_name', true ) ); ?>"
                                                data-user-email="<?php echo esc_attr( $user_item->user_email ); ?>"
                                                data-user-login="<?php echo esc_attr( $user_item->user_login ); ?>"
                                                data-role="<?php echo esc_attr( $role_key ); ?>">
                                                <?php esc_html_e( 'Editar', 'imovel-parceiro-core' ); ?>
                                            </button>
                                            <?php
                                            $ipc_blocked = class_exists( 'Imovel_Parceiro_User_Blocking' ) && Imovel_Parceiro_User_Blocking::is_blocked( $user_item->ID );
                                            if ( $ipc_blocked ) :
                                            ?>
                                                <span class="badge bg-danger" style="margin-left:6px;"><?php esc_html_e( 'Bloqueado', 'imovel-parceiro-core' ); ?></span>
                                            <?php endif; ?>
                                            <form method="post" class="imovel-user-block-form" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="imovel_admin_section" value="agents" />
                                                <input type="hidden" name="imovel_admin_id" value="<?php echo esc_attr( $user_item->ID ); ?>" />
                                                <input type="hidden" name="imovel_admin_block_action" value="<?php echo $ipc_blocked ? 'unblock' : 'block'; ?>" />
                                                <input type="hidden" name="imovel_admin_block_reason" value="" />
                                                <?php wp_nonce_field( 'imovel_admin_update', '_imovel_admin_nonce' ); ?>
                                                <button type="button" class="btn btn-sm <?php echo $ipc_blocked ? 'btn-outline-success' : 'btn-outline-danger'; ?> imovel-user-block-btn"
                                                    data-blocked="<?php echo $ipc_blocked ? '1' : '0'; ?>"
                                                    data-name="<?php echo esc_attr( $user_item->display_name ); ?>">
                                                    <?php echo $ipc_blocked ? esc_html__( 'Desbloquear', 'imovel-parceiro-core' ) : esc_html__( 'Bloquear', 'imovel-parceiro-core' ); ?>
                                                </button>
                                            </form>
                                            <?php if ( ! user_can( $user_item->ID, 'manage_options' ) ) : ?>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => 'agents', 'imovel_admin_action' => 'delete', 'imovel_admin_id' => $user_item->ID, '_wpnonce' => wp_create_nonce( 'imovel_admin_delete_user_' . $user_item->ID ) ), $dashboard_url ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.', 'imovel-parceiro-core' ) ); ?>');" style="margin-left:8px; color:#dc2626;">
                                                    <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?> <?php esc_html_e( 'Excluir', 'imovel-parceiro-core' ); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( $users_total_pages > 1 ) : ?>
                        <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                            <?php
                            $users_pagination_base = add_query_arg(
                                array(
                                    'imovel_admin_section' => 'agents',
                                    'imovel_admin_action' => 'list',
                                    'imovel_admin_per_page' => $per_page,
                                    'imovel_user_search' => $user_search,
                                    'imovel_admin_paged' => '%#%',
                                ),
                                $dashboard_url
                            );
                            echo paginate_links(
                                array(
                                    'base' => $users_pagination_base,
                                    'format' => '',
                                    'current' => $current_page,
                                    'total' => $users_total_pages,
                                    'prev_text' => '«',
                                    'next_text' => '»',
                                )
                            );
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bloquear corretor modal -->
                    <div id="imovel-user-block-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(2,6,23,.55); -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
                        <div style="background:#fff; border-radius:18px; border:1px solid #f1f5f9; box-shadow:0 24px 60px -12px rgba(2,6,23,.35); width:100%; max-width:520px; max-height:calc(100vh - 32px); overflow:auto;">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 22px; border-bottom:1px solid #f1f5f9; background:#f8fafc; position:sticky; top:0; z-index:2;">
                                <h5 style="margin:0; display:flex; align-items:center; gap:10px; font-size:16px; font-weight:700; color:#0f172a;">
                                    <span style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:10px; background:#fee2e2; color:#dc2626;"><?php echo houzez_dash_icon( 'triangle-alert', 'h-4 w-4' ); ?></span>
                                    <?php esc_html_e( 'Bloquear corretor', 'imovel-parceiro-core' ); ?>
                                </h5>
                                <button type="button" class="imovel-block-modal-close" style="cursor:pointer; width:32px; height:32px; border-radius:10px; font-size:20px; font-weight:700; color:#94a3b8; background:#f1f5f9; border:none; display:flex; align-items:center; justify-content:center;">&times;</button>
                            </div>
                            <div style="padding:22px;">
                                <p id="imovel-block-target" style="color:#334155; margin:0 0 14px;"></p>
                                <label for="imovel-block-reason" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Motivo do bloqueio *', 'imovel-parceiro-core' ); ?></label>
                                <textarea id="imovel-block-reason" class="form-control" rows="4" required></textarea>
                                <div class="mt-4 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary imovel-block-modal-close" style="border-radius:10px;"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                                    <button type="button" id="imovel-block-confirm" class="btn btn-danger" style="border-radius:10px;"><?php esc_html_e( 'Bloquear', 'imovel-parceiro-core' ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function(){
                        var blockModal = document.getElementById('imovel-user-block-modal');
                        if (!blockModal) return;
                        var reasonField = document.getElementById('imovel-block-reason');
                        var targetLabel = document.getElementById('imovel-block-target');
                        var confirmBtn = document.getElementById('imovel-block-confirm');
                        var activeForm = null;

                        function openBlock(form, name){
                            activeForm = form;
                            if (targetLabel) { targetLabel.textContent = 'Bloquear ' + name + '?'; }
                            if (reasonField) { reasonField.value = ''; }
                            blockModal.style.display = 'flex';
                            blockModal.setAttribute('aria-hidden', 'false');
                            document.body.classList.add('modal-open');
                        }
                        function closeBlock(){
                            blockModal.style.display = 'none';
                            blockModal.setAttribute('aria-hidden', 'true');
                            document.body.classList.remove('modal-open');
                        }

                        document.querySelectorAll('.imovel-user-block-btn').forEach(function(btn){
                            btn.addEventListener('click', function(){
                                var form = btn.closest('form');
                                if (!form) return;
                                if (btn.getAttribute('data-blocked') === '1') {
                                    if (window.confirm('Desbloquear ' + btn.getAttribute('data-name') + '?')) { form.submit(); }
                                    return;
                                }
                                openBlock(form, btn.getAttribute('data-name'));
                            });
                        });

                        blockModal.querySelectorAll('.imovel-block-modal-close').forEach(function(b){ b.addEventListener('click', closeBlock); });
                        blockModal.addEventListener('click', function(e){ if (e.target === blockModal) closeBlock(); });

                        if (confirmBtn) {
                            confirmBtn.addEventListener('click', function(){
                                var reason = (reasonField && reasonField.value ? reasonField.value : '').trim();
                                if (reason === '') { if (reasonField) reasonField.focus(); return; }
                                if (!activeForm) return;
                                var hidden = activeForm.querySelector('input[name="imovel_admin_block_reason"]');
                                if (hidden) { hidden.value = reason; }
                                activeForm.submit();
                            });
                        }
                    })();
                    </script>

                    <!-- Usuário modal (criar / editar) -->
                    <div id="imovel-user-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(2,6,23,.55); -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
                        <div style="background:#fff; border-radius:18px; border:1px solid #f1f5f9; box-shadow:0 24px 60px -12px rgba(2,6,23,.35); width:100%; max-width:540px; max-height:calc(100vh - 32px); overflow:auto;">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 22px; border-bottom:1px solid #f1f5f9; background:#f8fafc; position:sticky; top:0; z-index:2;">
                                <h5 id="imovel-user-modal-title" style="margin:0; display:flex; align-items:center; gap:10px; font-size:16px; font-weight:700; color:#0f172a;">
                                    <span style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:10px; background:#eef2ff; color:#6366f1;"><?php echo houzez_dash_icon( 'user-plus', 'h-4 w-4' ); ?></span>
                                    <?php esc_html_e( 'Novo usuário', 'imovel-parceiro-core' ); ?>
                                </h5>
                                <button type="button" class="imovel-user-modal-close" style="cursor:pointer; width:32px; height:32px; border-radius:10px; font-size:20px; font-weight:700; color:#94a3b8; background:#f1f5f9; border:none; display:flex; align-items:center; justify-content:center; transition:all .15s ease;">&times;</button>
                            </div>
                            <div style="padding:22px;">
                                <div id="imovel-user-feedback"></div>
                                <form id="imovel-user-form">
                                    <input type="hidden" name="user_id" value="0" />
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="imovel-user-first-name" class="form-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Primeiro nome *', 'imovel-parceiro-core' ); ?></label>
                                            <input type="text" name="first_name" id="imovel-user-first-name" class="form-control" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label for="imovel-user-login" class="form-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Nome de usuário *', 'imovel-parceiro-core' ); ?></label>
                                            <input type="text" name="user_login" id="imovel-user-login" class="form-control" required />
                                        </div>
                                        <div class="col-md-12">
                                            <label for="imovel-user-email" class="form-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'E-mail *', 'imovel-parceiro-core' ); ?></label>
                                            <input type="email" name="user_email" id="imovel-user-email" class="form-control" required />
                                        </div>
                                        <div class="col-md-12" id="imovel-user-phone-wrap">
                                            <label for="imovel-user-phone" class="form-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Telefone', 'imovel-parceiro-core' ); ?></label>
                                            <input type="text" name="phone" id="imovel-user-phone" class="form-control" />
                                        </div>
                                        <div class="col-md-6">
                                            <label for="imovel-user-password" class="form-label" id="imovel-user-pass-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Senha *', 'imovel-parceiro-core' ); ?></label>
                                            <input type="password" name="password" id="imovel-user-password" class="form-control" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label for="imovel-user-password-confirm" class="form-label" id="imovel-user-pass2-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Repita a senha *', 'imovel-parceiro-core' ); ?></label>
                                            <input type="password" name="password_confirm" id="imovel-user-password-confirm" class="form-control" required />
                                        </div>
                                        <div class="col-md-12">
                                            <label for="imovel-user-role" class="form-label" style="display:block; margin-bottom:6px; font-weight:600; color:#334155; font-size:13px;"><?php esc_html_e( 'Tipo de conta *', 'imovel-parceiro-core' ); ?></label>
                                            <select name="account_type" id="imovel-user-role" class="form-select" required>
                                                <option value=""><?php esc_html_e( 'Selecione o tipo de conta.', 'imovel-parceiro-core' ); ?></option>
                                                <option value="corretor"><?php esc_html_e( 'Corretor', 'imovel-parceiro-core' ); ?></option>
                                                <option value="proprietario"><?php esc_html_e( 'Proprietário', 'imovel-parceiro-core' ); ?></option>
                                                <option value="cliente"><?php esc_html_e( 'Cliente', 'imovel-parceiro-core' ); ?></option>
                                                <option value="vendedor"><?php esc_html_e( 'Vendedor', 'imovel-parceiro-core' ); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-outline-secondary imovel-user-modal-close" style="border-radius:10px;"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                                        <button type="submit" id="imovel-user-submit" class="btn btn-primary" style="border-radius:10px; display:inline-flex; align-items:center; gap:8px;">
                                            <?php echo houzez_dash_icon( 'check', 'h-4 w-4' ); ?>
                                            <?php esc_html_e( 'Salvar', 'imovel-parceiro-core' ); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function(){
                        var modal = document.getElementById('imovel-user-modal');
                        var form = document.getElementById('imovel-user-form');
                        var btnNew = document.getElementById('imovel-user-new-btn');
                        if (!modal || !form) return;

                        var roleMap = { 'houzez_agent':'corretor', 'houzez_owner':'proprietario', 'subscriber':'cliente', 'houzez_seller':'vendedor' };
                        var createNonce = '<?php echo esc_js( wp_create_nonce( 'imovel_admin_create_user' ) ); ?>';
                        var updateNonce = '<?php echo esc_js( wp_create_nonce( 'imovel_admin_update_user' ) ); ?>';

                        function open(){ modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open'); }
                        function close(){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open'); }
                        function resetForm(){
                            form.reset();
                            form.querySelector('[name="user_id"]').value = '0';
                            form.querySelector('[name="user_login"]').readOnly = false;
                            form.querySelector('[name="password"]').required = true;
                            form.querySelector('[name="password_confirm"]').required = true;
                            document.getElementById('imovel-user-pass-label').innerHTML = 'Senha *';
                            document.getElementById('imovel-user-pass2-label').innerHTML = 'Repita a senha *';
                            document.getElementById('imovel-user-feedback').innerHTML = '';
                            if (document.getElementById('imovel-user-phone-wrap')) { document.getElementById('imovel-user-phone-wrap').style.display = ''; }
                        }
                        function setMsg(html){ document.getElementById('imovel-user-feedback').innerHTML = html; }

                        if (btnNew) {
                            btnNew.addEventListener('click', function(){
                                resetForm();
                                document.getElementById('imovel-user-modal-title').textContent = 'Novo usuário';
                                document.getElementById('imovel-user-submit').innerHTML = 'Salvar';
                                open();
                            });
                        }

                        document.querySelectorAll('.imovel-user-edit-btn').forEach(function(ebtn){
                            ebtn.addEventListener('click', function(){
                                resetForm();
                                var d = this.dataset;
                                document.getElementById('imovel-user-modal-title').textContent = 'Editar usuário';
                                document.getElementById('imovel-user-submit').innerHTML = 'Salvar';
                                form.querySelector('[name="user_id"]').value = d.userId || '0';
                                if (document.getElementById('imovel-user-phone-wrap')) { document.getElementById('imovel-user-phone-wrap').style.display = 'none'; }
                                document.getElementById('imovel-user-first-name').value = (d.firstName || d.displayName || '');
                                document.getElementById('imovel-user-login').value = d.userLogin || '';
                                document.getElementById('imovel-user-login').readOnly = true;
                                document.getElementById('imovel-user-email').value = d.userEmail || '';
                                document.getElementById('imovel-user-phone').value = '';
                                document.getElementById('imovel-user-password').required = false;
                                document.getElementById('imovel-user-password-confirm').required = false;
                                document.getElementById('imovel-user-pass-label').innerHTML = 'Nova senha (opcional)';
                                document.getElementById('imovel-user-pass2-label').innerHTML = 'Repita a nova senha';
                                var rk = d.role || '';
                                var av = roleMap[rk] || '';
                                document.getElementById('imovel-user-role').value = av;
                                open();
                            });
                        });

                        modal.querySelectorAll('.imovel-user-modal-close').forEach(function(c){ c.addEventListener('click', close); });
                        modal.addEventListener('click', function(e){ if (e.target === modal) close(); });

                        form.addEventListener('submit', function(e){
                            e.preventDefault();
                            var userId = form.querySelector('[name="user_id"]').value;
                            var isEdit = userId && userId !== '0';
                            var data = new FormData(form);
                            data.append('action', isEdit ? 'imovel_parceiro_admin_update_user' : 'imovel_parceiro_admin_create_user');
                            data.append('nonce', isEdit ? updateNonce : createNonce);

                            var pass = data.get('password') || '';
                            var pass2 = data.get('password_confirm') || '';
                            if (pass !== pass2) {
                                setMsg('<div style="color:#842029; background:#f8d7da; border-radius:6px; padding:10px 12px; margin-bottom:12px;"><?php esc_js( __( 'As senhas não coincidem.', 'imovel-parceiro-core' ) ); ?></div>');
                                return;
                            }
                            if (!isEdit && (!pass || !pass || !data.get('first_name') || !data.get('user_email') || !data.get('account_type'))) {
                                setMsg('<div style="color:#842029; background:#f8d7da; border-radius:6px; padding:10px 12px; margin-bottom:12px;"><?php esc_js( __( 'Preencha todos os campos obrigatórios.', 'imovel-parceiro-core' ) ); ?></div>');
                                return;
                            }
                            if (isEdit && !pass) {
                                data.delete('password');
                                data.delete('password_confirm');
                            }

                            fetch('<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', { method:'POST', body:data, credentials:'same-origin' })
                            .then(function(r){ return r.json(); })
                            .then(function(res){
                                if (res && res.success) {
                                    setMsg('<div style="color:#0f5132; background:#d1e7dd; border-radius:6px; padding:10px 12px; margin-bottom:12px;">' + ((res.data && res.data.message) ? res.data.message : '<?php esc_js( __( 'Usuário salvo.', 'imovel-parceiro-core' ) ); ?>') + '</div>');
                                    setTimeout(function(){ window.location.reload(); }, 800);
                                } else {
                                    setMsg('<div style="color:#842029; background:#f8d7da; border-radius:6px; padding:10px 12px; margin-bottom:12px;">' + ((res && res.data && res.data.message) ? res.data.message : '<?php esc_js( __( 'Erro ao salvar usuário.', 'imovel-parceiro-core' ) ); ?>') + '</div>');
                                }
                            })
                            .catch(function(){
                                setMsg('<div style="color:#842029; background:#f8d7da; border-radius:6px; padding:10px 12px; margin-bottom:12px;"><?php esc_js( __( 'Falha de comunicação.', 'imovel-parceiro-core' ) ); ?></div>');
                            });
                        });
                    })();
                    </script>
                <?php else : ?>
                <!-- Other Sections Layout (Agents, Agencies, etc.) -->
                <?php if ( isset( $items_query ) && $items_query->have_posts() ) : ?>
                    <form method="get" style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <input type="hidden" name="imovel_admin_section" value="<?php echo esc_attr( $current_section ); ?>" />
                        <input type="hidden" name="imovel_admin_action" value="list" />
                        <label for="imovel_admin_per_page" style="font-weight:600; margin:0;"><?php esc_html_e( 'Itens por página', 'imovel-parceiro-core' ); ?></label>
                        <select id="imovel_admin_per_page" name="imovel_admin_per_page" style="padding:8px;">
                            <option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
                            <option value="25" <?php selected( $per_page, 25 ); ?>>25</option>
                            <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                            <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                        </select>
                        <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Aplicar', 'imovel-parceiro-core' ); ?></button>
                    </form>

                    <div style="overflow-x:auto;">
                        <table class="ipc-table" style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php echo esc_html( 'coupons' === $current_section ? __( 'Código', 'imovel-parceiro-core' ) : __( 'Título', 'imovel-parceiro-core' ) ); ?></th>
                                    <?php if ( 'coupons' === $current_section ) : ?>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Tipo', 'imovel-parceiro-core' ); ?></th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Valor', 'imovel-parceiro-core' ); ?></th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Expira', 'imovel-parceiro-core' ); ?></th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Limite', 'imovel-parceiro-core' ); ?></th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Usado', 'imovel-parceiro-core' ); ?></th>
                                    <?php endif; ?>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e6e6e6;"><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $items as $item ) : ?>
                                    <?php
                                    $ipc_c_type = '';
                                    $ipc_c_amount = '';
                                    $ipc_c_expires = '';
                                    $ipc_c_limit = '';
                                    $ipc_c_used = '';
                                    if ( 'coupons' === $current_section ) {
                                        $ipc_c_type = get_post_meta( $item->ID, 'discount_type', true );
                                        $ipc_c_amount = get_post_meta( $item->ID, 'coupon_amount', true );
                                        $ipc_c_expires = get_post_meta( $item->ID, 'date_expires', true );
                                        $ipc_c_limit = get_post_meta( $item->ID, 'usage_limit', true );
                                        $ipc_c_used = get_post_meta( $item->ID, 'usage_count', true );
                                    }
                                    ?>
                                    <tr>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo esc_html( $item->post_title ); ?></td>
                                        <?php if ( 'coupons' === $current_section ) : ?>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo 'recurring_percent' === $ipc_c_type ? esc_html__( 'Recorrente %', 'imovel-parceiro-core' ) : esc_html__( 'Porcentagem', 'imovel-parceiro-core' ); ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo esc_html( $ipc_c_amount ); ?>%</td>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo $ipc_c_expires ? esc_html( date_i18n( 'd/m/Y', (int) $ipc_c_expires ) ) : '—'; ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo $ipc_c_limit ? esc_html( $ipc_c_limit ) : '—'; ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo esc_html( (string) $ipc_c_used ); ?></td>
                                        <?php endif; ?>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;"><?php echo esc_html( $item->post_status ); ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f1f1f1;">
                                            <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'edit', 'imovel_admin_id' => $item->ID ), $dashboard_url ) ); ?>" style="margin-right:8px;">
                                                <?php esc_html_e( 'Editar', 'imovel-parceiro-core' ); ?>
                                            </a>
                                            <?php if ( in_array( $current_section, $ipc_deletable_sections, true ) ) : ?>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'delete', 'imovel_admin_id' => $item->ID, '_wpnonce' => wp_create_nonce( 'imovel_admin_delete_' . $item->ID ) ), $dashboard_url ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir este item?', 'imovel-parceiro-core' ) ); ?>');" style="margin-right:8px; color:#dc2626;">
                                                    <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?> <?php esc_html_e( 'Excluir', 'imovel-parceiro-core' ); ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ( 'approval' === $current_section && 'pending' === $item->post_status ) : ?>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_section' => $current_section, 'imovel_admin_action' => 'approve', 'imovel_admin_id' => $item->ID ), $dashboard_url ) ); ?>">
                                                    <?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( $total_pages > 1 ) : ?>
                        <div style="margin-top:12px; display:flex; justify-content:flex-end;">
                            <?php
                            $pagination_base = add_query_arg(
                                array(
                                    'imovel_admin_section' => $current_section,
                                    'imovel_admin_action' => 'list',
                                    'imovel_admin_per_page' => $per_page,
                                    'imovel_admin_paged' => '%#%',
                                ),
                                $dashboard_url
                            );
                            echo paginate_links(
                                array(
                                    'base' => $pagination_base,
                                    'format' => '',
                                    'current' => $current_page,
                                    'total' => $total_pages,
                                    'prev_text' => '«',
                                    'next_text' => '»',
                                )
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e( 'Nenhum item encontrado.', 'imovel-parceiro-core' ); ?></p>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
