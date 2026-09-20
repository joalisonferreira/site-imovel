<?php
if (!defined('ABSPATH')) exit;

/**
 * Organiza fotos de imóveis por pasta com o nome do imóvel + ID.
 * Mantém compatibilidade total com o que já existe:
 * - Arquivos antigos continuam onde estão (2026/09 etc.)
 * - Apenas novos uploads e novos imóveis usam imoveis/{slug}-{ID}/
 * - Move automaticamente anexos de imóveis recém-criados para a pasta correta
 */
class Imovel_Parceiro_Media_Organizer {

    const META_MOVED = '_imovel_parceiro_media_moved';
    const BASE_FOLDER = 'imoveis';

    public function __construct() {
        // Para edição de imóvel existente: upload direto na pasta do imóvel
        add_filter('upload_dir', array($this, 'filter_upload_dir'));

        // Para criação de imóvel: move anexos após salvar (quando já temos o ID)
        add_action('save_post_property', array($this, 'maybe_move_gallery'), 20, 3);
        // Também cobre criação via wp_insert_post
        add_action('added_post_meta', array($this, 'on_gallery_meta_added'), 10, 4);
        add_action('updated_post_meta', array($this, 'on_gallery_meta_added'), 10, 4);
    }

    /**
     * Se for upload de imagem de imóvel em edição, direciona para imoveis/{slug}-{ID}
     */
    public function filter_upload_dir($dirs) {
        // Só intervenha em uploads via media/plupload que carregam $_POST['action']
        // Houzez usa: action=houzez_property_img_upload e também plupload genérico
        $is_property_upload = false;

        // Caso 1: Houzez property image upload
        if (!empty($_POST['action']) && $_POST['action'] === 'houzez_property_img_upload') {
            $is_property_upload = true;
        }
        // Caso 2: Edição com post_id na requisição
        if (!$is_property_upload && !empty($_REQUEST['post_id'])) {
            $pid = absint($_REQUEST['post_id']);
            if ($pid && get_post_type($pid) === 'property') {
                $is_property_upload = true;
            }
        }
        // Caso 3: property_id/prop_id no POST (submit)
        if (!$is_property_upload && (!empty($_POST['property_id']) || !empty($_POST['prop_id']))) {
            $pid = !empty($_POST['property_id']) ? absint($_POST['property_id']) : absint($_POST['prop_id']);
            if ($pid && get_post_type($pid) === 'property') {
                $is_property_upload = true;
            }
        }

        if (!$is_property_upload) {
            return $dirs;
        }

        // Tenta resolver o ID do imóvel (edição)
        $property_id = 0;
        if (!empty($_REQUEST['post_id'])) $property_id = absint($_REQUEST['post_id']);
        elseif (!empty($_POST['property_id'])) $property_id = absint($_POST['property_id']);
        elseif (!empty($_POST['prop_id'])) $property_id = absint($_POST['prop_id']);
        elseif (!empty($_GET['post'])) $property_id = absint($_GET['post']);

        if (!$property_id || get_post_type($property_id) !== 'property') {
            // Criação: deixa no temp padrão (será movido depois via save_post)
            return $dirs;
        }

        if (!current_user_can('edit_post', $property_id) && !current_user_can('edit_posts')) {
            return $dirs;
        }

        $folder = $this->property_folder($property_id);
        $dirs['subdir'] = '/' . $folder;
        $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

        // Garante que a pasta exista
        if (!file_exists($dirs['path'])) {
            wp_mkdir_p($dirs['path']);
        }

        return $dirs;
    }

    /**
     * Nome da pasta: imoveis/{slug}-{ID} (ex: imoveis/condominio-weekend-bandeirantes-21952)
     * Reutiliza a pasta já gravada em post_meta para não duplicar ao trocar o título
     */
    public function property_folder($property_id) {
        $property_id = absint($property_id);
        $stored = get_post_meta($property_id, '_imovel_parceiro_media_folder', true);
        if (!empty($stored) && is_string($stored)) {
            return $stored;
        }
        $slug = sanitize_title(get_the_title($property_id));
        if (empty($slug)) $slug = 'imovel';
        $slug = substr($slug, 0, 60);
        $folder = self::BASE_FOLDER . '/' . $slug . '-' . $property_id;
        // Persiste para reutilização futura
        if ($property_id) {
            update_post_meta($property_id, '_imovel_parceiro_media_folder', $folder);
        }
        return $folder;
    }

    /**
     * Após salvar o imóvel, move anexos que ainda estão em 2026/09 para a pasta do imóvel
     */
    public function maybe_move_gallery($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (get_post_type($post_id) !== 'property') return;
        if (!current_user_can('edit_post', $post_id) && !current_user_can('edit_posts') && !current_user_can('manage_options')) {
            return;
        }
        // Evita loop: marca que já moveu esta versão
        if (get_post_meta($post_id, self::META_MOVED, true)) {
            // Se for atualização, ainda pode haver novas imagens em temp; verifica
            // Mas evita reprocessar tudo a cada save. Só processa anexos ainda não movidos.
        }

        $gallery = get_post_meta($post_id, 'fave_property_images', false);
        if (empty($gallery)) return;
        // fave_property_images pode ser array ou string com IDs separados por vírgula
        $ids = array();
        foreach ((array)$gallery as $g) {
            if (is_array($g)) $ids = array_merge($ids, $g);
            elseif (strpos($g, ',') !== false) $ids = array_merge($ids, explode(',', $g));
            else $ids[] = $g;
        }
        $ids = array_filter(array_map('absint', $ids));
        // Inclui thumbnail
        $thumb = get_post_thumbnail_id($post_id);
        if ($thumb) $ids[] = absint($thumb);
        $ids = array_unique($ids);
        if (empty($ids)) return;

        $moved = 0;
        foreach ($ids as $att_id) {
            if ($this->move_attachment_to_property_folder($att_id, $post_id)) $moved++;
        }

        if ($moved) {
            update_post_meta($post_id, self::META_MOVED, current_time('mysql'));
        }
    }

    public function on_gallery_meta_added($meta_id, $post_id, $meta_key, $meta_value) {
        if ($meta_key !== 'fave_property_images' && $meta_key !== '_thumbnail_id') return;
        if (get_post_type($post_id) !== 'property') return;
        // Delega para maybe_move_gallery (com debounce simples)
        static $done = array();
        if (isset($done[$post_id])) return;
        $done[$post_id] = true;
        $post = get_post($post_id);
        if ($post) $this->maybe_move_gallery($post_id, $post, true);
    }

    /**
     * Move um attachment (original + todos os sizes + webp) para a pasta do imóvel
     */
    private function move_attachment_to_property_folder($attachment_id, $property_id) {
        // Verifica se o usuário pode editar o imóvel ou é o autor do anexo
        $att_author = (int) get_post_field('post_author', $attachment_id);
        $current_user = get_current_user_id();
        if ($att_author !== $current_user && !current_user_can('edit_post', $property_id) && !current_user_can('manage_options')) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return false;

        // Já está na pasta correta?
        $target_folder = $this->property_folder($property_id);
        if (strpos($file, $target_folder) !== false) return false;

        $upload = wp_get_upload_dir();
        $basedir = $upload['basedir'];
        $baseurl = $upload['baseurl'];

        // Meta contém todos os tamanhos
        $meta = wp_get_attachment_metadata($attachment_id);
        $files_to_move = array();

        // Arquivo principal
        $rel = str_replace($basedir . '/', '', $file);
        $files_to_move[] = $rel;

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($rel);
            foreach ($meta['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $files_to_move[] = $dir . '/' . $size['file'];
                }
            }
        }
        // Também tenta webp correspondentes (webp-express)
        $webp_variants = array();
        foreach ($files_to_move as $rel_file) {
            $webp_variants[] = $rel_file . '.webp';
            // Algumas configs geram .jpg.webp
            if (substr($rel_file, -4) === '.jpg') $webp_variants[] = substr($rel_file, 0, -4) . '.webp';
            if (substr($rel_file, -5) === '.jpeg') $webp_variants[] = substr($rel_file, 0, -5) . '.webp';
        }
        $files_to_move = array_merge($files_to_move, $webp_variants);

        $target_path = $basedir . '/' . $target_folder;
        if (!file_exists($target_path)) {
            wp_mkdir_p($target_path);
        }

        $moved_any = false;
        $new_file_rel = '';
        foreach ($files_to_move as $rel_file) {
            $src = $basedir . '/' . $rel_file;
            if (!file_exists($src)) continue;
            $dest = $basedir . '/' . $target_folder . '/' . basename($rel_file);
            // Evita sobrescrever
            if (file_exists($dest)) continue;
            if (@rename($src, $dest)) {
                $moved_any = true;
                if ($rel_file === $rel) {
                    $new_file_rel = $target_folder . '/' . basename($rel_file);
                }
            }
        }

        if ($moved_any && $new_file_rel) {
            // Atualiza _wp_attached_file e guid
            update_attached_file($attachment_id, $new_file_rel);
            $new_guid = $baseurl . '/' . $new_file_rel;
            global $wpdb;
            $wpdb->update($wpdb->posts, array('guid' => $new_guid), array('ID' => $attachment_id));

            // Atualiza metadata (file)
            if (!empty($meta['file'])) {
                $meta['file'] = $new_file_rel;
                wp_update_attachment_metadata($attachment_id, $meta);
            }
            return true;
        }

        return false;
    }
}

new Imovel_Parceiro_Media_Organizer();
