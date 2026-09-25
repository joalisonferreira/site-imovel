<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pix (Asaas) disponível nas assinaturas — opt-in local e reversível.
 *
 * Contexto: o gateway `asaas-pix` do plugin woo-asaas declara apenas
 * `supports = ['products', 'refunds']` (sem `subscriptions`), então o
 * WooCommerce Subscriptions o remove do checkout quando o carrinho contém
 * assinatura — mesmo com o Pix ativo nas configurações da Asaas.
 *
 * Esta classe adiciona as flags SOMENTE à instância `asaas-pix`, sem tocar
 * em cartão (`asaas-credit-card`) ou boleto (`asaas-ticket`). O caminho de
 * processamento de assinatura do Pix já existe no woo-asaas
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

    public function __construct() {
        // Prioridade 5: roda antes do filtro do WooCommerce Subscriptions
        // (prioridade 10), que remove gateways sem suporte a 'subscriptions'.
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'enable_pix_for_subscriptions' ), 5 );
    }

    /**
     * Anexa as flags de assinatura à instância do gateway Pix.
     *
     * @param array $gateways Gateways disponíveis (id => instância).
     * @return array Gateways (inalterado se o Pix estiver ausente/desligado).
     */
    public function enable_pix_for_subscriptions( $gateways ) {
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
}

new Imovel_Parceiro_Pix_Subscriptions();
