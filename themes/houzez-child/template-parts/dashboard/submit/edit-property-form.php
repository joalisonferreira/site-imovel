<?php
/**
 * Created by PhpStorm.
 * User: waqasriaz
 * Date: 07/10/15
 * Time: 3:45 PM
 */
global $current_user, $hide_add_prop_fields, $required_fields, $is_multi_steps, $prop_meta_data;

$userID = get_current_user_id();
$packageUserId = $userID;

$agent_agency_id = houzez_get_agent_agency_id( $userID );
if( $agent_agency_id ) {
    $packageUserId = $agent_agency_id;
}

$enable_paid_submission = houzez_option('enable_paid_submission');
$select_packages_link = houzez_get_template_link('template/template-packages.php');
$remaining_listings = houzez_get_remaining_listings($packageUserId);
$show_submit_btn = houzez_option('submit_form_type');

if (is_page_template('template/user_dashboard_submit.php')) {

    $allowed_html = array(
        'i' => array(
            'class' => array()
        ),
        'strong' => array(),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array()
        )
    );

    // Check Author
    global $current_user, $property_data, $prop_meta_data;
    $current_user = wp_get_current_user();

    // Validate and sanitize the edit_property parameter
    $edit_prop_id = 0;
    if (isset($_GET['edit_property']) && !empty($_GET['edit_property'])) {
        $edit_prop_id = intval(trim($_GET['edit_property']));
    }

    if ($edit_prop_id <= 0) {
        esc_html_e('Invalid property ID provided.', 'houzez');
        return;
    }

    $gallery_error_label   = wp_strip_all_tags( __( 'Error!', 'houzez' ) );
    $gallery_error_message = wp_strip_all_tags( __( 'Upload at least one image.', 'houzez' ) );

    $required_error_label   = wp_strip_all_tags( __( 'Error!', 'houzez' ) );
    $required_error_message = wp_strip_all_tags( __( 'Please fill out the following required fields.', 'houzez' ) );

    $property_data = get_post($edit_prop_id);

    if (!empty($property_data) && ($property_data->post_type == 'property')) {
        $prop_meta_data = get_post_custom($property_data->ID);

        if ($property_data->post_author == $current_user->ID || houzez_can_manage() || houzez_is_editor()) {
            
            if (!houzez_is_admin() && 
                $property_data->post_status == 'draft' && 
                $enable_paid_submission == 'membership' && 
                $remaining_listings != -1 && 
                $remaining_listings < 1 && 
                is_user_logged_in()) {
                
                if (!houzez_user_has_membership($packageUserId)) {
                    print '<div class="user_package_status">
                        <h4>' . esc_html__("You don't have any package! You need to buy your package.", 'houzez') . '</h4>
                        <a class="btn btn-primary" href="' . esc_url($select_packages_link) . '">' . esc_html__('Get Package', 'houzez') . '</a>
                        </div>';
                } else {
                    print '<div class="user_package_status">
                        <h4>' . esc_html__("Your current package doesn't let you publish more properties! You need to upgrade your membership.", 'houzez') . '</h4>
                        <a class="btn btn-primary" href="' . esc_url($select_packages_link) . '">' . esc_html__('Upgrade Package', 'houzez') . '</a>
                        </div>';
                }
            } else { ?>

                <form id="submit_property_form" name="new_post" method="post" action="" enctype="multipart/form-data" class="update-frontend-property">
                    <input type="hidden" name="draft_prop_id" value="<?php echo intval($edit_prop_id); ?>">
                    
                    <?php if (isset($_GET['updated']) && intval($_GET['updated']) === 1) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php esc_html_e('Updated successfully.', 'houzez'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>

                    <?php if (isset($_GET['success']) && intval($_GET['success']) === 1) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php esc_html_e('Submitted successfully.', 'houzez'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>

                    <div class="validate-errors alert alert-danger alert-dismissible fade houzez-hidden" role="alert" style="display: none;">
                        <?php printf(
                        '<strong>%s</strong> %s',
                        $required_error_label,
                        $required_error_message
                        ); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                    <div class="validate-errors-gal alert alert-danger alert-dismissible fade houzez-hidden" role="alert" style="display: none;">
                        <?php printf(
                        '<strong>%s</strong> %s',
                        $gallery_error_label,
                        $gallery_error_message
                        ); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                    <?php get_template_part('template-parts/dashboard/submit/partials/menu-edit-property-mobile'); ?>

                    <?php
                    $layout = houzez_option('property_form_sections');
                    $layout = isset($layout['enabled']) ? $layout['enabled'] : [];

                    if ($layout): 
                        foreach ($layout as $key => $value) {
                            switch($key) {
                                case 'description-price':
                                    get_template_part('template-parts/dashboard/submit/description-and-price');
                                    break;

                                case 'media':
                                    get_template_part('template-parts/dashboard/submit/media');
                                    break;

                                case 'details':
                                    get_template_part('template-parts/dashboard/submit/details');
                                    break;

                                case 'energy_class':
                                    get_template_part('template-parts/dashboard/submit/energy-class');
                                    break;

                                case 'features':
                                    get_template_part('template-parts/dashboard/submit/features');
                                    break;

                                case 'location':
                                    get_template_part('template-parts/dashboard/submit/location');
                                    break;
                                
                                case 'virtual_tour':
                                    get_template_part('template-parts/dashboard/submit/360-virtual-tour');
                                    break;

                                case 'floorplans':
                                    get_template_part('template-parts/dashboard/submit/floor', 'plans');
                                    break;

                                case 'multi-units':
                                    get_template_part('template-parts/dashboard/submit/sub-properties');
                                    break;

                                case 'agent_info':
                                    if (houzez_show_agent_box()) {
                                        get_template_part('template-parts/dashboard/submit/contact-information');
                                    }
                                    break;

                                case 'private_note':
                                    get_template_part('template-parts/dashboard/submit/private-note');
                                    break;

                                case 'attachments':
                                    get_template_part('template-parts/dashboard/submit/attachments');
                                    break;
                            }
                        }
                    endif;

                    if (houzez_is_admin()) {
                        get_template_part('template-parts/dashboard/submit/settings');
                    }
                    ?>
                    
                    <?php wp_nonce_field('submit_property', 'property_nonce'); ?>
                    <input type="hidden" name="action" value="update_property"/>
                    <input type="hidden" name="property_author" value="<?php echo intval($property_data->post_author); ?>"/>
                    <input type="hidden" name="prop_id" value="<?php echo intval($property_data->ID); ?>"/>

                    <?php if (!houzez_is_admin()) { ?>
                        <input type="hidden" name="prop_featured" value="<?php if (isset($prop_meta_data['fave_featured']) && isset($prop_meta_data['fave_featured'][0])) { echo sanitize_text_field($prop_meta_data['fave_featured'][0]); } ?>">
                    <?php } ?>

                    <input type="hidden" name="prop_payment" value="<?php if (isset($prop_meta_data['fave_payment_status']) && isset($prop_meta_data['fave_payment_status'][0])) { echo sanitize_text_field($prop_meta_data['fave_payment_status'][0]); } ?>"/>
                    <input type="hidden" name="draft_property_id" value="<?php echo intval($edit_prop_id); ?>">
                    <?php if (get_post_status($edit_prop_id) == 'draft') {
                        echo '<input type="hidden" name="houzez_draft" value="draft">';
                    } ?>

                    <?php
                    $ipc_cancel_link = function_exists( 'houzez_dashboard_listings' ) ? houzez_dashboard_listings() : home_url( '/' );
                    $ipc_cancel_label = houzez_option( 'fal_cancel', esc_html__( 'Cancel', 'houzez' ) );
                    if ( '' === trim( (string) $ipc_cancel_label ) ) { $ipc_cancel_label = 'Cancelar'; }
                    $ipc_back_label = houzez_option( 'fal_back', esc_html__( 'Back', 'houzez' ) );
                    if ( '' === trim( (string) $ipc_back_label ) ) { $ipc_back_label = 'Voltar'; }
                    $ipc_next_label = houzez_option( 'fal_next', esc_html__( 'Next', 'houzez' ) );
                    if ( '' === trim( (string) $ipc_next_label ) ) { $ipc_next_label = 'Próximo'; }
                    $ipc_save_label = houzez_option( 'fal_save_changes', esc_html__( 'Save Changes', 'houzez' ) );
                    if ( '' === trim( (string) $ipc_save_label ) ) { $ipc_save_label = 'Salvar alterações'; }
                    ?>
                    <div class="d-flex justify-content-between p-2 add-new-listing-bottom-nav-wrap">
                        <a href="<?php echo esc_url( $ipc_cancel_link ); ?>" class="btn-cancel btn btn-primary-outlined">
                            <?php echo esc_html( $ipc_cancel_label ); ?>
                        </a>
                        <?php if ( $show_submit_btn == 'one_step' ) { ?>
                            <button type="submit" class="btn btn-success houzez-submit-js">
                                <?php get_template_part('template-parts/loader'); ?>
                                <?php echo esc_html( $ipc_save_label ); ?>
                            </button>
                        <?php } else { ?>
                            <div class="d-flex gap-2 align-items-center">
                                <button type="button" class="btn-back houzez-hidden btn btn-primary-outlined">
                                    <i class="houzez-icon icon-arrow-left-1 me-2"></i> <?php echo esc_html( $ipc_back_label ); ?>
                                </button>
                                <button type="button" class="btn-next btn btn-primary">
                                    <?php echo esc_html( $ipc_next_label ); ?> <i class="houzez-icon icon-arrow-right-1 ms-2"></i>
                                </button>
                                <div class="btn-step-submit" style="display: none;">
                                    <button type="submit" class="btn btn-success houzez-submit-js">
                                        <?php get_template_part('template-parts/loader'); ?>
                                        <?php echo esc_html( $ipc_save_label ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </form>
                <script>
                (function($){
                    $(function(){
                        var $form = $('form.update-frontend-property');
                        if (!$form.length) return;
                        // Sincroniza menu lateral com as setas Próximo/Voltar no modo edição.
                        function syncMenuToVisible(){
                            var $steps = $form.find('.form-step');
                            if (!$steps.length) return;
                            var $vis = $steps.filter(':visible').first();
                            if (!$vis.length) return;
                            var id = $vis.attr('id');
                            if (!id) return;
                            var $links = $('.menu-edit-property li a');
                            if ($links.length) {
                                $links.removeClass('active');
                                $links.filter('[data-val="'+id+'"]').addClass('active');
                            }
                            var $mobile = $('#menu_edit_mobile');
                            if ($mobile.length) { $mobile.val(id); }
                        }
                        $form.on('click', '.btn-next, .btn-back', function(){
                            setTimeout(syncMenuToVisible, 60);
                        });
                        $(document).on('click', '.menu-edit-property li a', function(){
                            setTimeout(syncMenuToVisible, 60);
                        });
                        setTimeout(syncMenuToVisible, 300);
                    });
                })(jQuery);
                </script>

            <?php }
        } else {
            esc_html_e('You are not logged in or This property does not belong to logged in user.', 'houzez');
        }
    } else {
        esc_html_e('This is not a valid request', 'houzez');
    }
}


