<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Speed up property photo uploads.
 *
 * - JPEG quality 82 instead of the 100 forced by the theme (3-5x smaller
 *   files, faster encode/write).
 * - Cap originals at 2560px (big_image_size_threshold) so thumbnail
 *   generation and watermarking don't process 12-48MP phone photos.
 * - Client-side downscale (1920px, q82) injected into the Houzez gallery
 *   plupload instance right after the plupload script tag, before any
 *   uploader is constructed.
 */
class Imovel_Parceiro_Upload_Performance {

    const JPEG_QUALITY = 82;
    const MAX_ORIGINAL_DIMENSION = 2560;
    const CLIENT_MAX_DIMENSION = 1920;
    const CLIENT_QUALITY = 82;

    public function __construct() {
        add_filter( 'houzez_jpeg_quality', array( $this, 'jpeg_quality' ) );
        add_filter( 'big_image_size_threshold', array( $this, 'big_image_threshold' ), 999 );
        add_filter( 'script_loader_tag', array( $this, 'inject_plupload_resize' ), 10, 2 );
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
