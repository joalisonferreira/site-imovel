<?php
// Child override of houzez/template-parts/realtors/agent/image.php.
// Sem foto: avatar genérico (sem o placeholder cinza do Houzez).
$image_size = function_exists( 'houzez_get_image_size_for' ) ? houzez_get_image_size_for( 'agent_profile' ) : 'thumbnail';
if ( function_exists( 'houzez_child_agent_avatar' ) ) {
    houzez_child_agent_avatar( 0, $image_size );
} elseif ( has_post_thumbnail() && get_the_post_thumbnail() != '' ) {
    the_post_thumbnail( $image_size, array( 'class' => 'img-fluid' ) );
}
