<?php
/**
 * User Verification Main Page - Child Override (sticky approved)
 */

global $verification_data, $document_types, $apply_verification_link, $cancel_verification_link;

// Get user ID and verification data
$user_id = get_current_user_id();
if (isset($_GET['edit_user']) && !empty($_GET['edit_user']) && current_user_can('edit_users')) {
    $user_id = intval($_GET['edit_user']);
}

// Get verification status and history
$verification_status = $GLOBALS['houzez_user_verification']->get_verification_status($user_id);
$verification_data = $GLOBALS['houzez_user_verification']->get_verification_data($user_id);
$document_types = $GLOBALS['houzez_user_verification']->get_document_type($user_id);

$profile_link = houzez_get_template_link_2('template/user_dashboard_profile.php');

$apply_verification_link = add_query_arg( 'apply', 'true', add_query_arg( 'hpage', 'verification', $profile_link ) );
$cancel_verification_link = add_query_arg( 'hpage', 'verification', $profile_link );

// Sticky approved: nunca oferecer o form quando já verificado, mesmo via ?apply=true
if ('approved' === $verification_status && isset($_GET['apply'])) {
    wp_safe_redirect($cancel_verification_link);
    exit;
}

// Set status variables for display
$status_text = '';
$status_class = '';
$badge_class = '';
$show_form = false;

switch ($verification_status) {
    case 'pending':
        $status_text = esc_html__('Pending', 'houzez');
        $status_class = 'text-warning';
        $badge_class = 'bg-warning';
        break;
    case 'approved':
        $status_text = esc_html__('Verified', 'houzez');
        $status_class = 'text-success';
        $badge_class = 'bg-success';
        break;
    case 'rejected':
        $status_text = esc_html__('Rejected', 'houzez');
        $status_class = 'text-danger';
        $badge_class = 'bg-danger';
        $show_form = true;
        break;
    case 'additional_info_required':
        $status_text = esc_html__('Additional Info Required', 'houzez');
        $status_class = 'text-warning';
        $badge_class = 'bg-warning';
        $show_form = true;
        break;
    default:
        $status_text = esc_html__('Not Verified', 'houzez');
        $status_class = 'text-muted';
        $badge_class = 'bg-info';
        $show_form = true;
        break;
}
?>

<div class="heading d-flex align-items-center justify-content-between">
    <div class="heading-text">
        <h2><?php esc_html_e('Account Verification', 'houzez'); ?></h2>
        <p><?php esc_html_e('Verify your identity to build trust with potential clients', 'houzez'); ?></p>
    </div>

    <div class="heading-btn">

        <?php if( !isset( $_GET['apply'] ) ): ?>
            <a href="<?php echo esc_url($profile_link); ?>" class="btn btn-primary-outlined">
            <i class="houzez-icon icon-arrow-left-1 me-1"></i>
            <?php esc_html_e('Back to Profile', 'houzez'); ?>
        </a>
        <?php else: ?>
            <a href="<?php echo esc_url($cancel_verification_link); ?>" class="btn btn-primary-outlined">
                <i class="houzez-icon icon-arrow-left-1 me-1"></i>
                <?php esc_html_e('Back', 'houzez'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="propertie-list">
    <ul class="d-flex align-items-center" id="verificationTabs" role="tablist">
        <li role="presentation">
            <a class="active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status-tab-pane" type="button" role="tab" aria-controls="status-tab-pane" aria-selected="true"><?php esc_html_e('Status', 'houzez'); ?></a>
        </li>
        <li role="presentation">
            <a class="" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-tab-pane" type="button" role="tab" aria-controls="history-tab-pane" aria-selected="false"><?php esc_html_e('History', 'houzez'); ?></a>
        </li>
    </ul>
</div>

<div class="tab-content">   
    <div class="tab-pane fade show active" id="status-tab-pane" role="tabpanel" aria-labelledby="status-tab">
    <div class="row">
        <div class="col-md-12 col-lg-8">
            <div id="status" class="block-wrap mt-0 mb-4">
                <div class="block-title-wrap d-flex justify-content-between align-items-center">
                    <?php if( !isset( $_GET['apply'] ) ): ?>
                        <h2><?php esc_html_e('Verification Status', 'houzez'); ?></h2>
                        <span class="badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status_text); ?></span>
                    <?php else: ?>
                        <h2><?php esc_html_e('Verification Application', 'houzez'); ?></h2>
                    <?php endif; ?>
                    
        </div>
        
                <div class="block-content-wrap">
                        <?php 
                        if ($verification_status === 'pending') {
                            get_template_part('template-parts/dashboard/verification/pending');
                        } elseif ($verification_status === 'approved') {
                            get_template_part('template-parts/dashboard/verification/approved');
                        } elseif ($verification_status === 'additional_info_required') {
                            get_template_part('template-parts/dashboard/verification/additional-information');
                        } elseif ($verification_status === 'rejected' && !isset( $_GET['apply'] )) {
                            get_template_part('template-parts/dashboard/verification/rejected'); 
                        } else {
                            get_template_part('template-parts/dashboard/verification/apply');
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12 col-lg-4">
                <?php get_template_part('template-parts/dashboard/verification/information-cards'); ?>
        </div>

        </div> <!-- row -->
    </div>

    <div class="tab-pane fade" id="history-tab-pane" role="tabpanel" aria-labelledby="history-tab">
       <?php get_template_part('template-parts/dashboard/verification/history'); ?>
    </div>

</div>



<style>

.verification-agreement {
    border-left: 4px solid #00aeff !important;
    transition: all 0.3s ease;
}

.verification-agreement:hover {
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
}

.verification-agreement .terms-content {
    max-height: 200px;
    overflow-y: auto;
    padding-right: 10px;
}

.verification-agreement .terms-content::-webkit-scrollbar {
    width: 6px;
}

.verification-agreement .terms-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.verification-agreement .terms-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

.verification-agreement .terms-content::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

.form-check .required {
    color: #dc3545;
}

.verification-error {
    display: block;
    font-size: 14px;
}


.file-help-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.benefit-icon-wrapper {
    display: flex;
    min-width: 24px;
    margin-top: 2px;
}
.verification-benefit-icon {
    font-size: 18px;
    color: #00aeff;
}

.verification-agreement .terms-content ul {
    list-style-type: none;
    padding-left: 0;
}

.verification-agreement .terms-content ul li {
    position: relative;
    padding-left: 1.5rem;
}

.verification-agreement .terms-content ul li:before {
    content: "•";
    position: absolute;
    left: 0;
    color: #00aeff;
    font-size: 1.2rem;
    line-height: 1.2;
}

/* Additional Info Timeline */
.timeline {
    position: relative;
    padding-left: 3rem;
    margin: 0 0 0 1rem;
    color: #565656;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 0.15rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e5e5;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    left: -1.38rem;
    top: 0.25rem;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
}

.timeline-title {
    font-size: 16px;
    margin-bottom: 0.25rem;
}

.admin-message {
    border-left: 4px solid #00aeff;
}

.additional-info-request-section {
    max-width: 800px;
}

#additional-info-messages {
    margin: 1rem 0;
}
</style>
