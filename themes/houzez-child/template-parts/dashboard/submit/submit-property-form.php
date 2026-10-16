<?php
/**
 * Created by PhpStorm.
 * User: waqasriaz
 * Date: 06/10/15
 * Time: 4:12 PM
 */
$userID = get_current_user_id();
$packageUserId = $userID;
$user_show_roles = houzez_option('user_show_roles');
$show_hide_roles = houzez_option('show_hide_roles');
$enable_paid_submission = houzez_option('enable_paid_submission');
$select_packages_link = houzez_get_template_link('template/template-packages.php'); 

$agent_agency_id = houzez_get_agent_agency_id( $userID );
if( $agent_agency_id ) {
    $packageUserId = $agent_agency_id;
}

$remaining_listings = houzez_get_remaining_listings( $packageUserId );

$cancel_link = houzez_dashboard_listings();
if( !is_user_logged_in() ) {
  $cancel_link = home_url('/');  
}

$show_submit_btn = houzez_option('submit_form_type');
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

$gallery_error_label   = wp_strip_all_tags( __( 'Erro!', 'imovel-parceiro-core' ) );
$gallery_error_message = wp_strip_all_tags( __( 'Envie ao menos uma imagem do imóvel.', 'imovel-parceiro-core' ) );

$required_error_label   = wp_strip_all_tags( __( 'Erro!', 'imovel-parceiro-core' ) );
$required_error_message = wp_strip_all_tags( __( 'Preencha os campos obrigatórios destacados abaixo.', 'imovel-parceiro-core' ) );

if( is_page_template( 'template/user_dashboard_submit.php' ) ) {

    if (!houzez_is_admin() && $enable_paid_submission == 'membership' && $remaining_listings != -1 && $remaining_listings < 1 && is_user_logged_in() ) {

        echo '<div class="block-wrap">
                <div class="block-content-wrap">';
        if (!houzez_user_has_membership($userID)) {
            print '<p class="mb-3">' . esc_html__("Você não tem um plano ativo. Escolha um plano para publicar seu imóvel.", 'imovel-parceiro-core') . '</p>
                    <a class="btn btn-success" href="' . $select_packages_link . '">' . esc_html__('Ver planos', 'imovel-parceiro-core') . '</a>';
        } else {
            print '<p class="mb-3">' . esc_html__("Seu plano atual não permite publicar mais imóveis. Troque de plano para continuar.", 'imovel-parceiro-core') . '</p>
            <a class="btn btn-success" href="' . $select_packages_link . '">' . esc_html__('Trocar de plano', 'imovel-parceiro-core') . '</a>';
        }
        echo '</div>
        </div>';

    } else { ?>

        <form autocomplete="off" id="submit_property_form" name="new_post" method="post" action="#" enctype="multipart/form-data"
              class="add-frontend-property" novalidate>

            <div class="validate-errors alert alert-danger alert-dismissible fade show houzez-hidden" role="alert">
                <?php printf(
                    '<strong>%s</strong> %s',
                    $required_error_label,
                    $required_error_message
                ); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="validate-errors-gal alert alert-danger alert-dismissible fade show houzez-hidden" role="alert">
                <?php printf(
                    '<strong>%s</strong> %s',
                    $gallery_error_label,
                    $gallery_error_message
                ); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="d-block d-lg-none">
                <?php get_template_part('template-parts/dashboard/submit/partials/author-mobile'); ?>
            </div>

            <?php
            $layout = houzez_option('property_form_sections');
            $layout = isset($layout['enabled']) ? $layout['enabled'] : [];

            if ($layout): foreach ($layout as $key=>$value) {

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
                        if(houzez_show_agent_box()) {
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

            if( houzez_is_admin() || houzez_is_editor() ) {
                get_template_part('template-parts/dashboard/submit/settings');
            }

            if(houzez_option('add-prop-gdpr-enabled')) {
                get_template_part('template-parts/dashboard/submit/gdpr'); 
            }

            if ( !is_user_logged_in() ) { 

                get_template_part('template-parts/dashboard/submit/account');

             } ?>

            <?php wp_nonce_field('submit_property', 'property_nonce'); ?>

            <input type="hidden" name="action" value="add_property"/>
            <input type="hidden" name="property_author" value="<?php echo intval($userID);?>"/>
            <?php if( ! houzez_is_admin() ) { ?>
            <input type="hidden" name="prop_featured" value="0"/>
            <?php } ?>

            <input type="hidden" name="prop_payment" value="not_paid"/>
            <?php if ( !is_user_logged_in() && $enable_paid_submission == 'membership' ) { ?>
                <input type="hidden" name="user_submit_has_no_membership" value="yes"/>
            <?php } ?>

            
            <?php
            // Override do child (stitch Imóvel Parceiro): slots explícitos —
            // esquerda = Cancelar (etapa 1) / Voltar (etapas 2+), direita =
            // Próximo/Salvar. A lógica de show/hide do tema (por classe) segue
            // intacta. Se o pai atualizar este arquivo, replicar o bloco abaixo.
            ?>
            <div class="d-flex justify-content-between align-items-center gap-2 p-2 add-new-listing-bottom-nav-wrap">
                <p class="ipc-form-step-hint text-muted small m-0" aria-live="polite"><?php esc_html_e('Preencha as etapas com atenção — os erros ficam destacados em vermelho.', 'imovel-parceiro-core'); ?></p>
                <div class="d-flex gap-2 align-items-center">
                    <a href="<?php echo esc_url($cancel_link ); ?>" class="btn-cancel btn btn-danger">
                        <?php echo esc_html__('Cancelar', 'imovel-parceiro-core'); ?>
                    </a>
                    <?php if($show_submit_btn != 'one_step') { ?>
                        <button class="btn-back houzez-hidden btn btn-danger" aria-label="<?php esc_attr_e('Voltar etapa', 'imovel-parceiro-core'); ?>">
                            <i class="houzez-icon icon-arrow-left-1 me-2"></i> <?php esc_html_e('Voltar', 'imovel-parceiro-core'); ?>
                        </button>
                    <?php } ?>
                </div>

            <?php if($show_submit_btn == 'one_step') { ?>
                <button id="add_new_property" type="submit" class="btn houzez-submit-js btn-success">
                    <?php get_template_part('template-parts/loader'); ?>
                    <?php echo esc_html__('Publicar imóvel', 'imovel-parceiro-core'); ?>        
                </button>
            <?php } else { ?>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn-next btn btn-success" aria-label="<?php esc_attr_e('Avançar etapa', 'imovel-parceiro-core'); ?>">
                        <?php echo esc_html__('Avançar', 'imovel-parceiro-core'); ?> <i class="houzez-icon icon-arrow-right-1 ms-2"></i>
                    </button>

                    <div class="btn-step-submit" style="display: none;">
                        <button id="add_new_property" type="submit" class="btn houzez-submit-js btn-success">
                            <?php get_template_part('template-parts/loader'); ?>
                            <?php echo esc_html__('Publicar imóvel', 'imovel-parceiro-core'); ?>
                        </button>
                    </div>
                </div>
            <?php } ?>
            </div>

            
        </form>

        <?php
    }
}