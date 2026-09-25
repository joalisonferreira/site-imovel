<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página dedicada de pagamento Pix (?pagamento-pix=1&order=ID&key=CHAVE).
 *
 * Fluxo:
 *  1. Após assinar via Pix, o cliente é redirecionado do "order-received"
 *     para esta página (filtro woocommerce_get_checkout_order_received_url).
 *  2. A página exibe QR code + código copia e cola e fica consultando o
 *     status do pedido via AJAX a cada 5s (polling).
 *  3. Quando o webhook do Asaas confirma o pagamento (pedido sai de
 *     "pendente"), a página mostra "Pagamento confirmado!" e redireciona
 *     para o início do dashboard automaticamente.
 *
 * Segurança: exige login, exige que o pedido pertença ao usuário logado e
 * exige a chave do pedido (order key) na URL.
 *
 * Sem mudanças de banco: nenhum option/tabela novo (só transients do WP).
 */
class Imovel_Parceiro_Pix_Payment_Page {

    const AJAX_ACTION  = 'imovel_pix_status';
    const NONCE_ACTION = 'imovel-pix-status';
    const POLL_MS      = 5000;

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_render' ), 5 );
        add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'redirect_order_received' ), 20, 2 );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_status' ) );
    }

    /**
     * URL da página de pagamento para um pedido.
     *
     * @param WC_Order $order
     * @return string
     */
    public static function get_payment_url( $order ) {
        return add_query_arg(
            array(
                'pagamento-pix' => 1,
                'order'         => $order->get_id(),
                'key'           => $order->get_order_key(),
            ),
            home_url( '/' )
        );
    }

    /**
     * URL do início do dashboard (destino após confirmação).
     *
     * @return string
     */
    public static function get_dashboard_url() {
        if ( function_exists( 'houzez_get_template_link_2' ) ) {
            $url = houzez_get_template_link_2( 'template/user_dashboard.php' );
            if ( ! empty( $url ) ) {
                return $url;
            }
        }
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        return home_url( '/' );
    }

    /**
     * Após o checkout, leva pedidos Pix pendentes para a página dedicada.
     */
    public function redirect_order_received( $url, $order ) {
        if ( is_admin() || ! $order instanceof WC_Order ) {
            return $url;
        }
        if ( 'asaas-pix' !== $order->get_payment_method() ) {
            return $url;
        }
        if ( ! $order->needs_payment() ) {
            return $url;
        }
        $pix = self::extract_pix( $order );
        if ( empty( $pix['payload'] ) || empty( $pix['image_src'] ) ) {
            return $url; // Sem QR salvo: mantém o thank-you nativo.
        }
        return self::get_payment_url( $order );
    }

    /**
     * Extrai payload/QR/expiração do meta __ASAAS_ORDER do pedido.
     *
     * @param WC_Order $order
     * @return array{payload:string,image_src:string,expires:string}
     */
    public static function extract_pix( $order ) {
        $out  = array( 'payload' => '', 'image_src' => '', 'expires' => '' );
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
            return $out;
        }
        // O meta __ASAAS_ORDER do woo-asaas é uma string JSON que decodifica
        // para objeto com payload (copia-e-cola), encodedImage (QR em base64)
        // e expirationDate — mesmos campos lidos pelo painel do dashboard
        // (extract_asaas_meta). Mantém os nomes legados qrcode* como fallback.
        $raw = $order->get_meta( '__ASAAS_ORDER' );
        if ( '' === (string) $raw ) {
            return $out;
        }
        if ( is_array( $raw ) ) {
            $data = (object) $raw;
        } elseif ( is_object( $raw ) ) {
            $data = $raw;
        } else {
            $data = json_decode( (string) $raw );
        }
        if ( ! is_object( $data ) && ! is_array( $data ) ) {
            return $out;
        }
        $data = (object) $data;
        if ( ! empty( $data->payload ) ) {
            $out['payload'] = (string) $data->payload; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        } elseif ( ! empty( $data->qrcode ) ) {
            $out['payload'] = (string) $data->qrcode;
        }
        $qr_raw = '';
        if ( ! empty( $data->encodedImage ) ) {
            $qr_raw = trim( (string) $data->encodedImage ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        } elseif ( ! empty( $data->qrcode_image ) ) {
            $qr_raw = trim( (string) $data->qrcode_image );
        }
        if ( '' !== $qr_raw ) {
            if ( 0 === strpos( $qr_raw, 'data:' ) || 0 === strpos( $qr_raw, 'http' ) ) {
                $out['image_src'] = $qr_raw;
            } else {
                $out['image_src'] = 'data:image/png;base64,' . preg_replace( '/\s+/', '', $qr_raw );
            }
        }
        if ( ! empty( $data->expirationDate ) ) {
            $out['expires'] = (string) $data->expirationDate; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
        } elseif ( ! empty( $data->qrcode_expiration_date ) ) {
            $out['expires'] = (string) $data->qrcode_expiration_date;
        }
        return $out;
    }

    /**
     * Valida acesso à página: login + pedido pertence ao usuário + chave confere.
     *
     * @return WC_Order|WP_Error
     */
    private function validate_access() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'login', __( 'Você precisa estar logado para ver esta página.', 'imovel-parceiro-core' ) );
        }
        $order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
        $key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
        if ( ! $order_id || '' === $key ) {
            return new WP_Error( 'invalid', __( 'Link de pagamento inválido.', 'imovel-parceiro-core' ) );
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'notfound', __( 'Pedido não encontrado.', 'imovel-parceiro-core' ) );
        }
        if ( ! hash_equals( (string) $order->get_order_key(), $key ) ) {
            return new WP_Error( 'forbidden', __( 'Você não tem permissão para ver este pagamento.', 'imovel-parceiro-core' ) );
        }
        if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
            return new WP_Error( 'forbidden', __( 'Este pagamento pertence a outro usuário.', 'imovel-parceiro-core' ) );
        }
        return $order;
    }

    /**
     * Renderiza a página quando ?pagamento-pix=1.
     */
    public function maybe_render() {
        if ( ! isset( $_GET['pagamento-pix'] ) ) {
            return;
        }

        nocache_headers();

        $order = $this->validate_access();
        if ( is_wp_error( $order ) ) {
            $this->render_error( $order->get_error_message() );
            exit;
        }

        $pix       = self::extract_pix( $order );
        $is_paid   = $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) );
        $plan_name = '';
        foreach ( $order->get_items() as $item ) {
            $plan_name = $item->get_name();
            break;
        }

        $this->render_page( $order, $pix, $is_paid, $plan_name );
        exit;
    }

    /**
     * AJAX: retorna o status atual do pedido (polling).
     */
    public function ajax_status() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
        $order    = $order_id ? wc_get_order( $order_id ) : false;

        if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
            wp_send_json_error( array( 'message' => __( 'Pedido inválido.', 'imovel-parceiro-core' ) ), 403 );
        }
        if ( ! is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'imovel-parceiro-core' ) ), 403 );
        }

        // Recarrega do banco para não usar cache da requisição.
        $order   = wc_get_order( $order->get_id() );
        $is_paid = $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) );

        wp_send_json_success(
            array(
                'paid'         => $is_paid,
                'status'       => $order->get_status(),
                'redirect_url' => self::get_dashboard_url(),
            )
        );
    }

    /**
     * Tela de erro (link inválido / sem permissão).
     */
    private function render_error( $message ) {
        status_header( 403 );
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'Pagamento', 'imovel-parceiro-core' ); ?></title>
            <?php wp_head(); ?>
        </head>
        <body>
            <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#f1f5f9;font-family:inherit;">
                <div style="max-width:480px;width:100%;background:#fff;border-radius:16px;padding:32px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08);">
                    <p style="font-size:15px;color:#475569;"><?php echo esc_html( $message ); ?></p>
                    <p><a href="<?php echo esc_url( self::get_dashboard_url() ); ?>" style="display:inline-block;margin-top:12px;background:#6366f1;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:700;"><?php esc_html_e( 'Ir para o dashboard', 'imovel-parceiro-core' ); ?></a></p>
                </div>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Tela principal: QR + copia e cola + espera do webhook + confirmação.
     */
    private function render_page( $order, $pix, $is_paid, $plan_name ) {
        $amount       = $order->get_formatted_order_total();
        $expires_iso  = $pix['expires'] ? gmdate( 'c', strtotime( $pix['expires'] ) ) : '';
        $dashboard    = self::get_dashboard_url();
        $ajax_url     = admin_url( 'admin-ajax.php' );
        $nonce        = wp_create_nonce( self::NONCE_ACTION );
        $config       = array(
            'ajaxUrl'   => $ajax_url,
            'action'    => self::AJAX_ACTION,
            'nonce'     => $nonce,
            'orderId'   => $order->get_id(),
            'orderKey'  => $order->get_order_key(),
            'pollMs'    => self::POLL_MS,
            'expires'   => $expires_iso,
            'redirect'  => $dashboard,
            'i18n'      => array(
                'waiting'   => __( 'Aguardando pagamento…', 'imovel-parceiro-core' ),
                'confirmed' => __( 'Pagamento confirmado!', 'imovel-parceiro-core' ),
                'expired'   => __( 'QR Code expirado. Gere uma nova cobrança no dashboard.', 'imovel-parceiro-core' ),
                'copy'      => __( 'Copiar código', 'imovel-parceiro-core' ),
                'copied'    => __( 'Código copiado!', 'imovel-parceiro-core' ),
            ),
        );
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'Pagamento via Pix', 'imovel-parceiro-core' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
            <?php wp_head(); ?>
            <style>
                .imovel-pix-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;background:#f1f5f9;}
                .imovel-pix-card{max-width:560px;width:100%;background:#fff;border-radius:20px;padding:32px 28px;box-shadow:0 12px 40px rgba(0,0,0,.10);text-align:center;}
                .imovel-pix-status{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 18px;font-weight:700;font-size:14px;margin-bottom:18px;}
                .imovel-pix-status.waiting{background:#fef9c3;color:#854d0e;}
                .imovel-pix-status.paid{background:#dcfce7;color:#166534;}
                .imovel-pix-status.expired{background:#fee2e2;color:#991b1b;}
                .imovel-pix-dot{width:10px;height:10px;border-radius:50%;background:currentColor;}
                .imovel-pix-status.waiting .imovel-pix-dot{animation:imovel-pix-pulse 1.2s infinite;}
                @keyframes imovel-pix-pulse{0%,100%{opacity:1}50%{opacity:.3}}
                .imovel-pix-qr{width:240px;height:240px;margin:0 auto 16px;border:1px solid #e2e8f0;border-radius:16px;padding:10px;background:#fff;}
                .imovel-pix-qr img{width:100%;height:100%;object-fit:contain;}
                .imovel-pix-payload{display:flex;gap:8px;margin:0 0 8px;}
                .imovel-pix-payload input{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:12px;font-size:13px;color:#334155;background:#f8fafc;min-width:0;}
                .imovel-pix-copy{white-space:nowrap;background:#6366f1;color:#fff;border:0;border-radius:10px;padding:0 18px;font-weight:700;cursor:pointer;}
                .imovel-pix-meta{color:#64748b;font-size:14px;margin:6px 0;}
                .imovel-pix-count{font-weight:700;color:#dc2626;}
                .imovel-pix-actions{margin-top:20px;display:flex;flex-direction:column;gap:10px;}
                .imovel-pix-btn{display:block;border-radius:10px;padding:13px;font-weight:700;text-decoration:none;cursor:pointer;border:0;font-size:15px;}
                .imovel-pix-btn.primary{background:#6366f1;color:#fff;}
                .imovel-pix-btn.ghost{background:#f1f5f9;color:#334155;}
                .imovel-pix-note{font-size:13px;color:#64748b;margin-top:14px;}
                .imovel-pix-check{width:72px;height:72px;border-radius:50%;background:#dcfce7;color:#166534;font-size:38px;line-height:72px;margin:0 auto 14px;}
                .imovel-pix-hide{display:none!important;}
            </style>
        </head>
        <body>
            <main class="imovel-pix-wrap">
                <div class="imovel-pix-card" id="imovel-pix-card">
                    <!-- Estado: aguardando -->
                    <div id="imovel-pix-waiting">
                        <span class="imovel-pix-status waiting" id="imovel-pix-status"><span class="imovel-pix-dot"></span><span id="imovel-pix-status-text"><?php esc_html_e( 'Aguardando pagamento…', 'imovel-parceiro-core' ); ?></span></span>
                        <h2 style="margin:0 0 4px;"><?php esc_html_e( 'Escaneie o QR Code para pagar', 'imovel-parceiro-core' ); ?></h2>
                        <?php if ( $plan_name ) : ?>
                            <p class="imovel-pix-meta"><?php echo esc_html( $plan_name ); ?> — <strong><?php echo wp_kses_post( $amount ); ?></strong></p>
                        <?php else : ?>
                            <p class="imovel-pix-meta"><strong><?php echo wp_kses_post( $amount ); ?></strong></p>
                        <?php endif; ?>
                        <?php if ( $pix['image_src'] ) : ?>
                            <div class="imovel-pix-qr"><img src="<?php echo esc_attr( $pix['image_src'] ); ?>" alt="<?php esc_attr_e( 'QR Code Pix', 'imovel-parceiro-core' ); ?>"></div>
                        <?php endif; ?>
                        <?php if ( $pix['payload'] ) : ?>
                            <div class="imovel-pix-payload">
                                <input type="text" id="imovel-pix-payload" readonly value="<?php echo esc_attr( $pix['payload'] ); ?>">
                                <button type="button" class="imovel-pix-copy" id="imovel-pix-copy"><?php esc_html_e( 'Copiar código', 'imovel-parceiro-core' ); ?></button>
                            </div>
                        <?php endif; ?>
                        <p class="imovel-pix-meta" id="imovel-pix-countdown-wrap" style="<?php echo $expires_iso ? '' : 'display:none;'; ?>"><?php esc_html_e( 'O código expira em', 'imovel-parceiro-core' ); ?> <span class="imovel-pix-count" id="imovel-pix-countdown">--:--</span></p>
                        <div class="imovel-pix-actions">
                            <button type="button" class="imovel-pix-btn ghost" id="imovel-pix-refresh"><?php esc_html_e( 'Já paguei, verificar agora', 'imovel-parceiro-core' ); ?></button>
                            <a class="imovel-pix-btn ghost" href="<?php echo esc_url( $dashboard ); ?>"><?php esc_html_e( 'Voltar ao dashboard', 'imovel-parceiro-core' ); ?></a>
                        </div>
                        <p class="imovel-pix-note"><?php esc_html_e( 'Assim que o pagamento for confirmado, você será levado ao dashboard automaticamente. Pode deixar esta página aberta.', 'imovel-parceiro-core' ); ?></p>
                    </div>
                    <!-- Estado: confirmado -->
                    <div id="imovel-pix-success" class="<?php echo $is_paid ? '' : 'imovel-pix-hide'; ?>">
                        <div class="imovel-pix-check">✓</div>
                        <span class="imovel-pix-status paid"><span class="imovel-pix-dot"></span><?php esc_html_e( 'Pagamento confirmado!', 'imovel-parceiro-core' ); ?></span>
                        <h2 style="margin:14px 0 4px;"><?php esc_html_e( 'Pagamento efetuado com sucesso.', 'imovel-parceiro-core' ); ?></h2>
                        <p class="imovel-pix-meta"><?php esc_html_e( 'Redirecionando para o dashboard…', 'imovel-parceiro-core' ); ?></p>
                        <div class="imovel-pix-actions">
                            <a class="imovel-pix-btn primary" href="<?php echo esc_url( $dashboard ); ?>"><?php esc_html_e( 'Ir para o dashboard agora', 'imovel-parceiro-core' ); ?></a>
                        </div>
                    </div>
                </div>
            </main>
            <script>
            (function(){
                var cfg = <?php echo wp_json_encode( $config ); ?>;
                var alreadyPaid = <?php echo $is_paid ? 'true' : 'false'; ?>;
                var waiting = document.getElementById('imovel-pix-waiting');
                var success = document.getElementById('imovel-pix-success');
                var statusText = document.getElementById('imovel-pix-status-text');
                var statusPill = document.getElementById('imovel-pix-status');

                function showSuccess(){
                    if (waiting) waiting.classList.add('imovel-pix-hide');
                    if (success) success.classList.remove('imovel-pix-hide');
                    setTimeout(function(){ window.location.href = cfg.redirect; }, 4000);
                }
                if (alreadyPaid) { showSuccess(); return; }

                // Copiar código.
                var copyBtn = document.getElementById('imovel-pix-copy');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function(){
                        var input = document.getElementById('imovel-pix-payload');
                        function done(){
                            copyBtn.textContent = cfg.i18n.copied;
                            setTimeout(function(){ copyBtn.textContent = cfg.i18n.copy; }, 2500);
                        }
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(input.value).then(done, function(){ input.select(); document.execCommand('copy'); done(); });
                        } else {
                            input.select(); document.execCommand('copy'); done();
                        }
                    });
                }

                // Contagem regressiva da expiração.
                var expiresAt = cfg.expires ? new Date(cfg.expires).getTime() : 0;
                var cdWrap = document.getElementById('imovel-pix-countdown-wrap');
                var cdEl = document.getElementById('imovel-pix-countdown');
                var expired = false;
                function tick(){
                    if (!expiresAt || expired) return;
                    var diff = expiresAt - Date.now();
                    if (diff <= 0) {
                        expired = true;
                        if (statusPill) { statusPill.className = 'imovel-pix-status expired'; }
                        if (statusText) { statusText.textContent = cfg.i18n.expired; }
                        if (cdWrap) cdWrap.style.display = 'none';
                        stop();
                        return;
                    }
                    var h = Math.floor(diff / 3600000), m = Math.floor(diff % 3600000 / 60000), s = Math.floor(diff % 60000 / 1000);
                    if (cdEl) cdEl.textContent = (h > 0 ? h + ':' : '') + ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
                }
                if (expiresAt) { tick(); setInterval(tick, 1000); } else if (cdWrap) { cdWrap.style.display = 'none'; }

                // Polling do status (webhook do Asaas atualiza o pedido).
                var timer = null, maxPolls = 720, polls = 0; // ~60 min
                function check(){
                    polls++;
                    if (polls > maxPolls || expired) { stop(); return; }
                    var data = new FormData();
                    data.append('action', cfg.action);
                    data.append('nonce', cfg.nonce);
                    data.append('order_id', cfg.orderId);
                    data.append('key', cfg.orderKey);
                    fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            if (res && res.success && res.data && res.data.paid) { stop(); showSuccess(); }
                        })
                        .catch(function(){ /* mantém tentando na próxima rodada */ });
                }
                function stop(){ if (timer) { clearInterval(timer); timer = null; } }
                timer = setInterval(check, cfg.pollMs);
                // Botão "Já paguei": força uma verificação imediata.
                var refresh = document.getElementById('imovel-pix-refresh');
                if (refresh) { refresh.addEventListener('click', check); }
            })();
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}

new Imovel_Parceiro_Pix_Payment_Page();
