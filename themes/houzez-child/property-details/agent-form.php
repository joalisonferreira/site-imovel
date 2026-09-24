<?php
// Child override of houzez/property-details/agent-form.php.
// Contact buttons are only active for qualified viewers (logged-in
// corretor/imobiliária with an active plan and a verified profile).
// Logged-out viewers get disabled buttons that open the login modal and
// show a login message. Phone numbers never reach the page source for
// unqualified viewers.
global $post, $current_user, $ele_settings;
$return_array = houzez20_get_property_agent();
if(empty($return_array)) {
	return;
}

$agent_info = isset($ele_settings['agent_detail']) ? $ele_settings['agent_detail'] : 'yes';

$terms_page_id = houzez_option('terms_condition');
$terms_page_id = apply_filters( 'wpml_object_id', $terms_page_id, 'page', true );
$hide_form_fields = houzez_option('hide_prop_contact_form_fields');
$gdpr_checkbox = houzez_option('gdpr_hide_checkbox', 1);
$agent_display = houzez_get_listing_data('agent_display_option');
$property_id = houzez_get_listing_data('property_id');

$ipc_is_logged_in = is_user_logged_in();
$ipc_can_see_contact = class_exists( 'Imovel_Parceiro_Contact_Visibility' ) && Imovel_Parceiro_Contact_Visibility::viewer_can_see_contact();
$ipc_login_message = __( 'Faça login como corretor ou imobiliária para ver os contatos deste imóvel.', 'imovel-parceiro-core' );
$ipc_qualify_message = __( 'Contatos disponíveis para corretores e imobiliárias com plano ativo e perfil verificado.', 'imovel-parceiro-core' );
// Proteção de lead: cliente usa o fluxo "Tenho interesse neste imóvel".
if ( class_exists( 'Imovel_Parceiro_Property_Interest' ) && Imovel_Parceiro_Property_Interest::is_client() ) {
	$ipc_qualify_message = __( 'Para falar sobre este imóvel, use o botão "Tenho interesse neste imóvel". O corretor responsável entrará em contato.', 'imovel-parceiro-core' );
}

// Scrub phone/email leaks from the agent info block for unqualified viewers.
$ipc_agent_data_html = isset( $return_array['agent_data'] ) ? $return_array['agent_data'] : '';
if ( ! $ipc_can_see_contact && '' !== $ipc_agent_data_html && class_exists( 'Imovel_Parceiro_Contact_Visibility' ) ) {
	$ipc_agent_data_html = Imovel_Parceiro_Contact_Visibility::strip_nodes_by_class( $ipc_agent_data_html, array( 'agent-phone' ) );
	$ipc_agent_data_html = preg_replace( '/<input[^>]*name="target_email\[\]"[^>]*>/', '', $ipc_agent_data_html );
}

$agent_number = $return_array['agent_mobile'] ?? '';
$agent_whatsapp_call = $return_array['agent_whatsapp_call'] ?? '';
$agent_mobile_call = $return_array['agent_mobile_call'] ?? '';
if( empty($agent_number) ) {
	$agent_number = $return_array['agent_phone'] ?? '';
	$agent_mobile_call = $return_array['agent_phone_call'] ?? '';
}

$user_name = $user_email = '';
if(!houzez_is_admin()) {
	$user_name =  $current_user->display_name;
	$user_email =  $current_user->user_email;
}

$action_class = "houzez-send-message";
$login_class = '';
$dataModel = '';
if( !is_user_logged_in() ) {
	$action_class = '';
	$login_class = 'msg-login-required';
	$dataModel = 'data-bs-toggle="modal" data-bs-target="#login-register-form"';
}

$agent_email = !empty($return_array['agent_email']) ? is_email($return_array['agent_email']) : false;

$agent_mobile_num = houzez_option('agent_mobile_num', 1 ); 
$agent_whatsapp_num = houzez_option('agent_whatsapp_num', 1);

if ($agent_email && $agent_display != 'none') {
?>
<div class="property-form-wrap" role="complementary">

	<?php 
	if(houzez_form_type()) {

		if( $agent_info == 'yes' ) {
			echo $ipc_agent_data_html;
		}
		
		if(!empty(houzez_option('contact_form_agent_above_image'))) {
			echo do_shortcode(houzez_option('contact_form_agent_above_image'));
		}

	} else { ?>
		<div class="property-form">
			<form method="post" action="#">
				
				<?php 
				if( $agent_info == 'yes' ) {
					echo $ipc_agent_data_html; 
				}?>

				<?php if( isset($hide_form_fields['name']) && $hide_form_fields['name'] != 1 ) { ?>
				<div class="form-group mb-2 mt-3">
					<input class="form-control" name="name" value="<?php echo esc_attr($user_name); ?>" type="text" placeholder="<?php echo houzez_option('spl_con_name', 'Name'); ?>">
				</div><!-- form-group -->
				<?php } ?>

				<?php if( isset($hide_form_fields['phone']) && $hide_form_fields['phone'] != 1 ) { ?>	
				<div class="form-group mb-2">
					<input class="form-control" name="mobile" value="" type="text" placeholder="<?php echo houzez_option('spl_con_phone', 'Phone'); ?>">
				</div><!-- form-group -->
				<?php } ?>

				<div class="form-group mb-2">
					<input class="form-control" name="email" value="<?php echo esc_attr($user_email); ?>" type="email" placeholder="<?php echo houzez_option('spl_con_email', 'Email'); ?>">
				</div><!-- form-group -->

				<?php if( isset($hide_form_fields['message']) && $hide_form_fields['message'] != 1 ) { ?>	
				<div class="form-group form-group-textarea mb-2">
					<textarea class="form-control hz-form-message" name="message" rows="4" placeholder="<?php echo houzez_option('spl_con_message', 'Message'); ?>"><?php echo houzez_option('spl_con_interested', "Hello, I am interested in"); ?> [<?php echo get_the_title(); ?>]</textarea>
				</div><!-- form-group -->	
				<?php } ?>

				<?php if( isset($hide_form_fields['usertype']) && $hide_form_fields['usertype'] != 1 ) { ?>	
				<div class="form-group mb-2">
					<select name="user_type" class="selectpicker form-control bs-select-hidden" title="<?php echo houzez_option('spl_con_select', 'Select'); ?>">

						<?php if( houzez_option('spl_con_buyer') != "" ) { ?>
						<option value="buyer"><?php echo houzez_option('spl_con_buyer', "I'm a buyer"); ?></option>
						<?php } ?>

						<?php if( houzez_option('spl_con_tennant') != "" ) { ?>
						<option value="tennant"><?php echo houzez_option('spl_con_tennant', "I'm a tennant"); ?></option>
						<?php } ?>

						<?php if( houzez_option('spl_con_agent') != "" ) { ?>
						<option value="agent"><?php echo houzez_option('spl_con_agent', "I'm an agent"); ?></option>
						<?php } ?>

						<?php if( houzez_option('spl_con_other') != "" ) { ?>
						<option value="other"><?php echo houzez_option('spl_con_other', 'Other'); ?></option>
						<?php } ?>
					</select><!-- selectpicker -->
				</div><!-- form-group -->
				<?php } ?>

				<?php do_action('houzez_property_agent_contact_fields'); ?>

				<?php if( houzez_option('gdpr_and_terms_checkbox', 1) ) { ?>
				<div class="form-group my-3">
					<label class="control control--checkbox m-0 <?php if( $gdpr_checkbox ){ echo 'p-0 hz-no-gdpr-checkbox';}?>">
						<?php if( ! $gdpr_checkbox ) { ?>
						<input type="checkbox" name="privacy_policy" aria-required="true">
						<span class="control__indicator"></span>
						<?php } ?>
						<div class="gdpr-text-wrap">
							<?php echo houzez_option('spl_sub_agree', 'By submitting this form I agree to'); ?> <a target="_blank" href="<?php echo esc_url(get_permalink($terms_page_id)); ?>"><?php echo houzez_option('spl_term', 'Terms of Use'); ?></a>
						</div>
						
					</label>
				</div><!-- form-group -->	
				<?php } ?>		
			
		        <input type="hidden" name="property_agent_contact_security" value="<?php echo wp_create_nonce('property_agent_contact_nonce'); ?>"/>
		        <input type="hidden" name="property_permalink" value="<?php echo esc_url(get_permalink($post->ID)); ?>"/>
		        <input type="hidden" name="property_title" value="<?php echo esc_attr(get_the_title($post->ID)); ?>"/>
		        <input type="hidden" name="property_id" value="<?php echo esc_attr($property_id); ?>"/>
		        <input type="hidden" name="action" value="houzez_property_agent_contact">
		        <input type="hidden" name="listing_id" value="<?php echo intval($post->ID)?>">
		        <input type="hidden" name="is_listing_form" value="yes">
		        <input type="hidden" name="agent_id" value="<?php echo isset($return_array['agent_id']) ? intval($return_array['agent_id']) : ''; ?>">
		        <input type="hidden" name="agent_type" value="<?php echo isset($return_array['agent_type']) ? esc_attr($return_array['agent_type']) : ''; ?>">

		        <?php do_action('houzez_after_property_agent_form_fields'); ?>

		        <?php get_template_part('template-parts/captcha'); ?>
		        <div class="form_messages"></div>
				
				<div class="property-schedule-tour-type-form d-flex justify-content-between gap-2 mb-2">
					<?php if ( $ipc_can_see_contact ) : ?>
					<button type="button" class="houzez-ele-button houzez_agent_property_form btn btn-secondary w-100">
						<?php get_template_part('template-parts/loader'); ?>
						<span><?php echo houzez_option('spl_btn_send', 'Send Email'); ?></span>
					</button>
					<?php else : ?>
					<button type="button" class="houzez-ele-button btn btn-secondary w-100 ipc-contact-locked" aria-disabled="true" data-ipc-message="<?php echo esc_attr( $ipc_is_logged_in ? $ipc_qualify_message : $ipc_login_message ); ?>"<?php echo $ipc_is_logged_in ? '' : ' data-bs-toggle="modal" data-bs-target="#login-register-form"'; ?>>
						<?php get_template_part('template-parts/loader'); ?>
						<span><?php echo houzez_option('spl_btn_send', 'Send Email'); ?></span>
					</button>
					<?php endif; ?>
					
					<?php if ( $ipc_can_see_contact && $return_array['is_single_agent'] == true && !empty($agent_number) && $agent_mobile_num && !wp_is_mobile() ) : ?>
					<a href="tel:<?php echo esc_attr($agent_mobile_call); ?>" data-property-id="<?php echo intval($post->ID); ?>" data-agent-id="<?php echo isset($return_array['agent_id']) ? intval($return_array['agent_id']) : ''; ?>" class="btn btn-secondary-outlined hz-btn-call w-100">
						<span class="hide-on-click"><?php echo houzez_option('spl_btn_call', 'Call'); ?></span>
						<span class="show-on-click"><?php echo esc_attr($agent_number); ?></span>
					</a>
					<?php elseif ( ! $ipc_can_see_contact && $return_array['is_single_agent'] == true && !empty($agent_number) && $agent_mobile_num && !wp_is_mobile() ) : ?>
					<button type="button" class="btn btn-secondary-outlined hz-btn-call w-100 ipc-contact-locked" aria-disabled="true" data-ipc-message="<?php echo esc_attr( $ipc_is_logged_in ? $ipc_qualify_message : $ipc_login_message ); ?>"<?php echo $ipc_is_logged_in ? '' : ' data-bs-toggle="modal" data-bs-target="#login-register-form"'; ?>>
						<span><?php echo houzez_option('spl_btn_call', 'Call'); ?></span>
					</button>
					<?php endif; ?>
				</div>


				<?php if( $ipc_can_see_contact && $return_array['is_single_agent'] == true && !empty($agent_whatsapp_call) && $agent_whatsapp_num ) { ?>
				<a target="_blank" href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $agent_whatsapp_call ); ?>&text=<?php echo houzez_option('spl_con_interested', "Hello, I am interested in").' ['.get_the_title().'] '.get_permalink(); ?>" data-property-id="<?php echo intval($post->ID); ?>" data-agent-id="<?php echo isset($return_array['agent_id']) ? intval($return_array['agent_id']) : ''; ?>" class="btn btn-secondary-outlined w-100 hz-btn-whatsapp mb-2"><i class="houzez-icon icon-messaging-whatsapp me-1"></i> <?php esc_html_e('WhatsApp', 'houzez'); ?></a>
				<?php } elseif( ! $ipc_can_see_contact && $return_array['is_single_agent'] == true && !empty($agent_whatsapp_call) && $agent_whatsapp_num ) { ?>
				<button type="button" class="btn btn-secondary-outlined w-100 hz-btn-whatsapp mb-2 ipc-contact-locked" aria-disabled="true" data-ipc-message="<?php echo esc_attr( $ipc_is_logged_in ? $ipc_qualify_message : $ipc_login_message ); ?>"<?php echo $ipc_is_logged_in ? '' : ' data-bs-toggle="modal" data-bs-target="#login-register-form"'; ?>><i class="houzez-icon icon-messaging-whatsapp me-1"></i> <?php esc_html_e('WhatsApp', 'houzez'); ?></button>
				<?php } ?>

				<?php if( $return_array['is_single_agent'] == true && houzez_option('agent_direct_messages', 0) ) { ?>
					<?php if ( $ipc_can_see_contact ) : ?>
				<button type="button" <?php echo $dataModel; ?> class="<?php echo esc_attr($action_class).' '.esc_attr($login_class); ?> btn btn-secondary-outlined w-100">
					<?php get_template_part('template-parts/loader'); ?>
					<?php echo houzez_option('spl_btn_message', 'Send Message'); ?>		
				</button>
					<?php else : ?>
				<button type="button"<?php echo $ipc_is_logged_in ? '' : ' ' . $dataModel; ?> class="<?php echo $ipc_is_logged_in ? '' : esc_attr($login_class); ?> btn btn-secondary-outlined w-100 ipc-contact-locked" aria-disabled="true" data-ipc-message="<?php echo esc_attr( $ipc_is_logged_in ? $ipc_qualify_message : $ipc_login_message ); ?>">
					<?php get_template_part('template-parts/loader'); ?>
					<?php echo houzez_option('spl_btn_message', 'Send Message'); ?>
				</button>
					<?php endif; ?>
				<?php } ?>
			</form>
		</div><!-- property-form -->
		
	<?php } ?>
</div><!-- property-form-wrap -->
<?php } ?>
<?php
// Toast + interceptador dos botões bloqueados (renderizado uma vez por página).
if ( empty( $GLOBALS['ipc_contact_locked_assets'] ) ) :
	$GLOBALS['ipc_contact_locked_assets'] = true;
	?>
<style>.ipc-contact-locked{opacity:.65;cursor:not-allowed}#ipc-contact-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);z-index:10060;max-width:min(92vw,480px);background:#0f172a;color:#fff;border-radius:12px;padding:12px 18px;font-size:14px;font-weight:500;box-shadow:0 12px 32px rgba(2,6,23,.35);opacity:0;pointer-events:none;transition:opacity .25s ease,transform .25s ease;text-align:center}#ipc-contact-toast.ipc-show{opacity:1;transform:translateX(-50%) translateY(0)}</style>
<div id="ipc-contact-toast" role="status" aria-live="polite"></div>
<script>
(function(){
	if (window.__ipcContactLockedBound) { return; }
	window.__ipcContactLockedBound = true;
	var toastTimer = null;
	function showToast(msg){
		var toast = document.getElementById('ipc-contact-toast');
		if (!toast) { return; }
		toast.textContent = msg;
		toast.classList.add('ipc-show');
		if (toastTimer) { clearTimeout(toastTimer); }
		toastTimer = setTimeout(function(){ toast.classList.remove('ipc-show'); }, 4500);
	}
	document.addEventListener('click', function(e){
		var btn = e.target && e.target.closest ? e.target.closest('.ipc-contact-locked') : null;
		if (!btn) { return; }
		e.preventDefault();
		showToast(btn.getAttribute('data-ipc-message') || '');
	}, true);
})();
</script>
<?php endif; ?>
