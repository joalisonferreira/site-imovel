<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Watermark {
    const OPTION_KEY = 'imovel_parceiro_watermark_settings';
    const OPTION_STATUS = 'imovel_parceiro_watermark_last_status';
    const META_PROCESSED = '_imovel_parceiro_watermark_processed';
    const META_HASH = '_imovel_parceiro_watermark_hash';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'handle_dashboard_request' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'maybe_process_on_metadata' ), 50, 2 );
        add_action( 'added_post_meta', array( $this, 'maybe_process_on_property_gallery_meta' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'maybe_process_on_property_gallery_meta' ), 10, 4 );
    }

    public static function get_settings() {
        $defaults = array(
            'enabled' => 0,
            'attachment_id' => 0,
            'position' => 'bottom-right',
            'opacity' => 60,
            'size_percent' => 20,
        );

        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings = wp_parse_args( $settings, $defaults );
        $settings['enabled'] = (int) ! empty( $settings['enabled'] );
        $settings['attachment_id'] = absint( $settings['attachment_id'] );
        $settings['position'] = self::sanitize_position( $settings['position'] );
        $settings['opacity'] = max( 5, min( 100, (int) $settings['opacity'] ) );
        $settings['size_percent'] = max( 5, min( 80, (int) $settings['size_percent'] ) );

        return $settings;
    }

    public function handle_dashboard_request() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( ! isset( $_POST['imovel_watermark_action'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_imovel_watermark_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_imovel_watermark_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'imovel_watermark_update' ) ) {
            wp_die( esc_html__( 'Operação não autorizada.', 'imovel-parceiro-core' ) );
        }

        $action = sanitize_key( wp_unslash( $_POST['imovel_watermark_action'] ) );
        $settings = self::get_settings();

        if ( 'save' === $action ) {
            $settings['enabled'] = ! empty( $_POST['imovel_watermark_enabled'] ) ? 1 : 0;
            $settings['position'] = isset( $_POST['imovel_watermark_position'] ) ? self::sanitize_position( wp_unslash( $_POST['imovel_watermark_position'] ) ) : 'bottom-right';
            $settings['opacity'] = isset( $_POST['imovel_watermark_opacity'] ) ? max( 5, min( 100, absint( wp_unslash( $_POST['imovel_watermark_opacity'] ) ) ) ) : 60;
            $settings['size_percent'] = isset( $_POST['imovel_watermark_size'] ) ? max( 5, min( 80, absint( wp_unslash( $_POST['imovel_watermark_size'] ) ) ) ) : 20;

            if ( ! empty( $_FILES['imovel_watermark_file'] ) && ! empty( $_FILES['imovel_watermark_file']['name'] ) ) {
                $attachment_id = $this->handle_watermark_upload( $_FILES['imovel_watermark_file'] );
                if ( is_wp_error( $attachment_id ) ) {
                    $this->redirect_with_notice( 'error', $attachment_id->get_error_message() );
                }
                $settings['attachment_id'] = $attachment_id;
            }

            update_option( self::OPTION_KEY, $settings, false );
            $this->redirect_with_notice( 'updated', __( 'Configuração da marca d\'água salva.', 'imovel-parceiro-core' ) );
        }

        if ( 'remove' === $action ) {
            $settings['attachment_id'] = 0;
            update_option( self::OPTION_KEY, $settings, false );
            $this->redirect_with_notice( 'updated', __( 'Imagem da marca d\'água removida.', 'imovel-parceiro-core' ) );
        }
    }

    public static function render_dashboard_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();
        $watermark_url = $settings['attachment_id'] ? wp_get_attachment_url( $settings['attachment_id'] ) : '';

        $sample_property = get_posts(
            array(
                'post_type' => 'property',
                'posts_per_page' => 1,
                'post_status' => array( 'publish', 'pending', 'draft', 'private' ),
                'meta_key' => 'fave_property_images',
            )
        );

        $sample_image_url = '';
        if ( ! empty( $sample_property ) ) {
            $sample_ids = get_post_meta( $sample_property[0]->ID, 'fave_property_images' );
            if ( ! empty( $sample_ids ) ) {
                $sample_image_url = wp_get_attachment_image_url( absint( $sample_ids[0] ), 'large' );
            }
        }

        $notice_type = isset( $_GET['imovel_wm_notice'] ) ? sanitize_key( wp_unslash( $_GET['imovel_wm_notice'] ) ) : '';
        $notice_text = isset( $_GET['imovel_wm_message'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_wm_message'] ) ) : '';
        ?>
        <div class="imovel-parceiro-admin-section" style="padding:16px; border:1px solid #e6e6e6; border-radius:10px; background:#fff; margin-top:16px;">
            <h4 style="margin:0 0 10px;"><?php esc_html_e( 'Marca d\'água', 'imovel-parceiro-core' ); ?></h4>
            <p style="margin:0 0 14px; color:#666;"><?php esc_html_e( 'Configure a marca d\'água para aplicar automaticamente em novas fotos de imóveis.', 'imovel-parceiro-core' ); ?></p>

            <?php if ( $notice_type && $notice_text ) : ?>
                <div style="margin-bottom:12px; padding:10px 12px; border-radius:8px; border:1px solid <?php echo 'updated' === $notice_type ? '#badbcc' : '#f5c2c7'; ?>; background:<?php echo 'updated' === $notice_type ? '#d1e7dd' : '#f8d7da'; ?>; color:<?php echo 'updated' === $notice_type ? '#0f5132' : '#842029'; ?>;">
                    <?php echo esc_html( $notice_text ); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'imovel_watermark_update', '_imovel_watermark_nonce' ); ?>
                <input type="hidden" name="imovel_watermark_action" value="save" />

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Ativar função', 'imovel-parceiro-core' ); ?></label>
                        <label class="control control--checkbox" style="font-weight:400;">
                            <input type="checkbox" name="imovel_watermark_enabled" value="1" <?php checked( (int) $settings['enabled'], 1 ); ?> />
                            <?php esc_html_e( 'Aplicar automaticamente em novos uploads de imóveis', 'imovel-parceiro-core' ); ?>
                            <span class="control__indicator"></span>
                        </label>
                    </div>

                    <div>
                        <label for="imovel_watermark_position" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Posição', 'imovel-parceiro-core' ); ?></label>
                        <select id="imovel_watermark_position" name="imovel_watermark_position" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
                            <?php
                            $positions = array(
                                'top-left' => __( 'Superior esquerda', 'imovel-parceiro-core' ),
                                'top-center' => __( 'Superior central', 'imovel-parceiro-core' ),
                                'top-right' => __( 'Superior direita', 'imovel-parceiro-core' ),
                                'center' => __( 'Centro', 'imovel-parceiro-core' ),
                                'bottom-left' => __( 'Inferior esquerda', 'imovel-parceiro-core' ),
                                'bottom-center' => __( 'Inferior central', 'imovel-parceiro-core' ),
                                'bottom-right' => __( 'Inferior direita', 'imovel-parceiro-core' ),
                            );
                            foreach ( $positions as $key => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['position'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="imovel_watermark_opacity" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Opacidade (%)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_watermark_opacity" name="imovel_watermark_opacity" min="5" max="100" value="<?php echo esc_attr( $settings['opacity'] ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>

                    <div>
                        <label for="imovel_watermark_size" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Tamanho (% da largura)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_watermark_size" name="imovel_watermark_size" min="5" max="80" value="<?php echo esc_attr( $settings['size_percent'] ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; margin-bottom:14px;">
                    <div>
                        <label for="imovel_watermark_file" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Upload da marca d\'água', 'imovel-parceiro-core' ); ?></label>
                        <input type="file" id="imovel_watermark_file" name="imovel_watermark_file" accept="image/png,image/jpeg,image/webp" style="width:100%;" />
                        <p style="margin:8px 0 0; font-size:12px; color:#666;"><?php esc_html_e( 'Use preferencialmente PNG com fundo transparente.', 'imovel-parceiro-core' ); ?></p>
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Imagem atual', 'imovel-parceiro-core' ); ?></label>
                        <?php if ( $watermark_url ) : ?>
                            <div style="padding:10px; border:1px solid #ececec; border-radius:8px; background:#f8f9fa;">
                                <img src="<?php echo esc_url( $watermark_url ); ?>" alt="" style="max-width:100%; height:auto;" />
                            </div>
                        <?php else : ?>
                            <p style="margin:0; color:#777;"><?php esc_html_e( 'Nenhuma marca d\'água configurada.', 'imovel-parceiro-core' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Salvar configuração', 'imovel-parceiro-core' ); ?></button>
                    <?php if ( $settings['attachment_id'] ) : ?>
                        <button type="submit" class="btn btn-outline-danger" name="imovel_watermark_action" value="remove"><?php esc_html_e( 'Remover imagem', 'imovel-parceiro-core' ); ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <div style="margin-top:18px;">
                <h5 style="margin:0 0 8px;"><?php esc_html_e( 'Prévia', 'imovel-parceiro-core' ); ?></h5>
                <div style="position:relative; max-width:560px; border:1px solid #e6e6e6; border-radius:8px; overflow:hidden; background:#f3f3f3; min-height:220px;">
                    <?php if ( $sample_image_url ) : ?>
                        <img src="<?php echo esc_url( $sample_image_url ); ?>" alt="" style="display:block; width:100%; height:auto;" />
                    <?php else : ?>
                        <div style="padding:24px; color:#777;"><?php esc_html_e( 'Sem imagem de imóvel disponível para prévia.', 'imovel-parceiro-core' ); ?></div>
                    <?php endif; ?>

                    <?php if ( $watermark_url ) : ?>
                        <?php
                        $position_style = self::preview_position_style( $settings['position'] );
                        $size = max( 5, min( 80, (int) $settings['size_percent'] ) );
                        ?>
                        <img src="<?php echo esc_url( $watermark_url ); ?>" alt="" style="position:absolute; <?php echo esc_attr( $position_style ); ?> width:<?php echo esc_attr( $size ); ?>%; opacity:<?php echo esc_attr( (float) $settings['opacity'] / 100 ); ?>; pointer-events:none;" />
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function maybe_process_on_metadata( $metadata, $attachment_id ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id || ! is_array( $metadata ) ) {
            return $metadata;
        }

        if ( ! $this->is_watermark_enabled() ) {
            return $metadata;
        }

        // Gallery uploads arrive with post_parent = 0 (the property link is
        // created later via fave_property_images), so also accept uploads
        // coming from the Houzez property image endpoint.
        if ( ! $this->is_property_image_attachment( $attachment_id ) && ! $this->is_property_gallery_upload_request() ) {
            return $metadata;
        }

        $this->process_attachment_watermark( $attachment_id, $metadata );

        return $metadata;
    }

    /**
     * Whether the current request is the Houzez property gallery upload.
     *
     * @return bool
     */
    private function is_property_gallery_upload_request() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            return false;
        }

        if ( ! isset( $_REQUEST['action'] ) ) {
            return false;
        }

        $action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );

        return 'houzez_property_img_upload' === $action;
    }

    public function maybe_process_on_property_gallery_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( 'fave_property_images' !== $meta_key ) {
            return;
        }

        if ( ! $this->is_watermark_enabled() ) {
            return;
        }

        $property_id = absint( $object_id );
        if ( 'property' !== get_post_type( $property_id ) ) {
            return;
        }

        $attachment_id = absint( $meta_value );
        if ( ! $attachment_id ) {
            return;
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) ) {
            return;
        }

        $this->process_attachment_watermark( $attachment_id, $metadata );
    }

    private function is_watermark_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['enabled'] ) && ! empty( $settings['attachment_id'] );
    }

    private function is_property_image_attachment( $attachment_id ) {
        if ( 'attachment' !== get_post_type( $attachment_id ) ) {
            return false;
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return false;
        }

        $parent_id = (int) wp_get_post_parent_id( $attachment_id );
        if ( $parent_id > 0 && 'property' === get_post_type( $parent_id ) ) {
            return true;
        }

        return false;
    }

    private function process_attachment_watermark( $attachment_id, $metadata ) {
        $processed = (int) get_post_meta( $attachment_id, self::META_PROCESSED, true );
        if ( 1 === $processed ) {
            return;
        }

        $settings = self::get_settings();
        $watermark_attachment_id = absint( $settings['attachment_id'] );
        if ( ! $watermark_attachment_id || $watermark_attachment_id === $attachment_id ) {
            return;
        }

        $watermark_path = get_attached_file( $watermark_attachment_id );
        if ( empty( $watermark_path ) || ! file_exists( $watermark_path ) ) {
            return;
        }

        $original_path = get_attached_file( $attachment_id );
        if ( empty( $original_path ) || ! file_exists( $original_path ) ) {
            return;
        }

        $target_files = array( $original_path );

        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            $dir = trailingslashit( dirname( $original_path ) );
            foreach ( $metadata['sizes'] as $size_data ) {
                if ( empty( $size_data['file'] ) ) {
                    continue;
                }
                $size_path = $dir . $size_data['file'];
                if ( file_exists( $size_path ) ) {
                    $target_files[] = $size_path;
                }
            }
        }

        $target_files = array_values( array_unique( $target_files ) );
        $any_processed = false;
        foreach ( $target_files as $target_file ) {
            if ( $this->apply_watermark_to_file( $target_file, $watermark_path, $settings ) ) {
                $any_processed = true;
            } else {
                error_log( sprintf( 'Imovel Parceiro watermark: failed to process attachment %d (%s).', $attachment_id, $target_file ) );
            }
        }

        if ( $any_processed ) {
            update_post_meta( $attachment_id, self::META_PROCESSED, 1 );
            $hash = @md5_file( $original_path );
            if ( $hash ) {
                update_post_meta( $attachment_id, self::META_HASH, $hash );
            }
            self::record_status( true, $attachment_id, '' );
        } else {
            self::record_status( false, $attachment_id, __( 'Falha ao aplicar a marca d\'água. Verifique se o PHP tem GD ou Imagick e se a pasta de uploads permite escrita.', 'imovel-parceiro-core' ) );
        }
    }

    /**
     * Last processing status (shown in the dashboard section).
     *
     * @return array
     */
    public static function get_last_status() {
        $status = get_option( self::OPTION_STATUS, array() );

        return is_array( $status ) ? $status : array();
    }

    /**
     * Persist the last processing outcome for dashboard diagnosis.
     *
     * @param bool $ok            Whether at least one file was processed.
     * @param int  $attachment_id Attachment involved.
     * @param string $message     Error message (empty on success).
     */
    private static function record_status( $ok, $attachment_id, $message ) {
        update_option(
            self::OPTION_STATUS,
            array(
                'ok' => $ok ? 1 : 0,
                'attachment_id' => absint( $attachment_id ),
                'message' => (string) $message,
                'time' => current_time( 'mysql' ),
            ),
            false
        );
    }

    private function apply_watermark_to_file( $target_file, $watermark_file, $settings ) {
        if ( ! file_exists( $target_file ) || ! file_exists( $watermark_file ) ) {
            return false;
        }

        if ( class_exists( 'Imagick' ) ) {
            return $this->apply_with_imagick( $target_file, $watermark_file, $settings );
        }

        if ( ! $this->can_use_gd() ) {
            return false;
        }

        return $this->apply_with_gd( $target_file, $watermark_file, $settings );
    }

    private function can_use_gd() {
        $required_functions = array(
            'imagecreatefromstring',
            'imagesx',
            'imagesy',
            'imagecreatetruecolor',
            'imagealphablending',
            'imagesavealpha',
            'imagecolorallocatealpha',
            'imagefill',
            'imagecopyresampled',
            'imagecopymerge',
            'imagedestroy',
            'imagepng',
            'imagejpeg',
            'imagegif',
        );

        foreach ( $required_functions as $function_name ) {
            if ( ! function_exists( $function_name ) ) {
                return false;
            }
        }

        return true;
    }

    private function apply_with_imagick( $target_file, $watermark_file, $settings ) {
        try {
            $image = new Imagick( $target_file );
            $watermark = new Imagick( $watermark_file );

            if ( ! $image->getImageWidth() || ! $image->getImageHeight() ) {
                return false;
            }

            $target_width = (int) $image->getImageWidth();
            $wm_target_width = max( 1, (int) round( $target_width * ( (int) $settings['size_percent'] / 100 ) ) );
            $watermark->resizeImage( $wm_target_width, 0, Imagick::FILTER_LANCZOS, 1 );

            $opacity = max( 0.05, min( 1, ( (int) $settings['opacity'] / 100 ) ) );
            $watermark->evaluateImage( Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA );

            $coords = $this->get_overlay_coordinates( $target_width, (int) $image->getImageHeight(), (int) $watermark->getImageWidth(), (int) $watermark->getImageHeight(), $settings['position'] );
            $image->compositeImage( $watermark, Imagick::COMPOSITE_OVER, $coords['x'], $coords['y'] );
            $image->writeImage( $target_file );

            $watermark->clear();
            $watermark->destroy();
            $image->clear();
            $image->destroy();

            return true;
        } catch ( Exception $e ) {
            return false;
        }
    }

    private function apply_with_gd( $target_file, $watermark_file, $settings ) {
        if ( ! $this->can_use_gd() ) {
            return false;
        }

        $base_blob = @file_get_contents( $target_file );
        $wm_blob = @file_get_contents( $watermark_file );
        if ( false === $base_blob || false === $wm_blob ) {
            return false;
        }

        $base = @imagecreatefromstring( $base_blob );
        $wm = @imagecreatefromstring( $wm_blob );
        if ( ! $base || ! $wm ) {
            return false;
        }

        $base_w = imagesx( $base );
        $base_h = imagesy( $base );
        $wm_w = imagesx( $wm );
        $wm_h = imagesy( $wm );

        if ( ! $base_w || ! $base_h || ! $wm_w || ! $wm_h ) {
            imagedestroy( $base );
            imagedestroy( $wm );
            return false;
        }

        $target_w = max( 1, (int) round( $base_w * ( (int) $settings['size_percent'] / 100 ) ) );
        $target_h = max( 1, (int) round( ( $target_w / $wm_w ) * $wm_h ) );

        $scaled = imagecreatetruecolor( $target_w, $target_h );
        imagealphablending( $scaled, false );
        imagesavealpha( $scaled, true );
        $transparent = imagecolorallocatealpha( $scaled, 0, 0, 0, 127 );
        imagefill( $scaled, 0, 0, $transparent );
        imagecopyresampled( $scaled, $wm, 0, 0, 0, 0, $target_w, $target_h, $wm_w, $wm_h );

        $coords = $this->get_overlay_coordinates( $base_w, $base_h, $target_w, $target_h, $settings['position'] );
        imagealphablending( $base, true );
        imagecopymerge( $base, $scaled, $coords['x'], $coords['y'], 0, 0, $target_w, $target_h, (int) $settings['opacity'] );

        $saved = $this->save_gd_image( $base, $target_file );

        imagedestroy( $scaled );
        imagedestroy( $wm );
        imagedestroy( $base );

        return $saved;
    }

    private function save_gd_image( $image, $target_file ) {
        $extension = strtolower( pathinfo( $target_file, PATHINFO_EXTENSION ) );

        if ( 'png' === $extension ) {
            return imagepng( $image, $target_file );
        }

        if ( 'gif' === $extension ) {
            return imagegif( $image, $target_file );
        }

        if ( 'webp' === $extension && function_exists( 'imagewebp' ) ) {
            return imagewebp( $image, $target_file, 90 );
        }

        if ( 'avif' === $extension && function_exists( 'imageavif' ) ) {
            return imageavif( $image, $target_file, 80 );
        }

        return imagejpeg( $image, $target_file, 90 );
    }

    private function get_overlay_coordinates( $img_w, $img_h, $wm_w, $wm_h, $position ) {
        $margin = 20;

        switch ( $position ) {
            case 'top-left':
                return array( 'x' => $margin, 'y' => $margin );
            case 'top-center':
                return array( 'x' => (int) round( ( $img_w - $wm_w ) / 2 ), 'y' => $margin );
            case 'top-right':
                return array( 'x' => max( 0, $img_w - $wm_w - $margin ), 'y' => $margin );
            case 'center':
                return array( 'x' => (int) round( ( $img_w - $wm_w ) / 2 ), 'y' => (int) round( ( $img_h - $wm_h ) / 2 ) );
            case 'bottom-left':
                return array( 'x' => $margin, 'y' => max( 0, $img_h - $wm_h - $margin ) );
            case 'bottom-center':
                return array( 'x' => (int) round( ( $img_w - $wm_w ) / 2 ), 'y' => max( 0, $img_h - $wm_h - $margin ) );
            case 'bottom-right':
            default:
                return array( 'x' => max( 0, $img_w - $wm_w - $margin ), 'y' => max( 0, $img_h - $wm_h - $margin ) );
        }
    }

    private static function sanitize_position( $position ) {
        $allowed = array( 'top-left', 'top-center', 'top-right', 'center', 'bottom-left', 'bottom-center', 'bottom-right' );
        $position = sanitize_key( $position );

        return in_array( $position, $allowed, true ) ? $position : 'bottom-right';
    }

    private static function preview_position_style( $position ) {
        switch ( self::sanitize_position( $position ) ) {
            case 'top-left':
                return 'top:12px; left:12px;';
            case 'top-center':
                return 'top:12px; left:50%; transform:translateX(-50%);';
            case 'top-right':
                return 'top:12px; right:12px;';
            case 'center':
                return 'top:50%; left:50%; transform:translate(-50%,-50%);';
            case 'bottom-left':
                return 'bottom:12px; left:12px;';
            case 'bottom-center':
                return 'bottom:12px; left:50%; transform:translateX(-50%);';
            case 'bottom-right':
            default:
                return 'bottom:12px; right:12px;';
        }
    }

    private function handle_watermark_upload( $file ) {
        if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
            return new WP_Error( 'invalid_file', __( 'Arquivo inválido.', 'imovel-parceiro-core' ) );
        }

        $valid_mimes = array( 'image/png', 'image/jpeg', 'image/webp' );
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $real_mime = isset( $check['type'] ) ? $check['type'] : '';

        if ( empty( $real_mime ) || ! in_array( $real_mime, $valid_mimes, true ) ) {
            return new WP_Error( 'invalid_mime', __( 'Formato inválido. Use PNG, JPEG ou WebP.', 'imovel-parceiro-core' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes' => array(
                    'png' => 'image/png',
                    'jpg|jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                ),
            )
        );

        if ( ! empty( $upload['error'] ) ) {
            return new WP_Error( 'upload_failed', sanitize_text_field( $upload['error'] ) );
        }

        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            return new WP_Error( 'attachment_failed', __( 'Não foi possível salvar o anexo da marca d\'água.', 'imovel-parceiro-core' ) );
        }

        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        if ( is_array( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        return absint( $attachment_id );
    }

    private function redirect_with_notice( $type, $message ) {
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/' );

        $redirect = add_query_arg(
            array(
                'imovel_admin_area' => 'gestao',
                'imovel_admin_section' => 'watermark',
                'imovel_wm_notice' => sanitize_key( $type ),
                'imovel_wm_message' => wp_strip_all_tags( $message ),
            ),
            $dashboard_url
        );

        wp_safe_redirect( $redirect );
        exit;
    }
}

Imovel_Parceiro_Watermark::instance();
