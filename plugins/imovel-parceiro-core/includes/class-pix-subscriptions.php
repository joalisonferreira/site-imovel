<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pix (Asaas) disponível nas assinaturas — opt-in local e reversível.
 *
 * Contexto: o plugin woo-asaas esconde o `asaas-pix` quando o carrinho contém
 * assinatura, em dois pontos:
 *  1. O gateway declara apenas `supports = ['products', 'refunds']`
 *     (woo-asaas/includes/gateway/class-pix.php:41-44), sem `subscriptions`.
 *  2. O filtro próprio do woo-asaas (`WC_Asaas\Cart::check_available_payment_gateways`,
 *     prioridade 10) faz `unset( $available_gateways['asaas-pix'] )` sempre que
 *     o carrinho tem produto de assinatura — sem checar supports e sem filtro
 *     de opt-out (woo-asaas/includes/cart/class-cart.php:174-177).
 *
 * Esta classe corrige os dois pontos SOMENTE para a instância `asaas-pix`,
 * sem tocar em cartão (`asaas-credit-card`) ou boleto (`asaas-ticket`).
 * O caminho de processamento de assinatura do Pix já existe no woo-asaas
 * (Pix::process_payment, caso 'subscription').
 *
 * Decisão consciente: NÃO adicionamos `gateway_scheduled_payments`, pois o
 * Pix não debita recorrência automática. Sem essa flag, as renovações nascem
 * como manuais (pedido pendente + novo QR a cada ciclo).
 *
 * ROLLBACK: para desativar, basta remover o require desta classe em
 * imovel-parceiro-core.php (ou `git revert` do commit correspondente).
 * Nenhuma mudança de banco de dados está envolvida.
 */
class Imovel_Parceiro_Pix_Subscriptions {

    const GATEWAY_ID = 'asaas-pix';

    /**
     * Flags adicionadas somente ao asaas-pix.
     *
     * - subscriptions: exibe o Pix no checkout com assinatura no carrinho.
     * - subscription_cancellation/suspension/reactivation: liberam os botões
     *   de cancelar/suspender/reativar (transições de status locais, sem
     *   chamada externa — a classe base do woo-asaas não implementa esses
     *   métodos, então nada externo é acionado).
     */
    const EXTRA_SUPPORTS = array(
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
    );

    /**
     * Traduções PT-BR das telas nativas do Pix no thank-you / order-pay /
     * ver-pedido (domínio 'woo-asaas'). O .po do woo-asaas não cobre essas
     * strings, então traduzimos via filtro, só no front.
     */
    const PTBR_STRINGS = array(
        'Payment details'                                                                                                     => 'Detalhes do pagamento',
        'Pay with Pix.'                                                                                                       => 'Pague com Pix.',
        'Click here to copy the Pix code'                                                                                     => 'Clique aqui para copiar o código Pix',
        'Code copied to clipboard'                                                                                            => 'Código copiado',
        'Open the app or Internet Banking to pay.'                                                                            => 'Abra o app do banco ou o internet banking para pagar.',
        'In the Pix option, choose "Read QR Code".'                                                                           => 'Na opção Pix, escolha "Ler QR Code".',
        'Scan the QR Code or, if you prefer, copy the code to Pix Copy and Paste.'                                            => 'Escaneie o QR Code ou, se preferir, copie o código do Pix copia e cola.',
        'Review the information and confirm payment. Ready! The order status will be updated immediately.'                     => 'Confira os dados e confirme o pagamento. Pronto! O pedido será atualizado automaticamente após a confirmação.',
        'You have %1$d %2$s to pay. After that time, your order will be cancelled.'                                           => 'Você tem %1$d %2$s para pagar. Após esse prazo, o pedido será cancelado.',
    );

    const PTBR_PLURALS = array(
        'minute' => array( 'minuto', 'minutos' ),
        'hour'   => array( 'hora', 'horas' ),
        'day'    => array( 'dia', 'dias' ),
    );

    public function __construct() {
        // Etapa 1 (prioridade 5): adiciona as flags ANTES do filtro do
        // woo-asaas (prioridade 10) e do WooCommerce Subscriptions.
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'add_subscription_supports' ), 5 );
        // Etapa 2 (prioridade 20): recoloca o asaas-pix DEPOIS do unset
        // forçado do woo-asaas (prioridade 10). Sem isso, a etapa 1 sozinha
        // não basta — o unset é incondicional.
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'restore_pix_gateway' ), 20 );
        // Etapa 3: PT-BR nas telas nativas do Pix (thank-you, order-pay,
        // ver pedido). Só front, só domínio woo-asaas.
        add_filter( 'gettext', array( $this, 'translate_pix_strings' ), 20, 3 );
        add_filter( 'ngettext', array( $this, 'translate_pix_plurals' ), 20, 5 );
    }

    /**
     * Anexa as flags de assinatura à instância do gateway Pix.
     *
     * @param array $gateways Gateways disponíveis (id => instância).
     * @return array Gateways (inalterado se o Pix estiver ausente/desligado).
     */
    public function add_subscription_supports( $gateways ) {
        if ( ! is_array( $gateways ) || empty( $gateways[ self::GATEWAY_ID ] ) ) {
            return $gateways;
        }

        $pix = $gateways[ self::GATEWAY_ID ];

        if ( ! is_object( $pix ) || ! isset( $pix->supports ) || ! is_array( $pix->supports ) ) {
            return $gateways;
        }

        foreach ( self::EXTRA_SUPPORTS as $feature ) {
            if ( ! in_array( $feature, $pix->supports, true ) ) {
                $pix->supports[] = $feature;
            }
        }

        return $gateways;
    }

    /**
     * Recoloca o asaas-pix após o unset forçado do woo-asaas.
     *
     * Guardas (para não furar bloqueios legítimos):
     * - só no front (nunca no admin);
     * - nunca na tela order-pay (ali o gateway é travado no do pedido);
     * - só se o carrinho tem produto de assinatura (mesma condição do unset);
     * - só se cartão ou boleto seguem disponíveis (se o woo-asaas desabilitou
     *   TODA a Asaas — ciclo de cobrança ou cupom incompatível — respeitamos).
     *
     * @param array $gateways Gateways disponíveis (id => instância).
     * @return array Gateways com o Pix recolocado quando aplicável.
     */
    public function restore_pix_gateway( $gateways ) {
        if ( ! is_array( $gateways ) || is_admin() ) {
            return $gateways;
        }

        if ( isset( $gateways[ self::GATEWAY_ID ] ) ) {
            return $gateways;
        }

        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
            return $gateways;
        }

        if ( ! $this->cart_has_subscription_products() ) {
            return $gateways;
        }

        if ( empty( $gateways['asaas-credit-card'] ) && empty( $gateways['asaas-ticket'] ) ) {
            return $gateways;
        }

        if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
            return $gateways;
        }

        $registered = WC()->payment_gateways()->payment_gateways();

        if ( empty( $registered[ self::GATEWAY_ID ] ) || ! is_object( $registered[ self::GATEWAY_ID ] ) ) {
            return $gateways;
        }

        $pix = $registered[ self::GATEWAY_ID ];

        if ( ! $pix->is_available() ) {
            return $gateways;
        }

        $gateways[ self::GATEWAY_ID ] = $pix;

        return $gateways;
    }

    /**
     * Replica a detecção do woo-asaas: há produto de assinatura no carrinho?
     *
     * @return bool
     */
    private function cart_has_subscription_products() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart || 0 >= WC()->cart->get_cart_contents_count() ) {
            return false;
        }

        $types = array( 'subscription', 'variable-subscription', 'subscription_variation' );
        if ( class_exists( 'WC_Asaas\Helper\Subscriptions_Helper' ) ) {
            $helper = new \WC_Asaas\Helper\Subscriptions_Helper();
            if ( ! empty( $helper->subscription_product_types ) && is_array( $helper->subscription_product_types ) ) {
                $types = $helper->subscription_product_types;
            }
        }

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( empty( $item['data'] ) || ! is_object( $item['data'] ) || ! method_exists( $item['data'], 'get_type' ) ) {
                continue;
            }
            if ( in_array( $item['data']->get_type(), $types, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Traduz strings singulares do woo-asaas para PT-BR (só front).
     *
     * @param string $translation Tradução atual.
     * @param string $text Texto original.
     * @param string $domain Domínio.
     * @return string
     */
    public function translate_pix_strings( $translation, $text, $domain ) {
        if ( 'woo-asaas' !== $domain || is_admin() ) {
            return $translation;
        }
        if ( isset( self::PTBR_STRINGS[ $text ] ) ) {
            return self::PTBR_STRINGS[ $text ];
        }
        return $translation;
    }

    /**
     * Traduz plurais do woo-asaas para PT-BR (minuto/hora/dia, só front).
     *
     * @param string $translation Tradução atual.
     * @param string $single Singular original.
     * @param string $plural Plural original.
     * @param int    $number Quantidade.
     * @param string $domain Domínio.
     * @return string
     */
    public function translate_pix_plurals( $translation, $single, $plural, $number, $domain ) {
        if ( 'woo-asaas' !== $domain || is_admin() ) {
            return $translation;
        }
        if ( isset( self::PTBR_PLURALS[ $single ] ) ) {
            $forms = self::PTBR_PLURALS[ $single ];
            return 1 === (int) $number ? $forms[0] : $forms[1];
        }
        return $translation;
    }
}

new Imovel_Parceiro_Pix_Subscriptions();
