<?php
if (!defined('ABSPATH')) exit;

/**
 * Garante que ao deletar um usuário (admin -> Usuários -> Deletar),
 * todo rastro seja removido/encerrado e o motivo seja "usuario removido da plataforma".
 */
class Imovel_Parceiro_User_Deletion
{
    const REASON = 'usuario removido da plataforma';

    public function __construct()
    {
        add_action('delete_user', [$this, 'purgeBeforeDelete'], 10, 3);
        // fallback after deletion (user já removido, limpa órfãos)
        add_action('deleted_user', [$this, 'purgeAfterDelete'], 10, 3);
    }

    public function purgeBeforeDelete($id, $reassign, $user)
    {
        $id = absint($id);
        if (!$id) return;
        $this->purge($id);
    }

    public function purgeAfterDelete($id, $reassign, $user)
    {
        $id = absint($id);
        if (!$id) return;
        // garante limpeza mesmo se hook anterior falhou (ex: delete via wp-cli)
        $this->purge($id);
    }

    private function purge(int $userId): void
    {
        global $wpdb;
        $reason = self::REASON;

        // 1) Parcerias: encerrar (não deletar) com motivo - cobre ambas as nomenclaturas de coluna
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
            $hasReq = in_array('requester_id', $cols, true);
            $hasOwn = in_array('owner_id', $cols, true);
            $hasCap = in_array('captador_id', $cols, true);
            $hasPar = in_array('partner_id', $cols, true);
            $conds = [];
            if ($hasReq) $conds[] = $wpdb->prepare("requester_id=%d", $userId);
            if ($hasOwn) $conds[] = $wpdb->prepare("owner_id=%d", $userId);
            if ($hasCap) $conds[] = $wpdb->prepare("captador_id=%d", $userId);
            if ($hasPar) $conds[] = $wpdb->prepare("partner_id=%d", $userId);
            if (!empty($conds)) {
                $where = '(' . implode(' OR ', $conds) . ")";
                $sql = "UPDATE {$table} SET status='encerrada', notes = CASE WHEN notes IS NULL OR notes='' THEN %s ELSE CONCAT(notes,' | ',%s) END, responded_at=NOW() WHERE {$where} AND status NOT IN ('encerrada','closed','cancelled','rejected','won','lost')";
                $wpdb->query($wpdb->prepare($sql, $reason, $reason));
                if (in_array('updated_at', $cols, true)) {
                    $wpdb->query($wpdb->prepare("UPDATE {$table} SET updated_at=NOW() WHERE {$where}", $userId));
                }
            }
        }

        // 2) Assinaturas WooCommerce Subscriptions: cancelar/encerrar
        $this->cancelSubscriptions($userId, $reason);

        // 3) Demais tabelas imovel_parceiro: deletar registros do usuário
        $tables = [
            'imovel_parceiro_opportunities' => ['owner_id','partner_id'],
            'imovel_parceiro_deals' => ['owner_id','partner_id'],
            'imovel_parceiro_commissions' => ['beneficiary_id'],
            'imovel_parceiro_owner_relations' => ['owner_user_id','broker_user_id'],
            'imovel_parceiro_owner_documents' => ['owner_user_id','reviewed_by'],
            'imovel_parceiro_broker_change_requests' => ['owner_user_id','requested_broker_id','current_broker_id','admin_user_id'],
            'imovel_parceiro_notifications' => ['user_id'],
            'imovel_parceiro_notification_subscriptions' => ['user_id'],
            'imovel_parceiro_acceptances' => ['user_id'],
            'imovel_parceiro_deletion_requests' => ['owner_user_id','admin_user_id'],
        ];
        foreach ($tables as $tbl => $cols) {
            $full = $wpdb->prefix . $tbl;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) continue;
            $where = implode(' OR ', array_map(fn($c) => "$c = %d", $cols));
            $args = array_fill(0, count($cols), $userId);
            // prepare dynamically
            $sql = "DELETE FROM {$full} WHERE " . $where;
            $wpdb->query($wpdb->prepare($sql, ...$args));
        }

        // 4) Houzez CRM (se existir) - checa colunas existentes
        $crmTables = [
            'houzez_crm_deals' => ['user_id','agent_id','lead_id'],
            'houzez_crm_activities' => ['user_id'],
            'houzez_crm_leads' => ['user_id','agent_id'],
        ];
        foreach ($crmTables as $tbl => $cols) {
            $full = $wpdb->prefix . $tbl;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) continue;
            $existing = $wpdb->get_col("SHOW COLUMNS FROM {$full}");
            $filtered = array_values(array_intersect($cols, $existing));
            if (empty($filtered)) continue;
            $where = implode(' OR ', array_map(fn($c) => "$c = %d", $filtered));
            $args = array_fill(0, count($filtered), $userId);
            $wpdb->query($wpdb->prepare("DELETE FROM {$full} WHERE " . $where, ...$args));
        }

        // 5) Propriedades e posts do usuário (property, houzez_agent, houzez_agency, shop_subscription post_author)
        $postTypes = ['property','houzez_agent','houzez_agency'];
        foreach ($postTypes as $pt) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type=%s", $userId, $pt));
            foreach ($ids as $pid) {
                wp_delete_post((int)$pid, true);
            }
        }

        // 6) Limpa transientes e metas órfãs já serão removidas pelo core, mas garante
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE user_id=%d", $userId));

        // Log
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log('info', "Purge usuário {$userId} motivo: {$reason}", ['source'=>'user-deletion']);
        }
        error_log("[user-deletion] purge {$userId} motivo: {$reason}");
    }

    private function cancelSubscriptions(int $userId, string $reason): void
    {
        global $wpdb;
        // subscriptions onde _customer_user = userId
        $subIds = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_customer_user' AND pm.meta_value=%d AND p.post_type='shop_subscription'",
            $userId
        ));
        // também onde post_author = userId (fallback)
        $more = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type='shop_subscription'", $userId));
        $subIds = array_unique(array_merge($subIds, $more));
        if (empty($subIds)) return;

        foreach ($subIds as $sid) {
            $sid = absint($sid);
            // Tenta via API WC Subscriptions
            if (function_exists('wcs_get_subscription')) {
                $sub = wcs_get_subscription($sid);
                if ($sub) {
                    try {
                        $sub->add_order_note(sprintf('Assinatura encerrada automaticamente: %s (usuário %d removido)', $reason, $userId));
                        if ($sub->has_status(['active','on-hold','pending'])) {
                            $sub->update_status('cancelled', $reason);
                        } else {
                            $sub->update_status('cancelled');
                        }
                        // move para trash e depois delete permanente para garantir BD limpo
                        wp_trash_post($sid);
                        wp_delete_post($sid, true);
                        continue;
                    } catch (Throwable $e) {
                        // fallback para DB
                    }
                }
            }
            // fallback DB puro
            $wpdb->update($wpdb->posts, ['post_status'=>'wc-cancelled'], ['ID'=>$sid]);
            update_post_meta($sid, '_cancelled_reason', $reason);
            wp_trash_post($sid);
            wp_delete_post($sid, true);
        }

        // Também cancela assinaturas via Asaas se houver meta asaas_subscription_id (registra erro mas não bloqueia)
        // já cobertas pelo fluxo acima; o Asaas será cancelado via webhook se necessário
    }
}

new Imovel_Parceiro_User_Deletion();
