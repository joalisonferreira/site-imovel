<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Formatação pt-BR com R$ nos overviews do Elementor (v1/v2).
 *
 * Substitui os widgets originais por subclasses de mesmo nome, de modo que
 * layouts já montados no Elementor continuem funcionando sem reedição.
 */
class Imovel_Parceiro_Overview_Prices {

    public function __construct() {
        add_action( 'elementor/widgets/register', array( $this, 'replace_widgets' ), 20 );
    }

    public function replace_widgets( $widgets_manager ) {
        if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'unregister' ) || ! method_exists( $widgets_manager, 'register' ) ) {
            return;
        }
        if ( ! class_exists( 'Elementor\Property_Overview' ) || ! class_exists( 'Elementor\Property_Overview_v2' ) ) {
            return;
        }

        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-elementor-overview-widgets.php';

        if ( class_exists( 'Imovel_Parceiro_Overview_V1' ) ) {
            $widgets_manager->unregister( 'houzez-property-overview' );
            $widgets_manager->register( new Imovel_Parceiro_Overview_V1() );
        }
        if ( class_exists( 'Imovel_Parceiro_Overview_V2' ) ) {
            $widgets_manager->unregister( 'houzez-property-overview-v2' );
            $widgets_manager->register( new Imovel_Parceiro_Overview_V2() );
        }
    }

    /**
     * Troca o valor cru pelo formato pt-BR com R$ no HTML gerado pelo pai.
     *
     * @param array  $item Widget repeater item (contém field_type).
     * @param string $html HTML do método pai.
     * @return string
     */
    public static function replace_meta_value_in_html( $item, $html ) {
        $html = (string) $html;
        if ( '' === $html || ! is_array( $item ) || empty( $item['field_type'] ) || ! function_exists( 'get_post_meta' ) ) {
            return $html;
        }

        global $post;
        if ( ! $post instanceof WP_Post ) {
            return $html;
        }

        $raw = get_post_meta( $post->ID, 'fave_' . $item['field_type'], false );
        if ( is_array( $raw ) ) {
            $raw = implode( ', ', $raw );
        }

        $formatted = self::format_meta_value( $item['field_type'], $raw );
        if ( (string) $formatted === (string) $raw ) {
            return $html;
        }

        $needle = '<strong>' . esc_attr( $raw ) . '</strong>';
        $pos = strpos( $html, $needle );
        if ( false === $pos ) {
            return $html;
        }

        return substr_replace( $html, '<strong>' . esc_attr( $formatted ) . '</strong>', $pos, strlen( $needle ) );
    }

    /**
     * Formata valores monetários canônicos (1600.00) para pt-BR com R$.
     *
     * @param string $meta_field Field id (ex.: valor-do-condominio).
     * @param mixed  $value      Valor bruto.
     * @return mixed
     */
    public static function format_meta_value( $meta_field, $value ) {
        if ( ! is_scalar( $value ) ) {
            return $value;
        }
        $text = trim( (string) $value );
        if ( '' === $text || ! preg_match( '/^\d+(\.\d{1,2})?$/', $text ) ) {
            return $value;
        }
        if ( ! preg_match( '/(price|preco|condominio|iptu)/', strtolower( (string) $meta_field ) ) ) {
            return $value;
        }
        return 'R$' . number_format( (float) $text, 2, ',', '.' );
    }
}

new Imovel_Parceiro_Overview_Prices();
