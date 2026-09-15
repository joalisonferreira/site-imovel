<?php 
global $property_data, $is_multi_steps, $hide_prop_fields; 

$gallery_image_req = houzez_option('gallery_image_req', 1);

?>
<div id="media" data-gallery-reg="<?php echo esc_attr($gallery_image_req); ?>" class="form-step-gal <?php echo esc_attr($is_multi_steps);?>">
    
    <div class="block-wrap">
        <div class="block-title-wrap d-flex justify-content-between align-items-center">
            <h2><?php echo houzez_option('cls_media', 'Property Media'); ?></h2>
        </div>
        <div class="block-content-wrap">
            
                <div class="upload-media-gallery mb-3">
                    <div id="houzez_property_gallery_container" class="row g-2">
    
                        <?php
                        $property_images_count = 0;
                        if(houzez_edit_property()) {
                            $property_images = get_post_meta( $property_data->ID, 'fave_property_images', false );

                            if ( ! empty( $property_images ) ) {
                                $property_images_count = count( array_filter( $property_images ) );
                            }
        

                            $featured_image_id = get_post_thumbnail_id( $property_data->ID );
                            $property_images[] = $featured_image_id;
                            $property_images = array_unique($property_images);

                            if( !empty($property_images[0])) {
                                foreach ($property_images as $prop_image_id) {

                                    $is_featured_image = ($featured_image_id == $prop_image_id);
                                    $featured_icon = ($is_featured_image) ? 'text-success' : '';

                                    $img_available = wp_get_attachment_image($prop_image_id, 'thumbnail');

                                    if( !empty($img_available)) {
                                        echo '<div class="col-md-2 col-sm-4 col-6 property-thumb" role="gridcell">';
                                        echo wp_get_attachment_image($prop_image_id, 'houzez-item-image-6', false, array('class' => 'img-fluid'));
                                        echo '<div class="upload-gallery-thumb-buttons d-flex justify-content-between align-items-center">';
                                            echo '<button class="icon-fav icon-featured" aria-label="Set as cover image" data-property-id="' . intval($property_data->ID) . '" data-attachment-id="' . intval($prop_image_id) . '"><i class="houzez-icon icon-rating-star full-star '.esc_attr($featured_icon).'" aria-hidden="true"></i></button>';

                                            echo '<button class="icon-delete" aria-label="Remove image" data-property-id="' . intval($property_data->ID) . '" data-attachment-id="' . intval($prop_image_id) . '"><span class="houzez-loader-js houzez-hidden spinner-border spinner-border-sm"></span><i class="houzez-icon icon-remove-circle" aria-hidden="true"></i></button>';
                                        echo '</div>';

                                        echo '<input type="hidden" class="propperty-image-id" name="propperty_image_ids[]" value="' . intval($prop_image_id) . '"/>';

                                        if ($is_featured_image) {
                                            echo '<input type="hidden" class="featured_image_id" name="featured_image_id" value="' . intval($prop_image_id) . '">';
                                        }
                                        
                                        echo '</div>';
                                    }
                                }
                            }
                        }
                        ?>
                    </div>
                </div>

            <p class="mb-3"><?php echo houzez_option('cl_drag_drop_text_image', 'Drag and drop the images to customize the image gallery order.'); ?></p>
            <div class="upload-property-media">
                <div id="houzez_gallery_dragDrop" class="media-drag-drop p-4 mb-4">
                    <div class="upload-icon mb-4">
                        <i class="houzez-icon icon-picture-sun" aria-hidden="true"></i>
                    </div>
                    <div class="upload-image-counter" aria-live="polite"><span class="uploaded"><?php echo esc_attr($property_images_count); ?></span> / <?php echo houzez_option('max_prop_images'); ?></div>
                    <div class="mb-3">
                        <?php echo houzez_option('cl_drag_drop_title', 'Drag and drop the gallery images here'); ?><br>
                        <span><?php echo houzez_option('cl_image_size', '(Minimum size 1440x900)'); ?></span><br>
                        <span><?php echo houzez_option('cl_image_featured', 'To mark an image as featured, click the star icon. If no image is marked as featured, the first image will be considered the featured image.'); ?></span>
                    </div>
                    <a id="select_gallery_images" href="javascript:;" class="btn btn-primary"><i class="houzez-icon icon-upload-button me-1"></i> <?php echo houzez_option('cl_image_btn', 'Select and Upload'); ?></a>
                </div>
                <div id="houzez_errors"></div>
                <div class="max-limit-error"><?php echo houzez_option('cl_max_limit_error', 'The maximum file upload limit has been reached.'); ?></div>
            </div>
        </div><!-- block-content-wrap -->
    </div><!-- block-wrap -->

    <?php if ( class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario() ) : ?>
        <?php get_template_part( 'template-parts/dashboard/submit/owner-documents' ); ?>
    <?php endif; ?>

    <?php if( $hide_prop_fields['video_url'] != 1 ) { ?>
        <div class="block-wrap">
            <div class="block-title-wrap d-flex justify-content-between align-items-center">
                <h2><?php echo houzez_option('cls_video', 'Video'); ?></h2>
            </div>
            <div class="block-content-wrap">
                <?php get_template_part('template-parts/dashboard/submit/form-fields/video'); ?>
            </div><!-- block-content-wrap -->
        </div><!-- block-wrap -->
    <?php } ?>

</div><!-- #media -->
