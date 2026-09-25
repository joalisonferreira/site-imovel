<?php
/**
 * Fresh checkout layout — baseado 1:1 no HTML stitch code.html
 * NÃO reutiliza checkout.css/js antigos. Usa Tailwind CDN + estrutura Tailwind do HTML.
 * Mantém a lógica padrão WooCommerce (fields, cupom, pedido, pagamento).
 */
defined( 'ABSPATH' ) || exit;
if ( ! is_checkout() ) { return; }
wc_print_notices();
do_action( 'woocommerce_before_checkout_form', $checkout );
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Você precisa estar logado para finalizar a compra.', 'woocommerce' ) ) );
    return;
}
?>
<div class="woocommerce-checkout-fresh">
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

<!-- Breadcrumbs & Steps (stitch) -->
<div class="mb-8 space-y-4">
  <nav aria-label="Breadcrumb" class="flex text-xs font-medium text-slate-500">
    <ol class="inline-flex items-center space-x-1.5 md:space-x-2">
      <li class="inline-flex items-center">
        <a class="hover:text-brand-600 flex items-center gap-1" href="<?php echo esc_url( home_url('/') ); ?>">
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
          Home
        </a>
      </li>
      <li><div class="flex items-center"><svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" fill-rule="evenodd"></path></svg><a class="ml-1 hover:text-brand-600" href="<?php $pl = function_exists('houzez_get_template_link') ? houzez_get_template_link('template/template-packages.php') : home_url('/'); echo esc_url($pl); ?>">Planos de Assinatura</a></div></li>
      <li aria-current="page"><div class="flex items-center"><svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" fill-rule="evenodd"></path></svg><span class="ml-1 text-slate-900 font-semibold">Finalização de compra</span></div></li>
    </ol>
  </nav>
  <div class="pt-2 pb-4 border-b border-slate-200/80">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900" style="font-family:'Plus Jakarta Sans',Inter,sans-serif">Finalização de compra</h1>
        <p class="text-sm text-slate-500 mt-0.5">Revise sua assinatura e insira as informações de faturamento credenciadas.</p>
      </div>
      <div class="flex items-center gap-2 self-start sm:self-auto text-xs font-semibold">
        <div class="flex items-center gap-1.5 text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill-rule="evenodd"></path></svg><span>1. Plano</span></div>
        <div class="w-4 h-px bg-slate-300"></div>
        <div class="flex items-center gap-1.5 text-brand-700 bg-brand-50 px-3 py-1.5 rounded-full border border-brand-200 shadow-sm"><span class="w-2 h-2 rounded-full bg-brand-600 animate-pulse"></span><span>2. Faturamento &amp; Checkout</span></div>
        <div class="w-4 h-px bg-slate-300"></div>
        <div class="flex items-center gap-1.5 text-slate-400 px-2 py-1"><span>3. Confirmação</span></div>
      </div>
    </div>
  </div>
</div>

<!-- Coupon banner nativo WooCommerce estilizado como stitch -->
<div class="mb-8">
  <div class="bg-white border border-blue-100 rounded-2xl p-4 sm:p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,.05)]">
    <div class="woocommerce-form-coupon-toggle">
      <?php wc_print_notice( __( 'Tem um cupom promocional ou convite de imobiliária?', 'imovel-parceiro-core' ) . ' <a href="#" class="showcoupon text-brand-600 font-bold underline underline-offset-2">' . __( 'Clique aqui para digitar seu cupom', 'imovel-parceiro-core' ) . '</a>', 'notice' ); ?>
    </div>
    <div class="checkout_coupon woocommerce-form-coupon hidden">
      <p class="form-row form-row-first"><input type="text" name="coupon_code" class="input-text border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-mono uppercase" placeholder="<?php esc_attr_e( 'Código do cupom', 'woocommerce' ); ?>" /></p>
      <p class="form-row form-row-last"><button type="button" class="button px-5 py-2.5 text-sm font-semibold rounded-xl bg-slate-900 text-white"><?php esc_html_e( 'Aplicar cupom', 'woocommerce' ); ?></button></p>
      <div class="clear"></div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
  <!-- Left: Detalhes de cobrança + Info adicional -->
  <div class="lg:col-span-7 space-y-8">
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-[0_2px_8px_-2px_rgba(15,23,42,.05)] p-6 sm:p-8">
      <div class="flex items-center justify-between pb-5 mb-6 border-b border-slate-100">
        <div class="flex items-center gap-3"><span class="w-2.5 h-6 bg-brand-600 rounded-full inline-block"></span><h2 class="text-xl font-bold text-slate-900 tracking-tight" style="font-family:'Plus Jakarta Sans',Inter,sans-serif">1. Detalhes de cobrança</h2></div>
        <span class="text-xs text-slate-500 font-medium"><span class="text-brand-600 font-bold">*</span> Campos obrigatórios</span>
      </div>

      <!-- Tipo de Faturamento (PF/PJ) — controla billing_persontype -->
      <div class="mb-6">
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Tipo de Faturamento</label>
        <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100/90 rounded-xl border border-slate-200/70 max-w-sm">
          <button type="button" id="ipc-tab-pf" class="py-2 text-xs sm:text-sm font-bold rounded-lg text-slate-900 bg-white shadow-sm flex items-center justify-center gap-2"><svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>Pessoa Física (CPF)</button>
          <button type="button" id="ipc-tab-pj" class="py-2 text-xs sm:text-sm font-semibold rounded-lg text-slate-600 flex items-center justify-center gap-2"><svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>Pessoa Jurídica (CNPJ)</button>
        </div>
      </div>

      <div id="customer_details"><div class="col2-set"><div class="col-1">
        <?php do_action( 'woocommerce_checkout_billing' ); ?>
      </div><div class="col-2"><?php do_action( 'woocommerce_checkout_shipping' ); ?></div></div></div>
    </div>

    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-[0_2px_8px_-2px_rgba(15,23,42,.05)] p-6 sm:p-8">
      <div class="flex items-center gap-3 mb-4"><span class="w-2.5 h-6 bg-slate-400 rounded-full inline-block"></span><h2 class="text-xl font-bold text-slate-900 tracking-tight" style="font-family:'Plus Jakarta Sans',Inter,sans-serif">2. Informação adicional</h2></div>
      <?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>
      <?php foreach ( $checkout->get_checkout_fields('order') as $key => $field ) : ?><?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?><?php endforeach; ?>
      <?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
    </div>
  </div>

  <!-- Right: Resumo do pedido -->
  <div class="lg:col-span-5">
    <div class="sticky top-28 space-y-6">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-[0_12px_32px_-8px_rgba(15,23,42,.06)] overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
          <div><span class="text-[11px] uppercase tracking-widest text-red-300 font-bold">Resumo do Pedido</span><h3 id="order_review_heading" class="text-lg font-extrabold text-white !m-0 !p-0 !border-0 !bg-transparent !shadow-none">Seu pedido</h3></div>
          <div class="flex items-center gap-1.5 px-2.5 py-1 bg-white/10 rounded-full text-xs text-white/90 border border-white/10"><svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" fill-rule="evenodd"></path></svg><span>Ativação Instantânea</span></div>
        </div>
        <div id="order_review" class="woocommerce-checkout-review-order p-6">
          <?php do_action( 'woocommerce_checkout_order_review' ); ?>
        </div>
      </div>
      <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 shadow-sm border border-slate-800 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/10"><svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></div>
        <div class="text-xs"><h5 class="font-bold text-white text-sm">Suporte Especializado CRECI</h5><p class="text-slate-300 mt-0.5">Dúvidas na contratação? Nosso time de corretores sênior está disponível no WhatsApp oficial.</p></div>
      </div>
    </div>
  </div>
</div>

</form>
</div>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
