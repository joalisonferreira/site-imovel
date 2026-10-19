<?php
if (!defined('ABSPATH')) exit;

/**
 * Garante que ao deletar um usuário (admin -> Usuários -> Deletar),
 * todo rastro seja removido/encerrado e o motivo seja "usuario removido da plataforma".
 *
 * REMOÇÃO TOTAL INTENCIONAL: ignora a opção "atribuir conteúdo a outro usuário"
 * do WP — mesmo com reassign preenchido, o conteúdo do removido é excluído.
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
        $email = '';
        if ($user instanceof WP_User) {
            $email = $user->user_email;
        } else {
            $u = get_userdata($id);
            if ($u) $email = $u->user_email;
        }
        // guarda email para purgeAfterDelete (quando user já não existe)
        if ($email) {
            set_transient('ipd_purge_email_' . $id, $email, 300);
        }
        $this->purge($id, $email);
    }

    public function purgeAfterDelete($id, $reassign, $user)
    {
        $id = absint($id);
        if (!$id) return;
        $email = get_transient('ipd_purge_email_' . $id);
        if (!$email && $user instanceof WP_User) {
            $email = $user->user_email;
        }
        $this->purge($id, (string) $email);
        delete_transient('ipd_purge_email_' . $id);
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function purge(int $userId, string $email = ''): void
    {
        global $wpdb;
        $reason = self::REASON;

        // 1) Parcerias: encerrar (não deletar) com motivo - cobre ambas as nomenclaturas de coluna
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ($this->tableExists($table)) {
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
                    // $where já vem com os IDs preparados acima; sem placeholders aqui
                    // (prepare() com args e sem placeholder falha no WP 6.2+).
                    $wpdb->query("UPDATE {$table} SET updated_at=NOW() WHERE {$where}");
                }
            }
        }

        // 2) Assinaturas WooCommerce Subscriptions: cancelar/encerrar (legado + HPOS)
        $this->cancelSubscriptions($userId, $email, $reason);

        // 2b) Pedidos WooCommerce (HPOS + legado): excluir tudo do usuário
        $this->deleteOrders($userId, $email, $reason);

        // 2c) Planos mpa_subscriptions (user_id + email)
        $mpa = $wpdb->prefix . 'mpa_subscriptions';
        if ($this->tableExists($mpa)) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$mpa} WHERE user_id=%d", $userId));
            if ('' !== $email) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$mpa} WHERE email=%s", $email));
            }
        }

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
            'imovel_parceiro_contact_access_log' => ['user_id'],
            'imovel_parceiro_partnership_events' => ['actor_user_id'],
            'imovel_parceiro_admin_alerts' => ['user_id','resolved_by'],
        ];
        foreach ($tables as $tbl => $cols) {
            $full = $wpdb->prefix . $tbl;
            if (!$this->tableExists($full)) continue;
            $where = implode(' OR ', array_map(fn($c) => "$c = %d", $cols));
            $args = array_fill(0, count($cols), $userId);
            // prepare dynamically
            $sql = "DELETE FROM {$full} WHERE " . $where;
            $wpdb->query($wpdb->prepare($sql, ...$args));
        }

        // 3b) Audit logs: anonimiza (mantém a trilha, remove o dado pessoal)
        $audit = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        if ($this->tableExists($audit)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$audit} SET actor_user_id=0 WHERE actor_user_id=%d",
                $userId
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$audit} SET other_user_id=0 WHERE other_user_id=%d",
                $userId
            ));
        }

        // 4) Houzez CRM (se existir) - checa colunas existentes
        $crmTables = [
            'houzez_crm_deals' => ['user_id','agent_id','lead_id'],
            'houzez_crm_activities' => ['user_id'],
            'houzez_crm_leads' => ['user_id','agent_id'],
            'houzez_crm_enquiries' => ['user_id','enquiry_to'],
            'houzez_crm_notes' => ['user_id'],
            'houzez_crm_viewed_listings' => ['user_id'],
        ];
        foreach ($crmTables as $tbl => $cols) {
            $full = $wpdb->prefix . $tbl;
            if (!$this->tableExists($full)) continue;
            $existing = $wpdb->get_col("SHOW COLUMNS FROM {$full}");
            $filtered = array_values(array_intersect($cols, $existing));
            if (empty($filtered)) continue;
            $where = implode(' OR ', array_map(fn($c) => "$c = %d", $filtered));
            $args = array_fill(0, count($filtered), $userId);
            $wpdb->query($wpdb->prepare("DELETE FROM {$full} WHERE " . $where, ...$args));
        }

        // 5) Propriedades e posts do usuário (property, houzez_agent, houzez_agency,
        //    houzez_reviews) + anexos filhos (fotos/arquivos não podem ficar órfãos)
        $postTypes = ['property','houzez_agent','houzez_agency','houzez_reviews'];
        foreach ($postTypes as $pt) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type=%s", $userId, $pt));
            foreach ($ids as $pid) {
                $this->deletePostTree((int) $pid);
            }
        }

        // 5b) Comentários do usuário (nome/e-mail não podem seguir públicos)
        $commentIds = $wpdb->get_col($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE user_id=%d", $userId));
        if ('' !== $email) {
            $more = $wpdb->get_col($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_author_email=%s", $email));
            $commentIds = array_unique(array_merge($commentIds, $more));
        }
        foreach ($commentIds as $cid) {
            wp_delete_comment((int) $cid, true);
        }

        // 6) Woo: tokens de pagamento, lookup de cliente e sessões
        $tokensTable = $wpdb->prefix . 'woocommerce_payment_tokens';
        if ($this->tableExists($tokensTable)) {
            $tokenIds = $wpdb->get_col($wpdb->prepare("SELECT token_id FROM {$tokensTable} WHERE user_id=%d", $userId));
            foreach ($tokenIds as $tid) {
                $wpdb->delete($wpdb->prefix . 'woocommerce_payment_tokenmeta', ['token_id' => $tid]);
                $wpdb->delete($tokensTable, ['token_id' => $tid]);
            }
        }
        $lookupTable = $wpdb->prefix . 'wc_customer_lookup';
        if ($this->tableExists($lookupTable)) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$lookupTable} WHERE user_id=%d", $userId));
            if ('' !== $email) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$lookupTable} WHERE email=%s", $email));
            }
        }
        $sessionsTable = $wpdb->prefix . 'woocommerce_sessions';
        if ($this->tableExists($sessionsTable) && '' !== $email) {
            $like = '%' . $wpdb->esc_like($email) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$sessionsTable} WHERE session_value LIKE %s", $like));
        }

        // 7) Limpa transientes e metas órfãs já serão removidas pelo core, mas garante
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE user_id=%d", $userId));

        // Log
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log('info', "Purge usuário {$userId} motivo: {$reason}", ['source'=>'user-deletion']);
        }
        error_log("[user-deletion] purge {$userId} motivo: {$reason}");
    }

    /**
     * Deleta post + anexos filhos (evita fotos/arquivos órfãos no banco e disco).
     */
    private function deletePostTree(int $postId): void
    {
        global $wpdb;
        if ($postId <= 0) return;
        $attachments = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='attachment'",
            $postId
        ));
        foreach ($attachments as $aid) {
            wp_delete_attachment((int) $aid, true);
        }
        wp_delete_post($postId, true);
    }

    private function cancelSubscriptions(int $userId, string $email, string $reason): void
    {
        global $wpdb;
        // Legado (postmeta): subscriptions onde _customer_user = userId
        $subIds = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_customer_user' AND pm.meta_value=%d AND p.post_type='shop_subscription'",
            $userId
        ));
        // também onde post_author = userId (fallback legado)
        $more = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type='shop_subscription'", $userId));

        // HPOS: wc_orders type shop_subscription por customer_id ou billing_email
        $hposTable = $wpdb->prefix . 'wc_orders';
        if ($this->tableExists($hposTable)) {
            $hpos = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$hposTable} WHERE type='shop_subscription' AND customer_id=%d",
                $userId
            ));
            $more = array_merge($more, $hpos);
            if ('' !== $email) {
                $byEmail = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$hposTable} WHERE type='shop_subscription' AND billing_email=%s",
                    $email
                ));
                $more = array_merge($more, $byEmail);
            }
        }

        $subIds = array_unique(array_merge($subIds, array_map('absint', (array) $more)));
        $subIds = array_filter($subIds);
        if (empty($subIds)) return;

        foreach ($subIds as $sid) {
            $sid = absint($sid);
            // Tenta via API WC Subscriptions (cobre legado e HPOS)
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
                        if (function_exists('wc_get_order')) {
                            $maybeOrder = wc_get_order($sid);
                            if ($maybeOrder) {
                                try { $maybeOrder->delete(true); } catch (Throwable $e) {}
                            }
                        }
                        continue;
                    } catch (Throwable $e) {
                        // fallback para DB
                    }
                }
            }
            // fallback DB puro (legado)
            $wpdb->update($wpdb->posts, ['post_status'=>'wc-cancelled'], ['ID'=>$sid]);
            update_post_meta($sid, '_cancelled_reason', $reason);
            wp_trash_post($sid);
            wp_delete_post($sid, true);
            // fallback DB puro (HPOS)
            if ($this->tableExists($hposTable)) {
                $wpdb->update($hposTable, ['status' => 'wc-cancelled'], ['id' => $sid]);
                $wpdb->delete($wpdb->prefix . 'wc_order_addresses', ['order_id' => $sid]);
                $wpdb->delete($wpdb->prefix . 'wc_order_operational_data', ['order_id' => $sid]);
                $wpdb->delete($wpdb->prefix . 'wc_orders_meta', ['order_id' => $sid]);
                $wpdb->delete($hposTable, ['id' => $sid]);
            }
        }

        // Cancela também no Asaas (nuvem) as assinaturas vinculadas ao usuário
        $this->cancelAsaasSubscriptions($userId, $email);
    }

    /**
     * Cancela na nuvem Asaas as assinaturas com _asaas_subscription_id
     * das subscriptions/pedidos do usuário. Nunca bloqueia o purge.
     */
    private function cancelAsaasSubscriptions(int $userId, string $email): void
    {
        global $wpdb;
        if (!class_exists('WC_Asaas\Api\Client\Client')) {
            error_log("[user-deletion] Asaas client ausente; cancelar manualmente as assinaturas do usuário {$userId}");
            return;
        }

        // gateway Asaas disponível para autenticar o client
        $gateway = null;
        if (function_exists('WC') && isset(WC()->payment_gateways)) {
            foreach ((array) WC()->payment_gateways()->payment_gateways() as $gw) {
                if ($gw instanceof WC_Payment_Gateway && 0 === strpos((string) $gw->id, 'asaas')) {
                    $gateway = $gw;
                    break;
                }
            }
        }
        if (!$gateway) {
            error_log("[user-deletion] gateway Asaas ausente; cancelar manualmente as assinaturas do usuário {$userId}");
            return;
        }

        // coleta ids Asaas: postmeta legado + HPOS meta
        $asaasIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_asaas_subscription_id' AND (pm.meta_value<>'' ) AND (p.post_author=%d OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id=p.ID AND pm2.meta_key IN ('_customer_user','_billing_email') AND (pm2.meta_value=%s OR pm2.meta_value=%s)))",
            $userId, (string) $userId, $email
        ));
        $metaTable = $wpdb->prefix . 'wc_orders_meta';
        if ($this->tableExists($metaTable)) {
            $hposIds = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT meta_value FROM {$metaTable} WHERE meta_key='_asaas_subscription_id' AND meta_value<>''"
            ));
            $asaasIds = array_merge($asaasIds, $hposIds);
        }
        $asaasIds = array_unique(array_filter(array_map('trim', (array) $asaasIds)));
        if (empty($asaasIds)) return;

        try {
            $client = new WC_Asaas\Api\Client\Client($gateway);
        } catch (Throwable $e) {
            error_log("[user-deletion] falha ao instanciar Asaas client (usuário {$userId}): " . $e->getMessage());
            return;
        }

        foreach ($asaasIds as $asaasId) {
            try {
                $client->delete('/subscriptions/' . rawurlencode($asaasId));
                error_log("[user-deletion] assinatura Asaas {$asaasId} cancelada (usuário {$userId})");
            } catch (Throwable $e) {
                error_log("[user-deletion] FALHA ao cancelar Asaas {$asaasId} (usuário {$userId}): " . $e->getMessage());
            }
        }
    }

    private function deleteOrders(int $userId, string $email, string $reason): void
    {
        global $wpdb;
        $orderIds = [];

        // HPOS: wc_orders
        $hposTable = $wpdb->prefix . 'wc_orders';
        if ($this->tableExists($hposTable)) {
            $ids = [];
            $ids = array_merge($ids, $wpdb->get_col($wpdb->prepare("SELECT id FROM {$hposTable} WHERE customer_id=%d", $userId)));
            if ('' !== $email) {
                $more = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$hposTable} WHERE billing_email=%s", $email));
                $ids = array_merge($ids, $more);
            }
            $ids = array_unique(array_map('absint', $ids));
            foreach ($ids as $oid) {
                $orderIds[] = $oid;
            }
            // deleta via API se possível, senão direto
            foreach ($ids as $oid) {
                if (function_exists('wc_get_order')) {
                    $order = wc_get_order($oid);
                    if ($order) {
                        try { $order->delete(true); continue; } catch (Throwable $e) {}
                    }
                }
                // fallback direto
                $wpdb->delete($hposTable, ['id' => $oid]);
                $wpdb->delete($wpdb->prefix . 'wc_order_addresses', ['order_id' => $oid]);
                $wpdb->delete($wpdb->prefix . 'wc_order_operational_data', ['order_id' => $oid]);
                $wpdb->delete($wpdb->prefix . 'wc_orders_meta', ['order_id' => $oid]);
            }
        }

        // Legado: wp_posts shop_order / shop_order_placehold
        $legacyIds = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type IN ('shop_order','shop_order_placehold') AND pm.meta_key='_customer_user' AND pm.meta_value=%d", $userId));
        if ('' !== $email) {
            $more = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type IN ('shop_order','shop_order_placehold') AND pm.meta_key='_billing_email' AND pm.meta_value=%s", $email));
            $legacyIds = array_merge($legacyIds, $more);
        }
        $legacyIds = array_unique(array_map('absint', $legacyIds));
        foreach ($legacyIds as $oid) {
            if (!in_array($oid, $orderIds, true)) {
                $orderIds[] = $oid;
            }
            wp_delete_post($oid, true);
        }

        // Também deleta diretamente por post_author (caso HPOS não usado)
        $authorOrders = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type IN ('shop_order','shop_order_placehold')", $userId));
        foreach ($authorOrders as $oid) {
            wp_delete_post((int)$oid, true);
        }

        if (!empty($orderIds) && function_exists('wc_get_logger')) {
            wc_get_logger()->log('info', "Purge pedidos usuário {$userId} (" . implode(',', $orderIds) . ") motivo: {$reason}", ['source'=>'user-deletion']);
        }
    }
}

new Imovel_Parceiro_User_Deletion();
