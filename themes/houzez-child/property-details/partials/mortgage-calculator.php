<?php
global $post;
$currency_symbol = currency_maker();
$currency_symbol = $currency_symbol['currency'];
$mcal_terms = houzez_option('mcal_terms', 30);
$mcal_down_payment = houzez_option('mcal_down_payment', 20);
$mcal_interest_rate = houzez_option('mcal_interest_rate', 3.5);
$mcal_prop_tax_enable = houzez_option('mcal_prop_tax_enable', 0);
$mcal_prop_tax = houzez_option('mcal_prop_tax', 0);
$mcal_hi_enable = houzez_option('mcal_hi_enable', 0);
$mcal_hi = houzez_option('mcal_hi', 0);
$mcal_hoa_enable = houzez_option('mcal_hoa_enable', 0);
$mcal_hoa = houzez_option('mcal_hoa', 0);
$mcal_pmi_enable = houzez_option('mcal_pmi_enable', 0);
$mcal_pmi = houzez_option('mcal_pmi', 0);
$property_price = get_post_meta($post->ID, 'fave_property_price', true);
$property_price = intval($property_price);

if ( class_exists( 'FCC_Rates' ) && houzez_currency_switcher_enabled() && isset( $_COOKIE[ "houzez_set_current_currency" ] ) ) {
    $currency_data = Fcc_get_currency($_COOKIE['houzez_set_current_currency']);
    $currency_symbol = $currency_data['symbol'];
    if( function_exists('houzez_get_plain_price') ) {
	    $property_price = houzez_get_plain_price($property_price );
	}
}
if($property_price == 0) {
	$mcal_terms = $mcal_down_payment = $mcal_interest_rate = $mcal_prop_tax = $mcal_hi = $mcal_pmi = $mcal_hoa = $property_price = '';
}
$default_down_value = $property_price ? round($property_price * (floatval($mcal_down_payment) / 100)) : '';
$financed_default = ($property_price && $default_down_value !== '') ? max(0, $property_price - $default_down_value) : $property_price;
$terms_options = array(5, 10, 15, 20, 25, 30);
if (!in_array(intval($mcal_terms), $terms_options, true)) { $terms_options[] = intval($mcal_terms); sort($terms_options); }
?>
<div class="ipc-mortgage" id="ipc-mortgage">
    <h3><?php esc_html_e('Simular financiamento', 'houzez'); ?></h3>
    <div class="ipc-mortgage-grid">
        <div>
            <label class="form-label" for="ipcHomePrice"><?php esc_html_e('Valor do imóvel', 'houzez'); ?></label>
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" inputmode="numeric" class="form-control" id="ipcHomePrice" value="<?php echo esc_attr(number_format($property_price, 0, ',', '.')); ?>" placeholder="R$ 0">
            </div>
        </div>
        <div>
            <label class="form-label" for="ipcDownPayment"><?php esc_html_e('Entrada', 'houzez'); ?></label>
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" inputmode="numeric" class="form-control" id="ipcDownPayment" value="<?php echo esc_attr($default_down_value !== '' ? number_format($default_down_value, 0, ',', '.') : ''); ?>" placeholder="R$ 0">
            </div>
        </div>
        <div>
            <label class="form-label"><?php esc_html_e('Prazo em anos', 'houzez'); ?></label>
            <div class="ipc-term-btns" role="group" aria-label="<?php esc_attr_e('Prazo em anos', 'houzez'); ?>">
                <?php foreach ($terms_options as $t) : ?>
                    <button type="button" data-years="<?php echo intval($t); ?>" class="<?php echo intval($t) === intval($mcal_terms) ? 'is-active' : ''; ?>"><?php echo intval($t); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="ipc-mortgage-grid mt-3" style="grid-template-columns:1fr 1fr;">
        <div></div>
        <div class="d-flex flex-column gap-2">
            <div class="ipc-mortgage-financed">
                <span style="font-size:13px;font-weight:700;"><?php esc_html_e('Valor a ser financiado', 'houzez'); ?> <span title="<?php esc_attr_e('Valor do imóvel menos a entrada', 'houzez'); ?>" style="cursor:help;">?</span></span>
                <strong id="ipcFinancedValue">R$ <?php echo esc_html(number_format($financed_default, 0, ',', '.')); ?></strong>
                <span id="ipcMonthlyHint" style="font-size:12px;color:#6b7280;"></span>
            </div>
            <button type="button" class="ipc-mortgage-cta" id="ipcShowInstallments"><?php esc_html_e('Ver parcelas', 'houzez'); ?></button>
        </div>
    </div>

    <div id="ipcInstallmentDetails" style="display:none;" class="mt-4">
        <div class="d-flex align-items-center flex-column flex-sm-row gap-4">
            <div class="mortgage-calculator-chart d-flex align-items-center mb-4" role="complementary">
                <div class="mortgage-calculator-monthly-payment-wrap w-100 text-center">
                    <div id="m_monthly_val" class="mortgage-calculator-monthly-payment mb-1"></div>
                    <div class="mortgage-calculator-monthly-requency"><?php echo houzez_option('spc_monthly', 'Monthly'); ?></div>
                </div>
                <canvas id="mortgage-calculator-chart" class="m-auto" width="250" height="250"></canvas>
            </div>
            <div class="mortgage-calculator-data w-100 mb-4" role="complementary">
                <ul class="list-unstyled list-lined" role="list">
                    <li class="d-flex align-items-center justify-content-between"><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php esc_html_e('Entrada', 'houzez'); ?></strong></span><span id="downPaymentResult"></span></div></li>
                    <li class="d-flex align-items-center justify-content-between"><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php esc_html_e('Valor financiado', 'houzez'); ?></strong></span><span id="loadAmountResult"></span></div></li>
                    <li class="d-flex align-items-center justify-content-between"><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php echo houzez_option('spc_monthly_mortgage_payment', 'Monthly Mortgage Payment'); ?></strong></span><span id="monthlyMortgagePaymentResult"></span></div></li>
                    <?php if($mcal_prop_tax_enable) { ?><li><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php echo houzez_option('spc_prop_tax', 'Property Tax'); ?></strong></span><span id="monthlyPropertyTaxResult"></span></div></li><?php } ?>
                    <?php if($mcal_hi_enable) { ?><li><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php echo houzez_option('spc_hi', 'Home Insurance'); ?></strong></span><span id="monthlyHomeInsuranceResult"></span></div></li><?php } ?>
                    <?php if($mcal_pmi_enable) { ?><li><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php echo houzez_option('spc_pmi', 'PMI'); ?></strong></span><span id="monthlyPMIResult"></span></div></li><?php } ?>
                    <?php if($mcal_hoa_enable) { ?><li><div class="w-100 d-flex justify-content-between py-2"><span><strong><?php echo houzez_option('spc_hoa', 'Monthly HOA Fees'); ?></strong></span><span id="monthlyHOAResult"></span></div></li><?php } ?>
                </ul>
            </div>
        </div>
    </div>

    <form id="houzez-calculator-form" method="post" style="display:none;" aria-hidden="true">
        <input type="text" id="homePrice" value="<?php echo intval($property_price); ?>">
        <input type="text" id="downPaymentPercentage" value="<?php echo esc_attr($mcal_down_payment); ?>">
        <input type="text" id="annualInterestRate" value="<?php echo esc_attr($mcal_interest_rate); ?>">
        <input type="text" id="loanTermInYears" value="<?php echo esc_attr($mcal_terms); ?>">
        <?php if($mcal_prop_tax_enable) { ?><input type="text" id="annualPropertyTaxRate" value="<?php echo esc_attr($mcal_prop_tax); ?>"><?php } ?>
        <?php if($mcal_hi_enable) { ?><input type="text" id="annualHomeInsurance" value="<?php echo esc_attr($mcal_hi); ?>"><?php } ?>
        <?php if($mcal_hoa_enable) { ?><input type="text" id="monthlyHOAFees" value="<?php echo esc_attr($mcal_hoa); ?>"><?php } ?>
        <?php if($mcal_pmi_enable) { ?><input type="text" id="pmi" value="<?php echo esc_attr($mcal_pmi); ?>"><?php } ?>
    </form>
</div>
<script>
(function(){
    function parseBR(v){ v=(v||'').toString().replace(/[^0-9]/g,''); return v==='' ? 0 : parseInt(v,10); }
    function fmtBR(n){ return 'R$ ' + Number(n||0).toLocaleString('pt-BR'); }
    function syncToHidden(){
        var price = parseBR(document.getElementById('ipcHomePrice').value);
        var down = parseBR(document.getElementById('ipcDownPayment').value);
        if (down > price) { down = price; document.getElementById('ipcDownPayment').value = price.toLocaleString('pt-BR'); }
        var pct = price > 0 ? (down / price * 100) : 0;
        var hp = document.getElementById('homePrice'); if (hp) { hp.value = price; hp.dispatchEvent(new Event('input', {bubbles:true})); }
        var dp = document.getElementById('downPaymentPercentage'); if (dp) { dp.value = pct.toFixed(2); dp.dispatchEvent(new Event('input', {bubbles:true})); }
        var fin = Math.max(0, price - down);
        var finEl = document.getElementById('ipcFinancedValue'); if (finEl) finEl.textContent = fmtBR(fin);
        var hint = document.getElementById('ipcMonthlyHint');
        var monthly = document.getElementById('monthlyMortgagePaymentResult');
        if (hint && monthly) { hint.textContent = monthly.textContent ? ('~ ' + monthly.textContent + '/mês') : ''; }
    }
    document.addEventListener('DOMContentLoaded', function(){
        var p = document.getElementById('ipcHomePrice'), d = document.getElementById('ipcDownPayment');
        if (p) p.addEventListener('input', syncToHidden);
        if (d) d.addEventListener('input', syncToHidden);
        document.querySelectorAll('#ipc-mortgage .ipc-term-btns button').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('#ipc-mortgage .ipc-term-btns button').forEach(function(x){ x.classList.remove('is-active'); });
                b.classList.add('is-active');
                var y = b.getAttribute('data-years');
                var lt = document.getElementById('loanTermInYears'); if (lt) { lt.value = y; lt.dispatchEvent(new Event('input', {bubbles:true})); }
                syncToHidden();
            });
        });
        var cta = document.getElementById('ipcShowInstallments');
        if (cta) cta.addEventListener('click', function(){
            syncToHidden();
            var det = document.getElementById('ipcInstallmentDetails');
            if (det) { det.style.display = 'block'; det.scrollIntoView({behavior:'smooth', block:'start'}); }
        });
        setTimeout(syncToHidden, 600);
        setTimeout(function(){
            var monthly = document.getElementById('monthlyMortgagePaymentResult');
            var hint = document.getElementById('ipcMonthlyHint');
            if (hint && monthly && monthly.textContent) hint.textContent = ('~ ' + monthly.textContent + '/mês');
        }, 1200);
    });
})();
</script>
