<?php
/**
 * Plugin Name: Asaas NFS-e - Subscriptions InvoiceSettings
 * Description: Configura emissão automática de NFS-e por assinatura Asaas (API v3) via WooCommerce Subscriptions. Hook em criação/vínculo da assinatura Asaas.
 * Version: 1.0.0
 * Author: Imóvel Parceiro Core
 * Requires PHP: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Asaas_Nfse_Subscription
{
    public const META_ASAAS_ID = '_asaas_subscription_id';
    public const META_FLAG     = '_asaas_nfse_configured';
    public const META_ERROR    = '_asaas_nfse_last_error';
    public const LOG_SOURCE    = 'asaas-nfse';

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Legado (postmeta). Com HPOS os metas de assinatura vivem em
        // wc_orders_meta e estes hooks nunca disparam para elas.
        add_action('added_post_meta', [$this, 'onMeta'], 10, 4);
        add_action('updated_post_meta', [$this, 'onMeta'], 10, 4);
        // HPOS: dispara em toda troca de status (3 args: $subscription,
        // $old_status, $new_status) com o objeto WC_Subscription.
        add_action('woocommerce_subscription_status_updated', [$this, 'onSubscriptionStatusUpdated'], 20, 3);
        add_action('woocommerce_subscription_status_active', [$this, 'onSubscriptionActive'], 20, 1);
        add_action('wcs_create_subscription', [$this, 'maybeConfigureById'], 20, 1);
        add_filter('woocommerce_checkout_fields', [$this, 'enforceAsaasFields'], 20);
        add_filter('woocommerce_billing_fields', [$this, 'enforceBillingFields'], 20);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateAsaasFields'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutStyles'], 20);
    }

    public function enforceAsaasFields($fields)
    {
        // Asaas exige para NFS-e: nome/sobrenome, email, cpf/cnpj, cep, endereço, número, bairro, cidade, estado, telefone
        if (isset($fields['billing']['billing_persontype'])) {
            $fields['billing']['billing_persontype']['required'] = true;
        }
        if (isset($fields['billing']['billing_neighborhood'])) {
            $fields['billing']['billing_neighborhood']['required'] = true;
            $fields['billing']['billing_neighborhood']['label'] = 'Bairro *';
        }
        if (isset($fields['billing']['billing_number'])) {
            $fields['billing']['billing_number']['required'] = true;
            $fields['billing']['billing_number']['label'] = 'Número *';
            $fields['billing']['billing_number']['placeholder'] = 'Ex: 405';
        }
        if (isset($fields['billing']['billing_cellphone'])) {
            $fields['billing']['billing_cellphone']['required'] = true;
        }
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['label'] = 'Celular / Telefone *';
        }
        // CPF/CNPJ deixamos validação condicional no validateAsaasFields
        if (isset($fields['billing']['billing_cpf'])) {
            $fields['billing']['billing_cpf']['custom_attributes'] = ['data-asaas'=>'cpf'];
        }
        if (isset($fields['billing']['billing_cnpj'])) {
            $fields['billing']['billing_cnpj']['custom_attributes'] = ['data-asaas'=>'cnpj'];
        }
        return $fields;
    }

    public function enforceBillingFields($fields)
    {
        if (isset($fields['billing_neighborhood'])) {
            $fields['billing_neighborhood']['required'] = true;
        }
        if (isset($fields['billing_number'])) {
            $fields['billing_number']['required'] = true;
        }
        if (isset($fields['billing_phone'])) {
            $fields['billing_phone']['required'] = true;
        }
        if (isset($fields['billing_cellphone'])) {
            $fields['billing_cellphone']['required'] = true;
        }
        return $fields;
    }

    public function validateAsaasFields($data, $errors)
    {
        // Valida CPF/CNPJ conforme persontype para evitar "Endereço incompleto" no Asaas
        $persontype = isset($_POST['billing_persontype']) ? sanitize_text_field($_POST['billing_persontype']) : '';
        $cpf = isset($_POST['billing_cpf']) ? preg_replace('/\D/', '', $_POST['billing_cpf']) : '';
        $cnpj = isset($_POST['billing_cnpj']) ? preg_replace('/\D/', '', $_POST['billing_cnpj']) : '';
        $phone = isset($_POST['billing_phone']) ? preg_replace('/\D/', '', $_POST['billing_phone']) : '';
        $cell = isset($_POST['billing_cellphone']) ? preg_replace('/\D/', '', $_POST['billing_cellphone']) : '';
        $number = isset($_POST['billing_number']) ? trim($_POST['billing_number']) : '';
        $neighborhood = isset($_POST['billing_neighborhood']) ? trim($_POST['billing_neighborhood']) : '';

        if ('2' === $persontype) {
            if (strlen($cnpj) !== 14) {
                $errors->add('validation', 'CNPJ obrigatório e deve conter 14 dígitos para Pessoa Jurídica (Asaas).');
            }
        } else {
            if (strlen($cpf) !== 11) {
                $errors->add('validation', 'CPF obrigatório e deve conter 11 dígitos para Pessoa Física (Asaas).');
            }
        }
        if ('' === $number) {
            $errors->add('validation', 'Número do endereço é obrigatório para emissão da NFS-e.');
        }
        if ('' === $neighborhood) {
            $errors->add('validation', 'Bairro é obrigatório para emissão da NFS-e.');
        }
        if ('' === $phone && '' === $cell) {
            $errors->add('validation', 'Telefone/Celular é obrigatório para o Asaas.');
        }
    }

    public function enqueueCheckoutStyles()
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        $css = plugin_dir_url(__FILE__) . 'assets/css/asaas-checkout.css';
        // fallback se arquivo ainda não existe (evita 404)
        wp_register_style('asaas-checkout', $css, [], '1.0.0');
        wp_enqueue_style('asaas-checkout');
        // JS para alternar CPF/CNPJ visualmente
        $js = "(function(){document.addEventListener('DOMContentLoaded',function(){var pt=document.getElementById('billing_persontype');function t(){var v=pt?pt.value:'';var cpf=document.getElementById('billing_cpf_field');var cnpj=document.getElementById('billing_cnpj_field');if(!cpf||!cnpj)return;cpf.style.display=v==='2'?'none':'';cnpj.style.display=v==='2'?'':'none';}if(pt){pt.addEventListener('change',t);t();}})})();";
        wp_add_inline_script('jquery', $js);
    }

    public function onMeta(int $metaId, int $objectId, string $metaKey, $metaValue): void
    {
        if (!in_array($metaKey, $this->getAsaasMetaKeys(), true)) {
            return;
        }
        if ('shop_subscription' !== get_post_type($objectId)) {
            return;
        }
        $asaasId = $this->sanitizeAsaasId($metaValue);
        if ('' === $asaasId) {
            return;
        }
        $this->maybeConfigure((int) $objectId, $asaasId);
    }

    public function onSubscriptionActive($subscription): void
    {
        $id = $subscription instanceof WC_Subscription ? $subscription->get_id() : (int) $subscription;
        $this->maybeConfigureById($id);
    }

    public function onSubscriptionStatusUpdated($subscription, $oldStatus = '', $newStatus = ''): void
    {
        // Assinatura ainda sem ID Asaas (ex: rollback de cartão recusado)
        // não tem o que configurar; apenas ignora sem fatal.
        $this->maybeConfigureById($subscription);
    }

    public function maybeConfigureById($subscription): void
    {
        try {
            $subscriptionId = $subscription instanceof WC_Subscription ? $subscription->get_id() : (int) $subscription;
            if (!$subscriptionId) {
                return;
            }
            $asaasId = $this->getAsaasId($subscriptionId);
            if ('' !== $asaasId) {
                $this->maybeConfigure($subscriptionId, $asaasId);
            }
        } catch (Throwable $e) {
            $this->log('maybeConfigureById exception: ' . $e->getMessage(), 'error');
        }
    }

    private function maybeConfigure(int $subscriptionId, string $asaasId): void
    {
        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        if ($subscription) {
            if ('yes' === $subscription->get_meta(self::META_FLAG)) {
                $this->log("Skip {$subscriptionId} já configurado asaas={$asaasId}");
                return;
            }
        } elseif ('yes' === get_post_meta($subscriptionId, self::META_FLAG, true)) {
            $this->log("Skip {$subscriptionId} já configurado asaas={$asaasId}");
            return;
        }
        $lock = "asaas_nfse_lock_{$subscriptionId}";
        if (false !== get_transient($lock)) {
            return;
        }
        set_transient($lock, 1, 60);

        $credentials = $this->getCredentials();
        if ('' === $credentials['token'] || '' === $credentials['url']) {
            $this->log("Credenciais ausentes sub={$subscriptionId}", 'error');
            $this->storeError($subscription, $subscriptionId, 'Credenciais Asaas não configuradas');
            return;
        }

        $payload = $this->buildPayload($subscriptionId);
        $response = $this->callAsaas($asaasId, $payload, $credentials);

        if ($response['success']) {
            $this->storeSuccess($subscription, $subscriptionId);
            $this->log("OK sub={$subscriptionId} asaas={$asaasId} payload=" . wp_json_encode($payload));
        } else {
            $this->storeError($subscription, $subscriptionId, $response['error']);
            $this->log("FAIL sub={$subscriptionId} asaas={$asaasId} err={$response['error']} http={$response['code']}", 'error');
        }
    }

    /**
     * @return array{token:string,url:string}
     */
    private function getCredentials(): array
    {
        // Pares consistentes (chave+endpoint do mesmo ambiente): misturar
        // chave de produção com URL de sandbox dá 401 e quebra a NFS-e.
        $constToken = defined('ASAAS_API_KEY') ? trim((string) ASAAS_API_KEY) : '';
        $constUrl   = defined('ASAAS_API_URL') ? trim((string) ASAAS_API_URL) : '';
        $optToken   = trim((string) get_option('asaas_api_key', ''));
        $optUrl     = trim((string) get_option('asaas_api_url', ''));
        if ('' !== $constToken) {
            $token = $constToken;
            $url = '' !== $constUrl ? $constUrl : ('' !== $optUrl ? $optUrl : 'https://www.asaas.com/api/v3');
        } elseif ('' !== $optToken) {
            $token = $optToken;
            $url = '' !== $optUrl ? $optUrl : ('' !== $constUrl ? $constUrl : 'https://www.asaas.com/api/v3');
        } else {
            // Fallback final: configurações do gateway woo-asaas (onde a chave
            // real vive: credit-card -> ticket -> pix). Chave e endpoint sempre
            // do mesmo gateway para não misturar ambientes.
            $token = '';
            $url = '';
            foreach (['woocommerce_asaas-credit-card_settings','woocommerce_asaas-ticket_settings','woocommerce_asaas-pix_settings'] as $optKey) {
                $gw = get_option($optKey, []);
                if (!is_array($gw) || empty($gw['api_key'])) continue;
                $token = trim((string) $gw['api_key']);
                $url = !empty($gw['endpoint']) ? trim((string) $gw['endpoint']) : 'https://www.asaas.com/api/v3';
                break;
            }
            if ('' === $url) {
                $url = 'https://www.asaas.com/api/v3';
            }
        }
        return [
            'token' => $token,
            'url'   => rtrim($url, '/'),
        ];
    }

    private function buildPayload(int $subscriptionId): array
    {
        $defaults = [
            'municipalServiceId'    => defined('ASAAS_NFSE_MUNICIPAL_SERVICE_ID') ? ASAAS_NFSE_MUNICIPAL_SERVICE_ID : get_option('asaas_nfse_service_id', ''),
            'municipalServiceName'  => defined('ASAAS_NFSE_MUNICIPAL_SERVICE_NAME') ? ASAAS_NFSE_MUNICIPAL_SERVICE_NAME : get_option('asaas_nfse_service_name', 'Assinatura mensal para acesso e utilização da plataforma digital Imóvel Parceiro.'),
            'municipalServiceCode'  => defined('ASAAS_NFSE_MUNICIPAL_SERVICE_CODE') ? ASAAS_NFSE_MUNICIPAL_SERVICE_CODE : get_option('asaas_nfse_service_code', ''),
            'updateToEffectiveDate' => 'ON_PAYMENT_CONFIRMATION',
            'deductions'            => 0,
            'effectiveDatePeriod'   => 'ON_PAYMENT_CONFIRMATION',
            'taxes' => [
                'retainIss' => false,
                'cofins'    => 0,
                'csll'      => 0,
                'inss'      => 0,
                'ir'        => 0,
                'pis'       => 0,
                'iss'       => 0,
            ],
        ];
        $payload = apply_filters('asaas_nfse_invoice_settings_payload', $defaults, $subscriptionId);
        $payload['municipalServiceId']   = sanitize_text_field((string) $payload['municipalServiceId']);
        $payload['municipalServiceCode'] = sanitize_text_field((string) $payload['municipalServiceCode']);
        return $payload;
    }

    /**
     * @return array{success:bool,code:int,error:string}
     */
    private function callAsaas(string $asaasId, array $payload, array $credentials): array
    {
        $url = $credentials['url'] . '/subscriptions/' . rawurlencode($asaasId) . '/invoiceSettings';
        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $credentials['token'],
                'User-Agent'   => 'WooCommerce-Asaas-NFSe/1.0',
            ],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'code' => 0, 'error' => $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'code' => $code, 'error' => ''];
        }
        $msg = $data['errors'][0]['description'] ?? $data['message'] ?? $body;
        $msg = sanitize_text_field(substr((string) $msg, 0, 500));
        return ['success' => false, 'code' => $code, 'error' => "HTTP {$code}: {$msg}"];
    }

    private function getAsaasId(int $subscriptionId): string
    {
        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        foreach ($this->getAsaasMetaKeys() as $key) {
            // HPOS primeiro (wc_orders_meta), depois legado (wp_postmeta).
            $val = $subscription ? $subscription->get_meta($key) : get_post_meta($subscriptionId, $key, true);
            if (('' === $val || null === $val) && $subscription) {
                $val = get_post_meta($subscriptionId, $key, true);
            }
            $san = $this->sanitizeAsaasId($val);
            if ('' !== $san) {
                return $san;
            }
        }
        return '';
    }

    /** @return string[] */
    private function getAsaasMetaKeys(): array
    {
        return apply_filters('asaas_nfse_meta_keys', [
            self::META_ASAAS_ID,
            '_asaas_id',
            '_asaas_subscription',
            'asaas_subscription_id',
        ]);
    }

    private function sanitizeAsaasId($value): string
    {
        $v = sanitize_text_field((string) $value);
        if ('' !== $v && !preg_match('/^[a-zA-Z0-9_\-]{5,64}$/', $v)) {
            return '';
        }
        return $v;
    }

    private function storeError($subscription, int $subscriptionId, string $error): void
    {
        if ($subscription instanceof WC_Subscription) {
            $subscription->update_meta_data(self::META_ERROR, $error);
            $subscription->save();
            return;
        }
        update_post_meta($subscriptionId, self::META_ERROR, $error);
    }

    private function storeSuccess($subscription, int $subscriptionId): void
    {
        if ($subscription instanceof WC_Subscription) {
            $subscription->update_meta_data(self::META_FLAG, 'yes');
            $subscription->update_meta_data('_asaas_nfse_configured_at', current_time('mysql'));
            $subscription->delete_meta_data(self::META_ERROR);
            $subscription->save();
            return;
        }
        update_post_meta($subscriptionId, self::META_FLAG, 'yes');
        update_post_meta($subscriptionId, '_asaas_nfse_configured_at', current_time('mysql'));
        delete_post_meta($subscriptionId, self::META_ERROR);
    }

    private function log(string $message, string $level = 'info'): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, ['source' => self::LOG_SOURCE]);
        } else {
            error_log('[asaas-nfse] ' . $message);
        }
    }
}

add_action('plugins_loaded', [Asaas_Nfse_Subscription::class, 'instance']);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('asaas_nfse', function ($args, $assoc) {
        $id = (int) ($assoc['id'] ?? 0);
        if (!$id) {
            WP_CLI::error('Use --id=<subscription_id>');
        }
        $sub = function_exists('wcs_get_subscription') ? wcs_get_subscription($id) : false;
        if ($sub) {
            $sub->delete_meta_data(Asaas_Nfse_Subscription::META_FLAG);
            $sub->save();
        } else {
            delete_post_meta($id, Asaas_Nfse_Subscription::META_FLAG);
        }
        (new ReflectionMethod(Asaas_Nfse_Subscription::instance(), 'maybeConfigureById'))->invoke(Asaas_Nfse_Subscription::instance(), $id);
        WP_CLI::success('Tentativa enviada. Ver WC Status > Logs > asaas-nfse');
    });
}
