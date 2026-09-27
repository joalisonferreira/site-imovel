<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Speed up property photo uploads.
 *
 * - JPEG/WebP quality 70 instead of the 100 forced by the theme (~25%
 *   smaller files, faster encode/write, sem perda visível).
 * - Cap originals at 2560px (big_image_size_threshold) so thumbnail
 *   generation and watermarking don't process 12-48MP phone photos.
 * - Client-side downscale (1920px, q70) injected into the Houzez gallery
 *   plupload instance right after the plupload script tag, before any
 *   uploader is constructed.
 * - Property gallery uploads skip unused sizes (100x100, 300x300, 600px e
 *   780x780): ~4 arquivos e ~150KB economizados por foto.
 */
class Imovel_Parceiro_Upload_Performance {

    const JPEG_QUALITY = 70;
    const MAX_ORIGINAL_DIMENSION = 2560;
    const CLIENT_MAX_DIMENSION = 1920;
    const CLIENT_QUALITY = 70;

    /**
     * Tamanhos gerados mas nunca usados nos layouts de imóvel.
     *
     * @var string[]
     */
    const SKIPPED_PROPERTY_SIZES = array(
        'woocommerce_gallery_thumbnail', // 100x100
        'woocommerce_thumbnail',         // 300x300
        'woocommerce_single',            // 600px proporcional
        'houzez-top-v7',                 // 780x780
    );

    /**
     * Quando true, remove os tamanhos acima de qualquer upload
     * (usado por rotinas de regeneração; fora delas, só uploads da
     * galeria de imóveis são afetados para não quebrar a loja).
     *
     * @var bool
     */
    public static $force_strip_sizes = false;

    public function __construct() {
        add_filter( 'houzez_jpeg_quality', array( $this, 'jpeg_quality' ) );
        add_filter( 'jpeg_quality', array( $this, 'jpeg_quality' ), 999 );
        add_filter( 'wp_editor_set_quality', array( $this, 'editor_quality' ), 999, 2 );
        add_filter( 'big_image_size_threshold', array( $this, 'big_image_threshold' ), 999 );
        add_filter( 'intermediate_image_sizes_advanced', array( $this, 'strip_unused_property_sizes' ), 999, 2 );
        add_filter( 'script_loader_tag', array( $this, 'inject_plupload_resize' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_friendly_errors' ), 20 );
    }

    /**
     * Mensagens de erro de upload amigáveis (PT-BR) no cadastro/edição.
     */
    public function enqueue_friendly_errors() {
        $is_dashboard = function_exists( 'houzez_is_dashboard' ) && houzez_is_dashboard();
        if ( ! $is_dashboard && ! is_singular( 'property' ) ) {
            return;
        }

        $file = IMOVEL_PARCEIRO_CORE_DIR . 'assets/js/upload-friendly-errors.js';
        if ( ! file_exists( $file ) ) {
            return;
        }

        wp_enqueue_script(
            'imovel-parceiro-upload-errors',
            IMOVEL_PARCEIRO_CORE_URL . 'assets/js/upload-friendly-errors.js',
            array(),
            (string) filemtime( $file ),
            true
        );
    }

    /**
     * Override the quality 100 forced by houzez_image_full_quality().
     *
     * @param int $quality Incoming quality.
     * @return int
     */
    public function jpeg_quality( $quality ) {
        return self::JPEG_QUALITY;
    }

    /**
     * Qualidade do editor (vale para JPEG e WebP) nos uploads.
     *
     * @param int    $quality Incoming quality.
     * @param string $mime    Mime type being generated.
     * @return int
     */
    public function editor_quality( $quality, $mime ) {
        if ( 0 === strpos( (string) $mime, 'image/' ) ) {
            return self::JPEG_QUALITY;
        }
        return $quality;
    }

    /**
     * Remove tamanhos nunca usados nos layouts de imóvel.
     *
     * Fora de regenerações forçadas, atua somente no upload da galeria
     * (houzez_property_img_upload) para não afetar a loja WooCommerce.
     *
     * @param array $sizes    Tamanhos que seriam gerados.
     * @param array $metadata Metadados do anexo.
     * @return array
     */
    public function strip_unused_property_sizes( $sizes, $metadata ) {
        if ( ! self::$force_strip_sizes ) {
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
            if ( 'houzez_property_img_upload' !== $action ) {
                return $sizes;
            }
        }

        foreach ( self::SKIPPED_PROPERTY_SIZES as $size ) {
            unset( $sizes[ $size ] );
        }

        return $sizes;
    }

    /**
     * Ensure monster originals are scaled down on upload.
     *
     * @param int|false $threshold Incoming threshold.
     * @return int
     */
    public function big_image_threshold( $threshold ) {
        return self::MAX_ORIGINAL_DIMENSION;
    }

    /**
     * Append a plupload patch right after the plupload script tag so gallery
     * photos are downscaled in the browser before upload.
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     * @return string
     */
    public function inject_plupload_resize( $tag, $handle ) {
        if ( 'plupload' !== $handle ) {
            return $tag;
        }

        static $injected = false;
        if ( $injected ) {
            return $tag;
        }
        $injected = true;

        $max = absint( self::CLIENT_MAX_DIMENSION );
        $quality = absint( self::CLIENT_QUALITY );

        $js = '(function(){'
            . 'try{'
            . 'if(!window.plupload||!plupload.Uploader||!plupload.Uploader.prototype){return;}'
            . 'if(plupload.Uploader.prototype.__ipcResizePatched){return;}'
            . 'plupload.Uploader.prototype.__ipcResizePatched=true;'
            . 'var origInit=plupload.Uploader.prototype.init;'
            . 'plupload.Uploader.prototype.init=function(){'
            . 'try{'
            . 'var url=this.settings&&this.settings.url?String(this.settings.url):"";'
            . 'if(url.indexOf("houzez_property_img_upload")!==-1&&!this.settings.resize){'
            . 'this.settings.resize={width:' . $max . ',height:' . $max . ',quality:' . $quality . ',crop:false};'
            . '}'
            . '}catch(e){}'
            . 'return origInit.apply(this,arguments);'
            . '};'
            . '}catch(e){}'
            . '})();';

        return $tag . '<script>' . $js . '</script>';
    }
}

new Imovel_Parceiro_Upload_Performance();
