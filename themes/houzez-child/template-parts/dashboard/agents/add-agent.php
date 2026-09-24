<?php
$userID       = get_current_user_id();
$dash_profile_link = houzez_get_template_link_2('template/user_dashboard_profile.php');
$agency_agent_add = add_query_arg( 'agents', 'list', $dash_profile_link );

$agency_id = get_user_meta($userID, 'fave_author_agency_id', true );
$agency_ids_cpt = get_post_meta($agency_id, 'fave_agency_cpt_agent', false );
$action = 'houzez_agency_agent';
$submit_id = 'houzez_agency_agent_register';

$username = $user_email = $first_name = $last_name = $agency_user_agent_id = $agency_user_id = '';
?>

<div class="heading d-flex align-items-center justify-content-between">
  <div class="heading-text">
    <h2><?php echo houzez_option('dsh_addnew', 'Add New Agent'); ?></h2> 
  </div>
  <div>
    <a class="btn btn-primary" href="<?php echo esc_url($agency_agent_add); ?>"><?php echo esc_html__('View All', 'houzez'); ?></a>
  </div>
</div> 

<div class="houzez-data-content">  
  <div class="block-wrap">
    <div class="block-content">
    <form method="" action="">
        <div class="mb-3">
          <label for="aa_username" class="form-label"><?php esc_html_e('Username','houzez');?></label>
          <input type="text" <?php if(!empty($agency_user_id)) { echo 'disabled'; } ?> name="aa_username" id="aa_username" class="form-control" value=""> 
        </div>
        <div class="mb-3">
          <label for="aa_email" class="form-label"><?php esc_html_e('Email','houzez');?></label>
          <input type="text" name="aa_email" id="aa_email" class="form-control" value=""> 
        </div>
        <div class="mb-3">
          <label for="aa_firstname" class="form-label"><?php esc_html_e('First Name','houzez');?></label>
          <input type="text" name="aa_firstname" id="aa_firstname" class="form-control" value=""> 
        </div>
        <div class="mb-3">
          <label for="aa_lastname" class="form-label"><?php esc_html_e('Last Name','houzez');?></label>
          <input type="text" name="aa_lastname" id="aa_lastname" class="form-control" value=""> 
        </div> 

        <?php 
        if( houzez_option('user_as_agent') == 'yes' ) { ?>
            
            <div class="mb-3">
                <label for="agent_category" class="form-label"><?php esc_html_e('Category','houzez');?></label>
                <select name="agent_category" class="selectpicker form-control bs-select-hidden" data-size="5" data-live-search="true" disabled>
                <?php
                if ( $agency_user_id != "" ) {
                    houzez_get_taxonomies_for_edit_listing( $agency_user_agent_id, 'agent_category');
                } else {
                    echo '<option value="">'.houzez_option('cl_none', 'None').'</option>';                
                    $agent_category_terms = get_terms (
                        array(
                            "agent_category"
                        ),
                        array(
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'hide_empty' => false,
                            'parent' => 0
                        )
                    );
                    houzez_get_taxonomies_with_id_value( 'agent_category', $agent_category_terms, -1);
                }
                ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="agent_city" class="form-label"><?php esc_html_e('City','houzez');?></label>
                <select name="agent_city" class="selectpicker form-control bs-select-hidden" data-size="5" data-live-search="true" disabled>
                <?php
                if ( $agency_user_id != "" ) {
                    houzez_get_taxonomies_for_edit_listing( $agency_user_agent_id, 'agent_city');
                } else {
                    echo '<option value="">'.houzez_option('cl_none', 'None').'</option>';                
                    $agent_city_terms = get_terms (
                        array(
                            "agent_city"
                        ),
                        array(
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'hide_empty' => false,
                            'parent' => 0
                        )
                    );
                    houzez_get_taxonomies_with_id_value( 'agent_city', $agent_city_terms, -1);
                }
                ?>
                </select>
            </div>

        <?php } ?>

        <div class="mb-3">
          <label for="aa_password" class="form-label"><?php esc_html_e('Password','houzez');?></label>
          <input type="password" id="aa_password" name="aa_password" value="" class="form-control"> 
        </div> 

        <?php if(empty($agency_user_id)) { ?>
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="aa_notification" name="aa_notification">
            <label class="form-check-label" for="aa_notification"> 
              <?php echo esc_html__('Send the new user an email about their account.', 'houzez');?>
            </label>
          </div>
        </div> 
        <?php } ?>

        <?php wp_nonce_field( 'houzez_agency_agent_ajax_nonce', 'houzez-security-agency-agent' );   ?>
        <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
        <input type="hidden" name="agency_id" value="<?php echo intval($userID); ?>" />
        <?php if( !empty($agency_ids_cpt)) {
            foreach( $agency_ids_cpt as $ag_id ): ?>
            <input type="hidden" name="agency_ids_cpt[]" value="<?php echo esc_attr($ag_id); ?>" />
        <?php
            endforeach;
            } else { ?>
            <input type="hidden" name="agency_ids_cpt[]" value='' />
        <?php } ?>
        <input type="hidden" name="agency_id_cpt" value='<?php echo $agency_id; ?>' />
        <input type="hidden" name="agency_user_agent_id" value="<?php echo intval($agency_user_agent_id); ?>" />
        <input type="hidden" name="agency_user_id" value="<?php echo intval($agency_user_id); ?>" />

        <div class="mb-3">
          <button id="<?php echo esc_attr($submit_id); ?>" class="btn btn-primary">
            <?php get_template_part('template-parts/loader'); ?>
            <?php esc_html_e('Save','houzez');?>
          </button>
          <div id="aa_register_message"></div>
        </div>
    </form>
  </div>
</div> 
</div>