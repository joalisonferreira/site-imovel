<?php
// Child override of houzez/template-parts/realtors/agent/agent-item.php.
// Única diferença em relação ao pai: sem foto usa o avatar genérico.
global $post, $houzez_local;
$des = get_post_meta($post->ID, 'fave_agent_des', true);
$position = get_post_meta($post->ID, 'fave_agent_position', true);
$company = get_post_meta($post->ID, 'fave_agent_company', true);
$logo_id = get_post_meta($post->ID, 'fave_agent_logo', true);
?>
<div class="agent-grid-wrap agent-item d-flex flex-column align-items-center text-center">
	<div class="agent-grid-image-wrap mb-3">
		<a class="agent-grid-image d-block" href="<?php the_permalink(); ?>">
			<?php
			if ( function_exists( 'houzez_child_agent_avatar' ) ) {
				houzez_child_agent_avatar( $post->ID, 'thumbnail', 'img-fluid rounded-circle' );
			} elseif ( has_post_thumbnail() && get_the_post_thumbnail() != '' ) {
		        the_post_thumbnail( 'thumbnail', array('class' => 'img-fluid rounded-circle') );
		    } else {
		        houzez_image_placeholder( 'thumbnail' );
		    }
			?>
			<?php get_template_part('template-parts/realtors/agent/verified'); ?>
		</a>
	</div>

	<div class="agent-info">
		<div class="agent-name">
			<a href="<?php the_permalink(); ?>" class="text-decoration-none"><?php the_title(); ?></a>
		</div>

		<div class="agent-company">
			<?php if( !empty($position) || !empty($company) ) { ?>
                <?php echo esc_attr($position); ?>
                <?php if( !empty($company) ) { ?>
                , <?php echo esc_attr($company); ?>
                <?php } ?>
            <?php } ?>
		</div>
	</div>
	<div class="agent-body py-3">
		<?php echo houzez_get_excerpt(15); ?>
	</div>
	<div class="agent-link">
		<a href="<?php the_permalink(); ?>"><?php echo $houzez_local['view_profile']; ?></a>
	</div>
</div>
