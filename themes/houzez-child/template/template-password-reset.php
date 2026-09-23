<?php
/**
 * Template Name: Password Reset
 *
 * Página alternativa de redefinição de senha (shortcode [ipc_password_reset]).
 */

get_header();
?>

<section class="frontend-submission-page">
    <div class="ipc-reset-page">
        <div class="ipc-reset-wrap">
            <?php echo do_shortcode( '[ipc_password_reset]' ); ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
