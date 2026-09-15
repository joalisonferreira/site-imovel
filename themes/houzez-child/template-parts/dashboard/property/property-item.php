<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_proprietario = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario();

if ( ! $is_proprietario ) {
    include get_template_directory() . '/template-parts/dashboard/property/property-item.php';
    return;
}

global $post;
$post_id = get_the_ID();
$submit_link = houzez_dashboard_add_listing();
$edit_link = add_query_arg( 'edit_property', $post_id, $submit_link );
$property_status = get_post_status( $post_id );
$context = Imovel_Parceiro_Owner_Workflow::get_property_owner_context( $post_id, get_current_user_id() );

$status_badge = '';
if ( 'publish' === $property_status ) {
    $status_badge = '<span class="dashboard-label bg-success">' . esc_html__( 'Approved', 'houzez' ) . '</span>';
} elseif ( 'pending' === $property_status ) {
    $status_badge = '<span class="dashboard-label bg-warning">' . esc_html__( 'Pending', 'houzez' ) . '</span>';
} elseif ( 'draft' === $property_status ) {
    $status_badge = '<span class="dashboard-label bg-dark">' . esc_html__( 'Draft', 'houzez' ) . '</span>';
} elseif ( 'expired' === $property_status ) {
    $status_badge = '<span class="dashboard-label bg-danger">' . esc_html__( 'Expired', 'houzez' ) . '</span>';
}

$broker_name = ! empty( $context['broker_name'] ) ? $context['broker_name'] : '-';
$workflow_status = ! empty( $context['workflow_status'] ) ? $context['workflow_status'] : '-';
$doc_status = ! empty( $context['documentation_status'] ) ? $context['documentation_status'] : '-';
$approval_status = ! empty( $context['approval_status'] ) ? $context['approval_status'] : '-';
?>
<tr>
    <td data-label="<?php esc_html_e( 'Select', 'houzez' ); ?>">
        <label class="control control--checkbox">
            <input type="checkbox" class="control control--checkbox checkbox-delete listing-bulk-delete" name="listing-bulk-delete[]" value="<?php echo intval( $post_id ); ?>">
            <span class="control__indicator"></span>
        </label>
    </td>
    <td data-label="<?php echo esc_html__( 'Thumbnail', 'houzez' ); ?>" class="px-0">
        <div class="image-holder">
            <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
                <?php
                $thumbnail_size = 'thumbnail';
                if ( has_post_thumbnail() && get_the_post_thumbnail( $post_id ) ) {
                    the_post_thumbnail( $thumbnail_size );
                } else {
                    houzez_image_placeholder( $thumbnail_size );
                }
                ?>
            </a>
        </div>
    </td>
    <td data-label="<?php echo esc_html__( 'Title', 'houzez' ); ?>">
        <div class="text-box">
            <a class="fw-bold" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php the_title(); ?></a><br>
            <address class="mb-1"><?php echo houzez_get_listing_data( 'property_map_address' ); ?></address>
            <small class="d-block text-muted"><?php esc_html_e( 'Corretor responsável:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $broker_name ); ?></small>
            <small class="d-block text-muted"><?php esc_html_e( 'Fluxo:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $workflow_status ); ?></small>
            <small class="d-block text-muted"><?php esc_html_e( 'Documentação:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $doc_status ); ?></small>
            <small class="d-block text-muted"><?php esc_html_e( 'Aprovação:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $approval_status ); ?></small>
        </div>
    </td>
    <td data-label="<?php echo esc_html__( 'Status', 'houzez' ); ?>"><?php echo houzez_taxonomy_simple( 'property_status' ); ?></td>
    <td data-label="" class="px-2"><?php echo $status_badge; ?></td>
    <td data-label="<?php echo esc_html__( 'ID', 'houzez' ); ?>"><?php echo houzez_get_listing_data( 'property_id' ); ?></td>
    <td data-label="<?php echo esc_html__( 'Price', 'houzez' ); ?>"><?php houzez_property_price_admin(); ?></td>
    <td data-label="<?php echo esc_html__( 'Type', 'houzez' ); ?>" class="px-2"><?php echo houzez_taxonomy_simple( 'property_type' ); ?></td>
    <td data-label="<?php echo esc_html__( 'Date', 'houzez' ); ?>" class="px-2">
        <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ) . ' ' . date_i18n( get_option( 'time_format' ), strtotime( $post->post_date ) ) ); ?>
    </td>
    <td data-label="<?php echo esc_html__( 'Actions', 'houzez' ); ?>" class="text-lg-center text-start px-0">
        <div class="dropdown" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php echo esc_html__( 'Actions', 'houzez' ); ?>">
            <a href="javascript:void(0)" class="action-btn" data-bs-toggle="dropdown" aria-expanded="false"><i class="houzez-icon icon-navigation-menu-horizontal"></i></a>
            <ul class="dropdown-menu dropdown-menu3">
                <li>
                    <a class="dropdown-item" href="<?php echo esc_url( $edit_link ); ?>">
                        <i class="houzez-icon icon-pencil"></i> <?php esc_html_e( 'Edit', 'houzez' ); ?>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item imovel-owner-request-broker-change" href="javascript:void(0)" data-property-id="<?php echo esc_attr( $post_id ); ?>" data-property-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
                        <i class="houzez-icon icon-single-neutral-actions-text"></i> <?php esc_html_e( 'Solicitar troca de corretor', 'imovel-parceiro-core' ); ?>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item imovel-owner-request-delete" href="javascript:void(0)" data-property-id="<?php echo esc_attr( $post_id ); ?>">
                        <i class="houzez-icon icon-bin"></i> <?php esc_html_e( 'Solicitar exclusão', 'imovel-parceiro-core' ); ?>
                    </a>
                </li>
            </ul>
        </div>
    </td>
</tr>
