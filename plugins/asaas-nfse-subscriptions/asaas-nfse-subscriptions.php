<?php
/**
 * Plugin Name: Asaas NFS-e — Subscriptions invoiceSettings (Produção)
 * Description: Configura emissão 100% automática de NFS-e por assinatura Asaas (API v3) via WooCommerce Subscriptions. Aplica POST /v3/subscriptions/{id}/invoiceSettings (com fallback PUT) assim que o ID Asaas é vinculado, com effectiveDatePeriod=ON_PAYMENT_CONFIRMATION + receivedOnly=true. Idempotente, não bloqueia o checkout e nunca expõe credenciais.
 * Version: 2.0.0
 * Author: Imóvel Parceiro
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce, woocommerce-subscriptions
 *
 * Regra fiscal (serviço padrão da conta Asaas):
 * - Serviço: "Assinatura mensal para acesso e utilização da plataforma digital Imóvel Parceiro."
 * - municipalServiceCode: "10.05.01.001"
 * - Tributação nacional: 100501 (config da conta, sem campo dedicado neste DTO)
 * - NBS (taxes.nbsCode): "1.1001.21.00"
 * - ISS (taxes.iss): 0, retainIss=false, demais tributos 0
 *
 * Documentação de referência (API Asaas v3):
 * - POST /v3/subscriptions/{id}/invoiceSettings — "Create configuration for issuing invoices"
 * - PUT  /v3/subscriptions/{id}/invoiceSettings — "Update configuration for issuing invoices"
 *   (a nova configuração vale para as próximas cobranças ou para as que ainda não têm invoice criada)
 * - DTO de criação exige obrigatoriamente o objeto "taxes" (retainIss, iss, pis, cofins, csll, inss, ir).
 * - Emissão após pagamento: effectiveDatePeriod=ON_PAYMENT_CONFIRMATION + receivedOnly=true.
 * - Autenticação: header "access_token" com a API Key de PRODUÇÃO. Base prod: https://api.asaas.com/v3
 *   (legado https://www.asaas.com/api/v3). Chaves sandbox e produção são distintas e incompatíveis
 *   entre ambientes — o plugin prioriza o par consistente (token+endpoint) do gateway woo-asaas.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Asaas_Nfse_Subscription
{
    /**
     * Meta key do gateway woo-asaas com o ID da assinatura no Asaas.
     */
    public const META_ASAAS_ID = '_asaas_subscription_id';

    /**
     * Flag canônica de idempotência (nome exigido pela especificação).
     */
    public const META_FLAG = '_asaas_invoice_settings_configured';

    /**
     * Flag legada (v1.x). Mantida apenas para leitura/compatibilidade.
     */
    public const META_FLAG_LEGACY = '_asaas_nfse_configured';

    public const META_CONFIGURED_AT = '_asaas_invoice_settings_configured_at';
    public const META_ERROR         = '_asaas_nfse_last_error';
    public const META_PAYLOAD_HASH  = '_asaas_nfse_payload_hash';

    public const LOG_SOURCE = 'asaas-nfse';
    public const RETRY_HOOK = 'ip_asaas_nfse_retry';

    /**
     * Endpoint base oficial de PRODUÇÃO da API Asaas v3.
     */
    public const PROD_API_URL = 'https://api.asaas.com/v3';

    /**
     * Endpoint base oficial de SANDBOX da API Asaas v3.
     */
    public const SANDBOX_API_URL = 'https://api-sandbox.asaas.com/v3';

    // ------------------------------------------------------------------
    // Regra fiscal padrão (conta Asaas). Sobrescrevível via constantes
    // wp-config.php ou via filtro 'asaas_nfse_invoice_settings_payload'.
    // ------------------------------------------------------------------
    public const DEFAULT_MUNICIPAL_SERVICE_CODE = '10.05.01.001';
    public const DEFAULT_SERVICE_NAME           = 'Assinatura mensal para acesso e utilização da plataforma digital Imóvel Parceiro.';
    public const DEFAULT_OBSERVATIONS           = 'Assinatura mensal para acesso e utilização da plataforma digital Imóvel Parceiro.';
    public const DEFAULT_NBS_CODE               = '1.1001.21.00';
    public const DEFAULT_ISS                    = 0;

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
        // Gatilhos de vínculo do ID Asaas (compatíveis com HPOS + legado postmeta).
        add_action('added_post_meta', [$this, 'onMeta'], 10, 4);
        add_action('updated_post_meta', [$this, 'onMeta'], 10, 4);
        add_action('woocommerce_subscription_status_updated', [$this, 'onSubscriptionStatusUpdated'], 20, 3);
        add_action('woocommerce_before_order_object_save', [$this, 'onBeforeOrderSave'], 20, 2);
        add_action('woocommerce_subscription_status_active', [$this, 'onSubscriptionActive'], 20, 1);
        add_action('wcs_create_subscription', [$this, 'maybeConfigureById'], 20, 1);

        // Checkout: a assinatura nasce sem ID Asaas (o gateway grava depois).
        add_action('woocommerce_checkout_subscription_created', [$this, 'scheduleRetry'], 20, 1);

        // Gatilho mais precoce: logo após o gateway criar a assinatura no Asaas.
        // O $payment_data carrega externalReference = ID da WC_Subscription.
        add_filter('woocommerce_asaas_process_subscription_api_response', [$this, 'onApiSubscriptionResponse'], 20, 2);

        // Retentativas assíncronas (nunca no request do checkout).
        add_action(self::RETRY_HOOK, [$this, 'retryConfigure'], 10, 2);

        // Observabilidade manual em produção (metabox + ação admin com nonce).
        add_action('add_meta_boxes', [$this, 'addMetaBox'], 20, 2);
        add_action('admin_post_asaas_nfse_retry', [$this, 'handleManualRetry']);

        // Regras de checkout exigidas pelo Asaas para NFS-e (mantidas da v1.x).
        add_filter('woocommerce_checkout_fields', [$this, 'enforceAsaasFields'], 20);
        add_filter('woocommerce_billing_fields', [$this, 'enforceBillingFields'], 20);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateAsaasFields'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutStyles'], 20);

        // O gateway asaas-pix declara supports sem 'subscriptions'; habilita aqui.
        add_action('woocommerce_init', [$this, 'enablePixForSubscriptions'], 20);
    }

    // ==================================================================
    // GATILHOS
    // ==================================================================

    /**
     * Dispara imediatamente após o gateway criar a assinatura no Asaas.
     * Apenas agenda verificação futura — nunca executa HTTP no checkout.
     *
     * @param mixed $response Resposta da API Asaas (intocada).
     * @param array $paymentData Payload enviado ao Asaas.
     * @return mixed A resposta original, sem modificação.
     */
    public function onApiSubscriptionResponse($response, $paymentData)
    {
        try {
            $subscriptionId = isset($paymentData['externalReference']) ? (int) $paymentData['externalReference'] : 0;
            if ($subscriptionId > 0) {
                $this->scheduleRetry($subscriptionId, 0);
            }
        } catch (Throwable $e) {
            $this->log('onApiSubscriptionResponse exception: ' . $e->getMessage(), 'error');
        }

        return $response;
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
        $this->maybeConfigureById($subscription);
    }

    public function onBeforeOrderSave($order, $dataStore): void
    {
        if (!$order instanceof WC_Subscription) {
            return;
        }

        $this->maybeConfigureById($order);
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
                return;
            }

            // ID Asaas ainda ausente (gateway grava depois do checkout): agenda retries.
            $this->scheduleRetry($subscriptionId, 0);
        } catch (Throwable $e) {
            $this->log('maybeConfigureById exception: ' . $e->getMessage(), 'error');
        }
    }

    // ==================================================================
    // RETENTATIVAS (ACTION SCHEDULER / WP-CRON)
    // ==================================================================

    /**
     * Agenda verificação futura com backoff: 2min, 5min, 15min, 30min, depois 1h (máx. 12 tentativas).
     *
     * @param WC_Subscription|int $subscription Assinatura ou ID.
     */
    public function scheduleRetry($subscription, int $attempt = 0): void
    {
        $subscriptionId = $subscription instanceof WC_Subscription ? $subscription->get_id() : (int) $subscription;
        if ($subscriptionId <= 0 || $attempt >= 12) {
            return;
        }

        $delays = [120, 300, 900, 1800];
        $delay  = $delays[min($attempt, count($delays) - 1)];
        if ($attempt >= count($delays)) {
            $delay = 3600;
        }

        $args = [$subscriptionId, $attempt + 1];

        try {
            if (function_exists('as_schedule_single_action')) {
                if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::RETRY_HOOK, $args)) {
                    return;
                }

                as_schedule_single_action(time() + $delay, self::RETRY_HOOK, $args);
                return;
            }

            if (!wp_next_scheduled(self::RETRY_HOOK, $args)) {
                wp_schedule_single_event(time() + $delay, self::RETRY_HOOK, $args);
            }
        } catch (Throwable $e) {
            $this->log('scheduleRetry exception: ' . $e->getMessage(), 'error');
        }
    }

    public function retryConfigure(int $subscriptionId, int $attempt = 1): void
    {
        try {
            if ($subscriptionId <= 0) {
                return;
            }

            if ($this->isConfigured($subscriptionId)) {
                return;
            }

            $asaasId = $this->getAsaasId($subscriptionId);
            if ('' !== $asaasId) {
                $this->maybeConfigure($subscriptionId, $asaasId);
                return;
            }

            // Ainda sem ID (ex.: cartão recusado com rollback): reagenda.
            $this->scheduleRetry($subscriptionId, $attempt);
        } catch (Throwable $e) {
            $this->log('retryConfigure exception: ' . $e->getMessage(), 'error');
        }
    }

    // ==================================================================
    // NÚCLEO: CONFIGURAÇÃO DO invoiceSettings (IDEMPOTENTE, NÃO BLOQUEANTE)
    // ==================================================================

    private function maybeConfigure(int $subscriptionId, string $asaasId): void
    {
        try {
            // Idempotência: executa exatamente uma vez por assinatura.
            if ($this->isConfigured($subscriptionId)) {
                $this->log("Skip {$subscriptionId} já configurado asaas={$asaasId}");
                return;
            }

            // Lock anti-concorrência (webhooks duplicados / reloads).
            $lock = "asaas_nfse_lock_{$subscriptionId}";
            if (false !== get_transient($lock)) {
                return;
            }

            set_transient($lock, 1, 90);

            try {
                $credentials = $this->getCredentials();
                if ('' === $credentials['token'] || '' === $credentials['url']) {
                    $this->log("Credenciais ausentes sub={$subscriptionId}", 'error');
                    $this->storeError($subscriptionId, 'Credenciais Asaas não configuradas');
                    return;
                }

                $payload  = $this->buildPostPayload($subscriptionId);
                $response = $this->callAsaas($asaasId, $payload, $credentials);

                if ($response['success']) {
                    $this->storeSuccess($subscriptionId, $payload, $response['method']);
                    $this->log("OK sub={$subscriptionId} asaas={$asaasId} via={$response['method']}");
                    $this->addNote($subscriptionId, 'NFS-e automática configurada no Asaas (invoiceSettings). Emissão após confirmação do pagamento.');
                } else {
                    $this->storeError($subscriptionId, $response['error']);
                    $this->log("FAIL sub={$subscriptionId} asaas={$asaasId} err={$response['error']} http={$response['code']}", 'error');
                    $this->addNote($subscriptionId, 'Falha ao configurar NFS-e automática no Asaas. Verifique WC > Status > Logs > asaas-nfse. O checkout NÃO foi afetado.');
                    // Falha transitória (5xx, timeout, 429): reagenda para retentativa.
                    if ($this->isRetryable($response['code'])) {
                        $this->scheduleRetry($subscriptionId, 0);
                    }
                }
            } finally {
                delete_transient($lock);
            }
        } catch (Throwable $e) {
            // NUNCA propaga exceção para o checkout.
            $this->log('maybeConfigure exception sub=' . $subscriptionId . ': ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Erros transitórios que justificam retentativa automática.
     */
    private function isRetryable(int $httpCode): bool
    {
        return 0 === $httpCode || 429 === $httpCode || ($httpCode >= 500 && $httpCode <= 599);
    }

    // ==================================================================
    // PAYLOAD (DTO REAL DA API V3)
    // ==================================================================

    /**
     * Payload de CRIAÇÃO (POST). Campos conforme SubscriptionConfigureInvoiceRequestDTO.
     * Emissão automática após pagamento: effectiveDatePeriod=ON_PAYMENT_CONFIRMATION + receivedOnly=true.
     *
     * NOTA: a spec interna sugeria campos "description", "issTax" e "updateToEffectiveDate",
     * que NÃO existem no DTO oficial. O mapeamento correto é:
     * - description (serviço) -> municipalServiceName + observations
     * - issTax (0%)            -> taxes.iss=0 + taxes.retainIss=false
     * - updateToEffectiveDate  -> effectiveDatePeriod=ON_PAYMENT_CONFIRMATION
     * - Código nacional 100501 -> configuração da conta (sem campo neste DTO)
     * - Código NBS             -> taxes.nbsCode
     *
     * @return array<string, mixed>
     */
    private function buildPostPayload(int $subscriptionId): array
    {
        $payload = [
            'municipalServiceCode' => $this->fiscal('code', self::DEFAULT_MUNICIPAL_SERVICE_CODE),
            'municipalServiceName' => $this->fiscal('name', self::DEFAULT_SERVICE_NAME),
            'deductions'           => 0,
            'effectiveDatePeriod'  => 'ON_PAYMENT_CONFIRMATION',
            'receivedOnly'         => true,
            'observations'         => $this->fiscal('observations', self::DEFAULT_OBSERVATIONS),
            'updatePayment'        => false,
            'taxes'                => $this->buildTaxes(),
        ];

        // municipalServiceId é opcional e identifica o serviço já cadastrado na conta.
        // Só é enviado quando explicitamente configurado (evita ID stale de outro ambiente).
        $serviceId = $this->fiscal('id', '');
        if ('' !== $serviceId) {
            $payload['municipalServiceId'] = $serviceId;
        }

        /**
         * Filtro de escape para casos excepcionais (ex.: outro municipalServiceCode por filial).
         *
         * @param array<string, mixed> $payload Payload de criação.
         * @param int $subscriptionId ID da WC_Subscription.
         */
        $payload = apply_filters('asaas_nfse_invoice_settings_payload', $payload, $subscriptionId);

        // Reimposição de segurança: a regra de emissão NUNCA pode ser alterada via filtro.
        $payload['effectiveDatePeriod'] = 'ON_PAYMENT_CONFIRMATION';
        $payload['receivedOnly']        = true;

        $payload['municipalServiceCode'] = sanitize_text_field((string) $payload['municipalServiceCode']);
        $payload['municipalServiceName'] = sanitize_text_field((string) ($payload['municipalServiceName'] ?? ''));
        if (isset($payload['municipalServiceId'])) {
            $payload['municipalServiceId'] = sanitize_text_field((string) $payload['municipalServiceId']);
        }

        return $payload;
    }

    /**
     * Payload de ATUALIZAÇÃO (PUT). O DTO de update NÃO aceita campos municipais —
     * apenas deductions, effectiveDatePeriod, receivedOnly, observations e taxes.
     *
     * @param array<string, mixed> $postPayload Payload de criação já filtrado.
     * @return array<string, mixed>
     */
    private function buildPutPayload(array $postPayload): array
    {
        $put = [
            'deductions'          => $postPayload['deductions'] ?? 0,
            'effectiveDatePeriod' => 'ON_PAYMENT_CONFIRMATION',
            'receivedOnly'        => true,
            'observations'        => isset($postPayload['observations']) ? sanitize_text_field((string) $postPayload['observations']) : '',
            'taxes'               => $postPayload['taxes'] ?? $this->buildTaxes(),
        ];

        return apply_filters('asaas_nfse_invoice_settings_put_payload', $put);
    }

    /**
     * Bloco fiscal obrigatório do DTO (todos os tributos zerados, ISS 0%).
     *
     * @return array<string, mixed>
     */
    private function buildTaxes(): array
    {
        $iss = $this->fiscalIss();

        return [
            'retainIss' => false,
            'iss'       => $iss,
            'pis'       => 0,
            'cofins'    => 0,
            'csll'      => 0,
            'inss'      => 0,
            'ir'        => 0,
            'nbsCode'   => $this->fiscal('nbs', self::DEFAULT_NBS_CODE),
        ];
    }

    /**
     * Lê um parâmetro fiscal: constante wp-config > option > padrão da spec.
     */
    private function fiscal(string $key, string $default): string
    {
        $map = [
            'code'         => ['ASAAS_NFSE_MUNICIPAL_SERVICE_CODE', 'asaas_nfse_service_code'],
            'name'         => ['ASAAS_NFSE_MUNICIPAL_SERVICE_NAME', 'asaas_nfse_service_name'],
            'id'           => ['ASAAS_NFSE_MUNICIPAL_SERVICE_ID', 'asaas_nfse_service_id'],
            'observations' => ['ASAAS_NFSE_OBSERVATIONS', 'asaas_nfse_observations'],
            'nbs'          => ['ASAAS_NFSE_NBS_CODE', 'asaas_nfse_nbs_code'],
        ];

        if (!isset($map[$key])) {
            return $default;
        }

        [$const, $opt] = $map[$key];

        if (defined($const) && '' !== trim((string) constant($const))) {
            return trim((string) constant($const));
        }

        // Alias legado de option (v1.x).
        if ('name' === $key) {
            $legacy = trim((string) get_option('asaas_nfse_service_name', ''));
            if ('' !== $legacy) {
                return $legacy;
            }
        }

        $optVal = trim((string) get_option($opt, ''));
        if ('' !== $optVal) {
            return $optVal;
        }

        return $default;
    }

    private function fiscalIss(): float
    {
        if (defined('ASAAS_NFSE_ISS') && is_numeric(constant('ASAAS_NFSE_ISS'))) {
            return (float) constant('ASAAS_NFSE_ISS');
        }

        $opt = get_option('asaas_nfse_iss', null);
        if (is_numeric($opt)) {
            return (float) $opt;
        }

        return (float) self::DEFAULT_ISS;
    }

    // ==================================================================
    // CREDENCIAIS (ISOLADAS, PAR CONSISTENTE TOKEN+ENDPOINT)
    // ==================================================================

    /**
     * @return array{token:string,url:string}
     */
    private function getCredentials(): array
    {
        // Regra de ouro: chave + endpoint SEMPRE do mesmo ambiente.
        // Misturar chave prod com URL sandbox devolve 401. Por isso o par
        // consistente do gateway woo-asaas tem prioridade sobre constantes avulsas.
        foreach (['woocommerce_asaas-credit-card_settings', 'woocommerce_asaas-ticket_settings', 'woocommerce_asaas-pix_settings'] as $optKey) {
            $gw = get_option($optKey, []);
            if (!is_array($gw) || empty($gw['api_key'])) {
                continue;
            }

            $gwToken = trim((string) $gw['api_key']);
            $gwUrl   = !empty($gw['endpoint']) ? trim((string) $gw['endpoint']) : self::PROD_API_URL;

            return [
                'token' => $gwToken,
                'url'   => rtrim($this->normalizeApiUrl($gwUrl, $gwToken), '/'),
            ];
        }

        // Fallback: constantes seguras do wp-config.php / options.
        $constToken = defined('ASAAS_API_KEY') ? trim((string) ASAAS_API_KEY) : '';
        $constUrl   = defined('ASAAS_API_URL') ? trim((string) ASAAS_API_URL) : '';
        $optToken   = trim((string) get_option('asaas_api_key', ''));
        $optUrl     = trim((string) get_option('asaas_api_url', ''));
        $token      = '' !== $constToken ? $constToken : $optToken;
        $url        = '' !== $constUrl ? $constUrl : ('' !== $optUrl ? $optUrl : self::PROD_API_URL);

        return [
            'token' => $token,
            'url'   => rtrim($this->normalizeApiUrl($url, $token), '/'),
        ];
    }

    /**
     * Normaliza a base URL e autocorrige divergência sandbox<>produção.
     * Legadas: https://www.asaas.com/api/v3 / https://sandbox.asaas.com/api/v3
     * Atuais:  https://api.asaas.com/v3 / https://api-sandbox.asaas.com/v3
     */
    private function normalizeApiUrl(string $url, string $token): string
    {
        $url = trim($url);
        if ('' === $url) {
            $url = self::PROD_API_URL;
        }

        $url = str_replace('https://www.asaas.com/api/v3', self::PROD_API_URL, $url);
        $url = str_replace('https://sandbox.asaas.com/api/v3', self::SANDBOX_API_URL, $url);

        $isSandboxToken = (stripos($token, 'sandbox') !== false || stripos($token, '$aact_test') !== false || stripos($token, 'hmlg') !== false);
        $isProdToken    = (stripos($token, '$aact_prod') !== false);
        $isSandboxUrl   = (stripos($url, 'sandbox') !== false);
        $isProdUrl      = (stripos($url, 'api.asaas.com') !== false && !$isSandboxUrl);

        if ($isProdToken && $isSandboxUrl) {
            $this->log('getCredentials: chave PROD com URL sandbox — autocorrigido para produção', 'warning');
            return self::PROD_API_URL;
        }

        if ($isSandboxToken && $isProdUrl) {
            $this->log('getCredentials: chave SANDBOX com URL prod — autocorrigido para sandbox', 'warning');
            return self::SANDBOX_API_URL;
        }

        return $url;
    }

    // ==================================================================
    // HTTP (POST COM FALLBACK PUT + GET DE VERIFICAÇÃO)
    // ==================================================================

    /**
     * Cria a configuração (POST); se já existir, atualiza (PUT).
     *
     * @param array<string, mixed> $payload Payload de criação.
     * @param array{token:string,url:string} $credentials Credenciais.
     * @return array{success:bool,code:int,error:string,method:string}
     */
    private function callAsaas(string $asaasId, array $payload, array $credentials): array
    {
        $url  = $credentials['url'] . '/subscriptions/' . rawurlencode($asaasId) . '/invoiceSettings';
        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $credentials['token'],
                'User-Agent'   => 'WooCommerce-Asaas-NFSe/2.0',
            ],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'code' => 0, 'error' => $this->sanitizeError($response->get_error_message()), 'method' => 'POST'];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'code' => $code, 'error' => '', 'method' => 'POST'];
        }

        $body = (string) wp_remote_retrieve_body($response);

        // Configuração já existente: atualiza em vez de falhar.
        if (400 === $code && $this->looksAlreadyConfigured($body)) {
            $this->log("POST devolveu 400 (config existente) sub asaas={$asaasId} — tentando PUT");
            return $this->callAsaasPut($url, $this->buildPutPayload($payload), $credentials);
        }

        return ['success' => false, 'code' => $code, 'error' => $this->extractApiError($code, $body), 'method' => 'POST'];
    }

    /**
     * @param array<string, mixed> $putPayload Payload de atualização.
     * @param array{token:string,url:string} $credentials Credenciais.
     * @return array{success:bool,code:int,error:string,method:string}
     */
    private function callAsaasPut(string $url, array $putPayload, array $credentials): array
    {
        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $credentials['token'],
                'User-Agent'   => 'WooCommerce-Asaas-NFSe/2.0',
            ],
            'body'    => wp_json_encode($putPayload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'code' => 0, 'error' => $this->sanitizeError($response->get_error_message()), 'method' => 'PUT'];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'code' => $code, 'error' => '', 'method' => 'PUT'];
        }

        return ['success' => false, 'code' => $code, 'error' => $this->extractApiError($code, (string) wp_remote_retrieve_body($response)), 'method' => 'PUT'];
    }

    /**
     * Leitura da configuração atual (usada pelo WP-CLI verify e pela metabox).
     *
     * @param array{token:string,url:string} $credentials Credenciais.
     * @return array{code:int,body:string}
     */
    private function getInvoiceSettings(string $asaasId, array $credentials): array
    {
        $url      = $credentials['url'] . '/subscriptions/' . rawurlencode($asaasId) . '/invoiceSettings';
        $response = wp_remote_get($url, [
            'headers' => [
                'access_token' => $credentials['token'],
                'User-Agent'   => 'WooCommerce-Asaas-NFSe/2.0',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['code' => 0, 'body' => $this->sanitizeError($response->get_error_message())];
        }

        return [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => substr((string) wp_remote_retrieve_body($response), 0, 2000),
        ];
    }

    private function looksAlreadyConfigured(string $body): bool
    {
        return (bool) preg_match('/alread|exist|duplicat|configur/i', $body);
    }

    /**
     * Extrai mensagem de erro da API sem vazar dados sensíveis.
     */
    private function extractApiError(int $code, string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data)) {
            $msg = $data['errors'][0]['description'] ?? $data['errors'][0]['code'] ?? $data['message'] ?? '';
            if ('' !== (string) $msg) {
                return $this->sanitizeError("HTTP {$code}: {$msg}");
            }
        }

        $fallback = trim($body);
        if ('' === $fallback) {
            return "HTTP {$code}: resposta vazia do Asaas";
        }

        return $this->sanitizeError("HTTP {$code}: {$fallback}");
    }

    private function sanitizeError(string $message): string
    {
        $message = sanitize_text_field($message);
        if (strlen($message) > 500) {
            $message = substr($message, 0, 500);
        }

        return $message;
    }

    // ==================================================================
    // LEITURA DE METADADOS / IDEMPOTÊNCIA
    // ==================================================================

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

    /**
     * @return string[]
     */
    private function getAsaasMetaKeys(): array
    {
        return apply_filters('asaas_nfse_meta_keys', [
            self::META_ASAAS_ID,
            '_asaas_id',
            '_asaas_subscription',
            'asaas_subscription_id',
        ]);
    }

    /**
     * IDs Asaas de assinatura têm formato "sub_xxx" (letras, números, _ e -).
     */
    private function sanitizeAsaasId($value): string
    {
        $v = sanitize_text_field((string) $value);
        if ('' !== $v && !preg_match('/^[a-zA-Z0-9_\-]{5,64}$/', $v)) {
            return '';
        }

        return $v;
    }

    /**
     * Flag canônica da spec (+ alias legado v1.x para compatibilidade).
     */
    private function isConfigured(int $subscriptionId): bool
    {
        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        if ($subscription) {
            return 'yes' === $subscription->get_meta(self::META_FLAG)
                || 'yes' === $subscription->get_meta(self::META_FLAG_LEGACY);
        }

        return 'yes' === get_post_meta($subscriptionId, self::META_FLAG, true)
            || 'yes' === get_post_meta($subscriptionId, self::META_FLAG_LEGACY, true);
    }

    /**
     * @param array<string, mixed> $payload Payload aplicado (para hash de auditoria).
     */
    private function storeSuccess(int $subscriptionId, array $payload, string $method): void
    {
        $hash = md5(wp_json_encode($payload) . '|' . $method);
        $now  = current_time('mysql');

        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        if ($subscription instanceof WC_Subscription) {
            $subscription->update_meta_data(self::META_FLAG, 'yes');
            $subscription->update_meta_data(self::META_FLAG_LEGACY, 'yes');
            $subscription->update_meta_data(self::META_CONFIGURED_AT, $now);
            $subscription->update_meta_data(self::META_PAYLOAD_HASH, $hash);
            $subscription->delete_meta_data(self::META_ERROR);
            $subscription->save();
            return;
        }

        update_post_meta($subscriptionId, self::META_FLAG, 'yes');
        update_post_meta($subscriptionId, self::META_FLAG_LEGACY, 'yes');
        update_post_meta($subscriptionId, self::META_CONFIGURED_AT, $now);
        update_post_meta($subscriptionId, self::META_PAYLOAD_HASH, $hash);
        delete_post_meta($subscriptionId, self::META_ERROR);
    }

    private function storeError(int $subscriptionId, string $error): void
    {
        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        if ($subscription instanceof WC_Subscription) {
            $subscription->update_meta_data(self::META_ERROR, $error);
            $subscription->save();
            return;
        }

        update_post_meta($subscriptionId, self::META_ERROR, $error);
    }

    private function addNote(int $subscriptionId, string $note): void
    {
        try {
            $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
            if ($subscription instanceof WC_Subscription && method_exists($subscription, 'add_order_note')) {
                $subscription->add_order_note($note);
            }
        } catch (Throwable $e) {
            $this->log('addNote exception: ' . $e->getMessage(), 'error');
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        // NUNCA registrar access_token ou dados de cartão/portador aqui.
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, ['source' => self::LOG_SOURCE]);
        } else {
            error_log('[asaas-nfse] ' . $message);
        }
    }

    // ==================================================================
    // ADMIN (METABOX DE OBSERVABILIDADE + RETENTATIVA MANUAL)
    // ==================================================================

    public function addMetaBox(string $postType, $post): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screenId = $screen ? (string) $screen->id : '';
        $isSubscriptionScreen = ('shop_subscription' === $postType)
            || (false !== strpos($screenId, 'shop_subscription'));

        if (!$isSubscriptionScreen) {
            return;
        }

        add_meta_box(
            'asaas-nfse-box',
            'Asaas NFS-e automática',
            [$this, 'renderMetaBox'],
            null,
            'side',
            'default'
        );
    }

    public function renderMetaBox($post): void
    {
        $subscriptionId = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
        if (function_exists('wcs_get_subscription')) {
            $sub = wcs_get_subscription($subscriptionId);
            if ($sub) {
                $subscriptionId = $sub->get_id();
            }
        }

        $asaasId    = $this->getAsaasId($subscriptionId);
        $configured = $this->isConfigured($subscriptionId) ? 'yes' : 'no';
        $error      = get_post_meta($subscriptionId, self::META_ERROR, true);
        if (function_exists('wcs_get_subscription')) {
            $sub = wcs_get_subscription($subscriptionId);
            if ($sub) {
                $metaErr = $sub->get_meta(self::META_ERROR);
                if ('' !== (string) $metaErr) {
                    $error = $metaErr;
                }
            }
        }

        echo '<p><strong>Asaas ID:</strong> ' . esc_html($asaasId !== '' ? $asaasId : '—') . '</p>';
        echo '<p><strong>invoiceSettings:</strong> ' . ('yes' === $configured ? 'configurado' : 'pendente') . '</p>';
        if ('' !== (string) $error) {
            echo '<p><strong>Último erro:</strong> ' . esc_html((string) $error) . '</p>';
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=asaas_nfse_retry&subscription_id=' . $subscriptionId),
            'asaas_nfse_retry_' . $subscriptionId
        );
        echo '<p><a class="button" href="' . esc_url($url) . '">Reconfigurar agora</a></p>';
        echo '<p class="description">Logs: WooCommerce &gt; Status &gt; Logs &gt; asaas-nfse.</p>';
    }

    /**
     * Retentativa manual via admin (cap + nonce + sanitização). Nunca expõe token.
     */
    public function handleManualRetry(): void
    {
        $subscriptionId = isset($_GET['subscription_id']) ? absint($_GET['subscription_id']) : 0;
        if (!$subscriptionId) {
            wp_die('Assinatura inválida.');
        }

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            wp_die('Sem permissão.');
        }

        check_admin_referer('asaas_nfse_retry_' . $subscriptionId);

        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
        if ($subscription instanceof WC_Subscription) {
            $subscription->delete_meta_data(self::META_FLAG);
            $subscription->delete_meta_data(self::META_FLAG_LEGACY);
            $subscription->save();
        } else {
            delete_post_meta($subscriptionId, self::META_FLAG);
            delete_post_meta($subscriptionId, self::META_FLAG_LEGACY);
        }

        $this->maybeConfigureById($subscriptionId);

        $redirect = add_query_arg('asaas_nfse', $this->isConfigured($subscriptionId) ? 'ok' : 'fail', wp_get_referer() ?: admin_url());
        wp_safe_redirect($redirect);
        exit;
    }

    // ==================================================================
    // CHECKOUT (CAMPOS EXIGIDOS PELO ASAAS PARA NFS-e) — mantido da v1.x
    // ==================================================================

    public function enablePixForSubscriptions(): void
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        if (empty($gateways['asaas-pix']) || !is_object($gateways['asaas-pix'])) {
            return;
        }

        $pix = $gateways['asaas-pix'];
        if (!isset($pix->supports) || !is_array($pix->supports)) {
            return;
        }

        foreach (['subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'multiple_subscriptions', 'gateway_scheduled_payments'] as $feature) {
            if (!in_array($feature, $pix->supports, true)) {
                $pix->supports[] = $feature;
            }
        }
    }

    public function enforceAsaasFields($fields)
    {
        // Asaas exige para NFS-e: nome/sobrenome, email, cpf/cnpj, cep, endereço, número, bairro, cidade, estado, telefone.
        if (isset($fields['billing']['billing_persontype'])) {
            $fields['billing']['billing_persontype']['required'] = true;
        }

        if (isset($fields['billing']['billing_neighborhood'])) {
            $fields['billing']['billing_neighborhood']['required'] = true;
            $fields['billing']['billing_neighborhood']['label']    = 'Bairro *';
        }

        if (isset($fields['billing']['billing_number'])) {
            $fields['billing']['billing_number']['required']    = true;
            $fields['billing']['billing_number']['label']       = 'Número *';
            $fields['billing']['billing_number']['placeholder'] = 'Ex: 405';
        }

        if (isset($fields['billing']['billing_cellphone'])) {
            $fields['billing']['billing_cellphone']['required'] = true;
        }

        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['label']    = 'Celular / Telefone *';
        }

        if (isset($fields['billing']['billing_cpf'])) {
            $fields['billing']['billing_cpf']['custom_attributes'] = ['data-asaas' => 'cpf'];
        }

        if (isset($fields['billing']['billing_cnpj'])) {
            $fields['billing']['billing_cnpj']['custom_attributes'] = ['data-asaas' => 'cnpj'];
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
        $persontype   = isset($_POST['billing_persontype']) ? sanitize_text_field($_POST['billing_persontype']) : '';
        $cpf          = isset($_POST['billing_cpf']) ? preg_replace('/\D/', '', (string) $_POST['billing_cpf']) : '';
        $cnpj         = isset($_POST['billing_cnpj']) ? preg_replace('/\D/', '', (string) $_POST['billing_cnpj']) : '';
        $phone        = isset($_POST['billing_phone']) ? preg_replace('/\D/', '', (string) $_POST['billing_phone']) : '';
        $cell         = isset($_POST['billing_cellphone']) ? preg_replace('/\D/', '', (string) $_POST['billing_cellphone']) : '';
        $number       = isset($_POST['billing_number']) ? trim((string) $_POST['billing_number']) : '';
        $neighborhood = isset($_POST['billing_neighborhood']) ? trim((string) $_POST['billing_neighborhood']) : '';

        if ('2' === $persontype) {
            if (strlen((string) $cnpj) !== 14) {
                $errors->add('validation', 'CNPJ obrigatório e deve conter 14 dígitos para Pessoa Jurídica (Asaas).');
            } elseif (!self::isValidCnpj((string) $cnpj)) {
                // Evita erro E0207 e congêneres no portal nacional da NFS-e.
                $errors->add('validation', 'CNPJ inválido. Confira os números digitados (dígitos verificadores não conferem).');
            }
        } else {
            if (strlen((string) $cpf) !== 11) {
                $errors->add('validation', 'CPF obrigatório e deve conter 11 dígitos para Pessoa Física (Asaas).');
            } elseif (!self::isValidCpf((string) $cpf)) {
                // Evita erro E0207 ("CPF do tomador não encontrado") no portal nacional da NFS-e.
                $errors->add('validation', 'CPF inválido. Confira os números digitados (dígitos verificadores não conferem).');
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

    /**
     * Valida CPF pelos dígitos verificadores (módulo 11). Rejeita sequências
     * conhecidamente inválidas (ex.: 111.111.111-11). Impede que CPFs com erro
     * de digitação cheguem ao Asaas e falhem no portal nacional (ex.: E0207).
     */
    private static function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || 1 === count(array_unique(str_split($cpf)))) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CNPJ pelos dígitos verificadores (módulo 11). Rejeita sequências
     * conhecidamente inválidas (ex.: 00.000.000/0000-00).
     */
    private static function isValidCnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || 1 === count(array_unique(str_split($cnpj)))) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($t = 0; $t < 2; $t++) {
            $weights = 0 === $t ? $weights1 : $weights2;
            $len     = 0 === $t ? 12 : 13;
            $sum     = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$i];
            }

            $rest  = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ((int) $cnpj[$len] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public function enqueueCheckoutStyles()
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        $css = plugin_dir_url(__FILE__) . 'assets/css/asaas-checkout.css';
        wp_register_style('asaas-checkout', $css, [], '2.0.0');
        wp_enqueue_style('asaas-checkout');
        $js = "(function(){document.addEventListener('DOMContentLoaded',function(){var pt=document.getElementById('billing_persontype');function t(){var v=pt?pt.value:'';var cpf=document.getElementById('billing_cpf_field');var cnpj=document.getElementById('billing_cnpj_field');if(!cpf||!cnpj)return;cpf.style.display=v==='2'?'none':'';cnpj.style.display=v==='2'?'':'none';}if(pt){pt.addEventListener('change',t);t();}})})();";
        wp_add_inline_script('jquery', $js);
    }

    // ==================================================================
    // WP-CLI (VALIDAÇÃO EM PRODUÇÃO)
    // ==================================================================

    /**
     * GET de verificação: lê a configuração atual no Asaas.
     */
    public function cliVerify(int $subscriptionId): void
    {
        $asaasId = $this->getAsaasId($subscriptionId);
        if ('' === $asaasId) {
            WP_CLI::error("Assinatura {$subscriptionId} sem asaas_subscription_id vinculado.");
        }

        $credentials = $this->getCredentials();
        if ('' === $credentials['token']) {
            WP_CLI::error('Credenciais Asaas ausentes (verifique o gateway ou ASAAS_API_KEY).');
        }

        // Mostra apenas o host da URL — nunca o token.
        WP_CLI::log('Endpoint: ' . (string) wp_parse_url($credentials['url'], PHP_URL_HOST) . '/subscriptions/{id}/invoiceSettings');
        $result = $this->getInvoiceSettings($asaasId, $credentials);
        WP_CLI::log('HTTP ' . $result['code']);
        WP_CLI::log($result['body']);
    }

    public function cliConfigure(int $subscriptionId, bool $force = false): void
    {
        if ($force) {
            $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscriptionId) : false;
            if ($subscription instanceof WC_Subscription) {
                $subscription->delete_meta_data(self::META_FLAG);
                $subscription->delete_meta_data(self::META_FLAG_LEGACY);
                $subscription->save();
            } else {
                delete_post_meta($subscriptionId, self::META_FLAG);
                delete_post_meta($subscriptionId, self::META_FLAG_LEGACY);
            }
        }

        $this->maybeConfigureById($subscriptionId);

        if ($this->isConfigured($subscriptionId)) {
            WP_CLI::success("Assinatura {$subscriptionId} configurada. Ver WC Status > Logs > asaas-nfse.");
        }

        WP_CLI::error("Assinatura {$subscriptionId} NÃO configurada. Ver WC Status > Logs > asaas-nfse.");
    }
}

add_action('plugins_loaded', [Asaas_Nfse_Subscription::class, 'instance']);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('asaas_nfse configure', function ($args, $assocArgs) {
        $id = (int) ($assocArgs['id'] ?? 0);
        if (!$id) {
            WP_CLI::error('Use: wp asaas_nfse configure --id=<subscription_id> [--force]');
        }

        Asaas_Nfse_Subscription::instance()->cliConfigure($id, !empty($assocArgs['force']));
    });

    WP_CLI::add_command('asaas_nfse verify', function ($args, $assocArgs) {
        $id = (int) ($assocArgs['id'] ?? 0);
        if (!$id) {
            WP_CLI::error('Use: wp asaas_nfse verify --id=<subscription_id>');
        }

        Asaas_Nfse_Subscription::instance()->cliVerify($id);
    });
}
