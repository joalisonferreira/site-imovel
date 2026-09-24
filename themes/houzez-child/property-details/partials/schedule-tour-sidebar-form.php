<?php
// Child override of houzez/property-details/partials/schedule-tour-sidebar-form.php.
// The tour request emails the broker, so the submit is only active for
// qualified viewers (logged-in corretor/imobiliária with an active plan and
// a verified profile). Everyone else gets a disabled button with guidance.
global $post, $current_user, $ele_settings;
$return_array = houzez20_get_property_agent();
if(empty($return_array)) {
	return;
}
$terms_page_id = houzez_option('terms_condition');
$terms_page_id = apply_filters( 'wpml_object_id', $terms_page_id, 'page', true );
$agent_display = houzez_get_listing_data('agent_display_option');

$schedule_time_slots = houzez_option('schedule_time_slots');
$gdpr_checkbox = houzez_option('gdpr_hide_checkbox', 1);
$schedule_num_days = houzez_option('schedule_num_days', 8);
$agent_email = !empty($return_array['agent_email']) ? is_email($return_array['agent_email']) : false;

$agent_info = isset($ele_settings['agent_detail']) ? $ele_settings['agent_detail'] : 'yes';

$ipc_is_logged_in = is_user_logged_in();
$ipc_can_see_contact = class_exists( 'Imovel_Parceiro_Contact_Visibility' ) && Imovel_Parceiro_Contact_Visibility::viewer_can_see_contact();
$ipc_login_message = __( 'Faça login como corretor ou imobiliária para agendar uma visita.', 'imovel-parceiro-core' );
$ipc_qualify_message = __( 'Agendamento disponível para corretores e imobiliárias com plano ativo e perfil verificado.', 'imovel-parceiro-core' );
// Proteção de lead: cliente usa o fluxo "Tenho interesse neste imóvel".
if ( class_exists( 'Imovel_Parceiro_Property_Interest' ) && Imovel_Parceiro_Property_Interest::is_client() ) {
	$ipc_qualify_message = __( 'Para visitar este imóvel, use o botão "Tenho interesse neste imóvel". O corretor responsável entrará em contato.', 'imovel-parceiro-core' );
}

$ipc_agent_data_html = isset( $return_array['agent_data'] ) ? $return_array['agent_data'] : '';
if ( ! $ipc_can_see_contact && '' !== $ipc_agent_data_html && class_exists( 'Imovel_Parceiro_Contact_Visibility' ) ) {
	$ipc_agent_data_html = Imovel_Parceiro_Contact_Visibility::strip_nodes_by_class( $ipc_agent_data_html, array( 'agent-phone' ) );
	$ipc_agent_data_html = preg_replace( '/<input[^>]*name="target_email\[\]"[^>]*>/', '', $ipc_agent_data_html );
}

if ($agent_email && $agent_display != 'none') { 

if(houzez_form_type()) {

    echo '<div class="property-form-wrap">';
        
        if(!empty(houzez_option('schedule_tour_shortcode'))) {
            echo do_shortcode(houzez_option('schedule_tour_shortcode'));
        }
    echo '</div>';

} else { 
?>
<form method="post" action="#">

    <input type="hidden" name="schedule_contact_form_ajax"
       value="<?php echo wp_create_nonce('schedule-contact-form-nonce'); ?>"/>
    <input type="hidden" name="property_permalink"
           value="<?php echo esc_url(get_permalink($post->ID)); ?>"/>
    <input type="hidden" name="property_title"
           value="<?php echo esc_attr(get_the_title($post->ID)); ?>"/>
    <input type="hidden" name="action" value="houzez_schedule_send_message">

    <input type="hidden" name="listing_id" value="<?php echo intval($post->ID)?>">
    <input type="hidden" name="is_listing_form" value="yes">
    <input type="hidden" name="is_schedule_form" value="yes">
    <input type="hidden" name="agent_id" value="<?php echo isset($return_array['agent_id']) ? intval($return_array['agent_id']) : ''; ?>">
    <input type="hidden" name="agent_type" value="<?php echo isset($return_array['agent_type']) ? esc_attr($return_array['agent_type']) : ''; ?>">

    <div class="property-schedule-tour-form-wrap p-4">
        <?php 
        if( $agent_info == 'yes' ) {
            echo $ipc_agent_data_html; 
        }?>
        <div class="property-schedule-tour-day-form mt-3">
            <div class="property-schedule-tour-day-form-slide-wrap">
                <div class="property-schedule-tour-day-form-slide houzez-all-slider-wrap">
                    
                    <?php
                    $m = date("m"); // Current month
                    $de = date("d"); // Current day
                    $y = date("Y"); // Current year

                    $schedule_num_days = intval($schedule_num_days);

                    if (!$schedule_num_days) {
                        $schedule_num_days = 14; // Set default to 14 if not specified or invalid
                    }

                    // Adjust $i <= 20 for 21 days range (0 to 20 equals 21 days)
                    for($i = 0; $i <= $schedule_num_days; $i++) { 

                        $day = date_i18n('D', mktime(0, 0, 0, $m, ($de + $i), $y)); 
                        $day_number = date_i18n('d', mktime(0, 0, 0, $m, ($de + $i), $y));
                        $month = date_i18n('M', mktime(0, 0, 0, $m, ($de + $i), $y));
                    ?>
                        <div class="form-group">
                            <label class="control control--radio ps-0">
                                <input name="schedule_date" type="radio" value="<?php echo $day.' '.$day_number.' '.$month; ?>">
                                <span class="control__indicator d-block w-100 h-100 p-2">
                                    <?php echo $day ?><br>
                                    <span class="control__indicator_day"><?php echo $day_number ?></span><br>
                                    <?php echo $month ?>
                                </span>
                            </label>
                        </div>

                    <?php
                        }
                    ?>
        
                </div>
            </div>
        </div>

        <div class="property-schedule-tour-form-title my-3"><?php echo houzez_option('spl_con_tour_type', 'Tour Type'); ?></div>
        
        <div class="property-schedule-tour-type-form d-flex justify-content-between gap-2 mb-2">
            <div class="form-group w-100">
                <label class="control control--radio ps-0">
                <input name="schedule_tour_type" type="radio" checked value="<?php echo houzez_option('spl_con_in_person', 'In Person'); ?>">
                <span class="control__indicator d-flex justify-content-center align-items-center w-100 h-100 p-2"><?php echo houzez_option('spl_con_in_person', 'In Person'); ?></span>
                </label>
            </div>
            <!-- form-group -->
            <div class="form-group w-100">
                <label class="control control--radio ps-0">
                <input name="schedule_tour_type" type="radio" value="<?php echo houzez_option('spl_con_video_chat', 'Video Chat'); ?>">
                <span class="control__indicator d-flex justify-content-center align-items-center w-100 h-100 p-2"><?php echo houzez_option('spl_con_video_chat', 'Video Chat'); ?></span>
                </label>
            </div>
            <!-- form-group -->
        </div>
        <div class="form-group mb-2">
            <select name="schedule_time" class="selectpicker form-control bs-select-hidden" title="<?php echo houzez_option('spl_con_time', 'Choose a time'); ?>" data-live-search="false">
                <?php 
                $time_slots = explode(',', $schedule_time_slots); 
                foreach ($time_slots as $time) {
                    echo '<option value="'.trim($time).'">'.esc_attr($time).'</option>';
                }
                ?> 
            </select>
            <!-- selectpicker -->
        </div>
        <div class="form-group mb-2">
            <input class="form-control" name="name" placeholder="<?php echo houzez_option('spl_con_name', 'Name'); ?>" type="text">
        </div>

        <div class="form-group mb-2">
            <input class="form-control" name="phone" placeholder="<?php echo houzez_option('spl_con_phone', 'Phone'); ?>" type="text">
        </div>

        <div class="form-group mb-2">
            <input class="form-control" name="email" placeholder="<?php echo houzez_option('spl_con_email', 'Email'); ?>" type="email">
        </div>

        <div class="form-group form-group-textarea mb-2">
            <textarea class="form-control" name="message" rows="3" placeholder="<?php echo houzez_option('spl_con_message_plac', 'Message'); ?>"></textarea>
        </div>

        <?php do_action('houzez_schedule_tour_fields'); ?>
        
        <?php if( houzez_option('gdpr_and_terms_checkbox', 1) ) { ?>
        <div class="form-group form-group-terms mb-2">
            <label class="control control--checkbox d-flex align-items-start <?php if( $gdpr_checkbox ){ echo 'p-0 hz-no-gdpr-checkbox';}?>">
                <?php if( ! $gdpr_checkbox ) { ?>
                <input type="checkbox" name="privacy_policy">
                <span class="control__indicator"></span>
                <?php } ?>
                <div class="gdpr-text-wrap">
                    <?php echo houzez_option('spl_sub_agree', 'By submitting this form I agree to'); ?> <a target="_blank" href="<?php echo esc_url(get_permalink($terms_page_id)); ?>"><?php echo houzez_option('spl_term', 'Terms of Use'); ?></a>
                </div>
            </label>
        </div><!-- form-group -->
        <?php } ?>
        

        <?php do_action('houzez_after_property_schedule_tour_form_fields'); ?>

        <?php get_template_part('template-parts/captcha'); ?>
        <div class="form_messages"></div>

        <?php if ( $ipc_can_see_contact ) : ?>
        <button class="schedule_contact_form houzez-ele-button btn btn-secondary w-100">
            <?php get_template_part('template-parts/loader'); ?>
            <?php echo houzez_option('spl_btn_tour_sch', 'Submit a Tour Request'); ?> 
        </button>
        <?php else : ?>
        <button type="button" class="houzez-ele-button btn btn-secondary w-100 ipc-contact-locked" aria-disabled="true" data-ipc-message="<?php echo esc_attr( $ipc_is_logged_in ? $ipc_qualify_message : $ipc_login_message ); ?>"<?php echo $ipc_is_logged_in ? '' : ' data-bs-toggle="modal" data-bs-target="#login-register-form"'; ?>>
            <?php get_template_part('template-parts/loader'); ?>
            <?php echo houzez_option('spl_btn_tour_sch', 'Submit a Tour Request'); ?>
        </button>
        <?php endif; ?>
    </div>
</form>
<?php } 
}?>
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
