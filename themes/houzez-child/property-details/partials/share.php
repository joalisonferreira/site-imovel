<?php
/**
 * Override: menu "Compartilhar" do detalhe do imóvel.
 *
 * Baseado no parcial do tema pai (houzez/property-details/partials/share.php),
 * com duas diferenças:
 * 1. Link do WhatsApp usa wa.me (padrão do portal) em vez de api.whatsapp.com.
 * 2. Rótulos/aria-labels em PT-BR (regra AGENTS.md).
 */
global $post;
$image = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'large' );
$permalink = get_permalink();
$title = get_the_title();
$encoded_permalink = urlencode( $permalink );
$encoded_title = urlencode( $title );
$query_string = rawurlencode( $title . ' ' . $permalink );
?>

<div class="dropdown-item" role="menuitem"><?php echo esc_html__( 'Compartilhar:', 'imovel-parceiro-core' ); ?></div>

<a class="dropdown-item" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo $query_string; ?>" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar no WhatsApp', 'imovel-parceiro-core' ); ?>">
	<i class="houzez-icon icon-messaging-whatsapp me-1" aria-hidden="true"></i> <?php esc_html_e( 'WhatsApp', 'imovel-parceiro-core' ); ?>
</a>

<a class="dropdown-item" href="https://pinterest.com/pin/create/button/?url=<?php echo $encoded_permalink; ?>&amp;media=<?php echo ! empty( $image[0] ) ? urlencode( $image[0] ) : ''; ?>&amp;description=<?php echo $encoded_title; ?>" onclick="window.open(this.href, 'share_pinterest','left=50,top=50,width=600,height=350,toolbar=0'); return false;" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar no Pinterest', 'imovel-parceiro-core' ); ?>">
    <i class="houzez-icon icon-social-pinterest me-1" aria-hidden="true"></i> <?php echo esc_html__( 'Pinterest', 'imovel-parceiro-core' ); ?>
</a>

<a class="dropdown-item" href="https://www.facebook.com/sharer.php?u=<?php echo $encoded_permalink; ?>&amp;t=<?php echo $encoded_title; ?>" onclick="window.open(this.href, 'share_facebook','left=50,top=50,width=600,height=350,toolbar=0'); return false;" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar no Facebook', 'imovel-parceiro-core' ); ?>">
	<i class="houzez-icon icon-social-media-facebook me-1" aria-hidden="true"></i> <?php esc_html_e( 'Facebook', 'imovel-parceiro-core' ); ?>
</a>

<a class="dropdown-item" href="https://twitter.com/intent/tweet?text=<?php echo $encoded_title; ?>&url=<?php echo $encoded_permalink; ?>" onclick="window.open(this.href, 'share_twitter','left=50,top=50,width=600,height=350,toolbar=0'); return false;" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar no X', 'imovel-parceiro-core' ); ?>">
	<i class="houzez-icon icon-x-logo-twitter-logo-2 me-1" aria-hidden="true"></i> <?php esc_html_e( 'X', 'imovel-parceiro-core' ); ?>
</a>

<a class="dropdown-item" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $encoded_permalink; ?>&title=<?php echo $encoded_title; ?>&source=<?php echo urlencode( home_url( '/' ) ); ?>" onclick="window.open(this.href, 'share_linkedin','left=50,top=50,width=600,height=350,toolbar=0'); return false;" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar no LinkedIn', 'imovel-parceiro-core' ); ?>">
	<i class="houzez-icon icon-professional-network-linkedin me-1" aria-hidden="true"></i> <?php esc_html_e( 'LinkedIn', 'imovel-parceiro-core' ); ?>
</a>

<a class="dropdown-item" href="mailto:?subject=<?php echo $encoded_title; ?>&body=<?php echo $encoded_permalink; ?>" role="menuitem" aria-label="<?php esc_attr_e( 'Compartilhar por e-mail', 'imovel-parceiro-core' ); ?>">
	<i class="houzez-icon icon-envelope me-1" aria-hidden="true"></i> <?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?>
</a>
