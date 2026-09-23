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
        add_action('added_post_meta', [$this, 'onMeta'], 10, 4);
        add_action('updated_post_meta', [$this, 'onMeta'], 10, 4);
        add_action('woocommerce_subscription_status_active', [$this, 'onSubscriptionActive'], 20, 1);
        add_action('wcs_create_subscription', [$this, 'maybeConfigureById'], 20, 1);
        add_filter('woocommerce_checkout_fields', [$this, 'requireNeighborhoodAndNumber'], 20);
        add_filter('woocommerce_billing_fields', [$this, 'requireBillingFields'], 20);
    }

    public function requireNeighborhoodAndNumber($fields)
    {
        if (isset($fields['billing']['billing_neighborhood'])) {
            $fields['billing']['billing_neighborhood']['required'] = true;
        }
        if (isset($fields['billing']['billing_number'])) {
            $fields['billing']['billing_number']['required'] = true;
        }
        // Houzez/Woo extra fields: billing_address_1 já é obrigatório, garante bairro/número
        return $fields;
    }

    public function requireBillingFields($fields)
    {
        if (isset($fields['billing_neighborhood'])) {
            $fields['billing_neighborhood']['required'] = true;
        }
        if (isset($fields['billing_number'])) {
            $fields['billing_number']['required'] = true;
        }
        return $fields;
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
        if ('yes' === get_post_meta($subscriptionId, self::META_FLAG, true)) {
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
            $this->storeError($subscriptionId, 'Credenciais Asaas não configuradas');
            return;
        }

        $payload = $this->buildPayload($subscriptionId);
        $response = $this->callAsaas($asaasId, $payload, $credentials);

        if ($response['success']) {
            update_post_meta($subscriptionId, self::META_FLAG, 'yes');
            update_post_meta($subscriptionId, '_asaas_nfse_configured_at', current_time('mysql'));
            delete_post_meta($subscriptionId, self::META_ERROR);
            $this->log("OK sub={$subscriptionId} asaas={$asaasId} payload=" . wp_json_encode($payload));
        } else {
            $this->storeError($subscriptionId, $response['error']);
            $this->log("FAIL sub={$subscriptionId} asaas={$asaasId} err={$response['error']} http={$response['code']}", 'error');
        }
    }

    /**
     * @return array{token:string,url:string}
     */
    private function getCredentials(): array
    {
        $constToken = defined('ASAAS_API_KEY') ? trim((string) ASAAS_API_KEY) : '';
        $constUrl   = defined('ASAAS_API_URL') ? trim((string) ASAAS_API_URL) : '';
        $token = '' !== $constToken ? $constToken : trim((string) get_option('asaas_api_key', ''));
        $url   = '' !== $constUrl ? $constUrl : trim((string) get_option('asaas_api_url', 'https://www.asaas.com/api/v3'));
        if ('' === $url) {
            $url = 'https://www.asaas.com/api/v3';
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
        foreach ($this->getAsaasMetaKeys() as $key) {
            $val = get_post_meta($subscriptionId, $key, true);
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

    private function storeError(int $subscriptionId, string $error): void
    {
        update_post_meta($subscriptionId, self::META_ERROR, $error);
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
        delete_post_meta($id, Asaas_Nfse_Subscription::META_FLAG);
        (new ReflectionMethod(Asaas_Nfse_Subscription::instance(), 'maybeConfigureById'))->invoke(Asaas_Nfse_Subscription::instance(), $id);
        WP_CLI::success('Tentativa enviada. Ver WC Status > Logs > asaas-nfse');
    });
}
