<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $is_multi_steps, $hide_prop_fields;

$property_price = '';
$property_sec_price = '';
$condominio_value = '';
$iptu_value = '';

if ( function_exists( 'houzez_edit_property' ) && houzez_edit_property() ) {
    $property_price = (string) houzez_get_field_meta( 'property_price' );
    $property_sec_price = (string) houzez_get_field_meta( 'property_sec_price' );
    $condominio_value = (string) houzez_get_field_meta( 'valor-do-condominio' );
    $iptu_value = (string) houzez_get_field_meta( 'valor-do-iptu' );
}
?>
<div id="description-price" class="<?php echo esc_attr( $is_multi_steps ); ?>">
    <div class="block-wrap">
        <div class="block-title-wrap d-flex justify-content-between align-items-center">
            <h2><?php echo houzez_option( 'cls_description', 'Description' ); ?></h2>
        </div>

        <div class="block-content-wrap">
            <?php get_template_part( 'template-parts/dashboard/submit/form-fields/title' ); ?>

            <?php get_template_part( 'template-parts/dashboard/submit/form-fields/description' ); ?>

            <div class="row">
                <?php if ( $hide_prop_fields['prop_type'] != 1 ) { ?>
                <div class="col-md-4 col-sm-12">
                    <?php get_template_part( 'template-parts/dashboard/submit/form-fields/type' ); ?>
                </div>
                <?php } ?>

                <?php if ( $hide_prop_fields['prop_status'] != 1 ) { ?>
                <div class="col-md-4 col-sm-12">
                    <?php get_template_part( 'template-parts/dashboard/submit/form-fields/status' ); ?>
                </div>
                <?php } ?>

                <?php if ( $hide_prop_fields['prop_label'] != 1 ) { ?>
                <div class="col-md-4 col-sm-12">
                    <?php get_template_part( 'template-parts/dashboard/submit/form-fields/label' ); ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="block-wrap">
        <div class="block-title-wrap d-flex justify-content-between align-items-center">
            <h2><?php esc_html_e( 'Valor', 'imovel-parceiro-core' ); ?></h2>
        </div>

        <div class="block-content-wrap">
            <div class="row">
                <div class="col-md-6 col-sm-12">
                    <div class="form-group mb-3">
                        <label class="form-label" for="property_price"><?php esc_html_e( 'Preço de venda ou aluguel *', 'imovel-parceiro-core' ); ?></label>
                        <div class="imovel-parceiro-price-field">
                            <span class="imovel-parceiro-price-prefix" aria-hidden="true">R$</span>
                            <input class="form-control imovel-parceiro-price-input" inputmode="decimal" autocomplete="off" name="property_price" id="property_price" value="<?php echo esc_attr( $property_price ); ?>" placeholder="<?php esc_attr_e( '0,00', 'imovel-parceiro-core' ); ?>" type="text" required>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group mb-3">
                        <label class="form-label" for="property_sec_price"><?php esc_html_e( 'Preço antigo (opcional)', 'imovel-parceiro-core' ); ?></label>
                        <div class="imovel-parceiro-price-field">
                            <span class="imovel-parceiro-price-prefix" aria-hidden="true">R$</span>
                            <input class="form-control imovel-parceiro-price-input" inputmode="decimal" autocomplete="off" name="property_sec_price" id="property_sec_price" value="<?php echo esc_attr( $property_sec_price ); ?>" placeholder="<?php esc_attr_e( '0,00', 'imovel-parceiro-core' ); ?>" type="text">
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group mb-3">
                        <label class="form-label" for="fave_valor-do-condominio"><?php esc_html_e( 'Valor do condomínio', 'imovel-parceiro-core' ); ?></label>
                        <div class="imovel-parceiro-price-field">
                            <span class="imovel-parceiro-price-prefix" aria-hidden="true">R$</span>
                            <input class="form-control imovel-parceiro-price-input" inputmode="decimal" autocomplete="off" name="fave_valor-do-condominio" id="fave_valor-do-condominio" value="<?php echo esc_attr( $condominio_value ); ?>" placeholder="<?php esc_attr_e( '0,00', 'imovel-parceiro-core' ); ?>" type="text">
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="form-group mb-3">
                        <label class="form-label" for="fave_valor-do-iptu"><?php esc_html_e( 'Valor do IPTU', 'imovel-parceiro-core' ); ?></label>
                        <div class="imovel-parceiro-price-field">
                            <span class="imovel-parceiro-price-prefix" aria-hidden="true">R$</span>
                            <input class="form-control imovel-parceiro-price-input" inputmode="decimal" autocomplete="off" name="fave_valor-do-iptu" id="fave_valor-do-iptu" value="<?php echo esc_attr( $iptu_value ); ?>" placeholder="<?php esc_attr_e( '0,00', 'imovel-parceiro-core' ); ?>" type="text">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .imovel-parceiro-price-field{position:relative}
    .imovel-parceiro-price-field .imovel-parceiro-price-prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:#6b7280;pointer-events:none;z-index:2}
    .imovel-parceiro-price-field .form-control.imovel-parceiro-price-input{padding-left:38px}
</style>
<script>
(function(){
    var fields = document.querySelectorAll('.imovel-parceiro-price-input');
    if (!fields.length) {
        return;
    }

    function mask(el){
        var v = el.value.replace(/[^\d,]/g, '');
        var ci = v.indexOf(',');
        var ints = (ci === -1 ? v : v.slice(0, ci)).replace(/\D+/g, '');
        var dec = (ci === -1 ? '' : v.slice(ci + 1)).replace(/\D+/g, '').slice(0, 2);
        var intFmt = ints.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        el.value = intFmt + (ci !== -1 || dec ? ',' + dec : '');
    }

    // Converte o valor inicial para exibição BRL antes de mascarar.
    // O banco guarda canônico ("350000.00"); sem isso o ponto decimal era
    // tratado como milhar e o valor multiplicava por 100 a cada edição.
    function normalizeInitial(el){
        var raw = (el.value || '').trim();
        if (!raw || raw.indexOf(',') !== -1) {
            return;
        }
        var num;
        if (/^\d+\.\d{1,2}$/.test(raw)) {
            num = parseFloat(raw);
        } else if (/^[\d.]+$/.test(raw)) {
            num = parseInt(raw.replace(/\D+/g, ''), 10);
        } else {
            return;
        }
        if (isNaN(num)) {
            return;
        }
        el.value = num.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function ensureCents(el){
        if (el.value && el.value.indexOf(',') === -1) {
            el.value += ',00';
        }
    }

    function toNumeric(el){
        var v = el.value.replace(/[^\d,]/g, '');
        var ci = v.indexOf(',');
        var ints = (ci === -1 ? v : v.slice(0, ci)).replace(/\D+/g, '') || '0';
        var dec = (ci === -1 ? '' : v.slice(ci + 1)).replace(/\D+/g, '').slice(0, 2) || '00';
        return ints + '.' + dec;
    }

    [].forEach.call(fields, function(el){
        el.addEventListener('keydown', function(e){
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            if (e.key && e.key.length === 1 && !/[0-9,]/.test(e.key)) {
                e.preventDefault();
            }
        });
        el.addEventListener('input', function(){
            mask(el);
        });
        el.addEventListener('blur', function(){
            mask(el);
            ensureCents(el);
        });
        normalizeInitial(el);
        mask(el);
        ensureCents(el);
    });

    var form = document.getElementById('submit_property_form');
    if (form) {
        form.addEventListener('submit', function(){
            [].forEach.call(fields, function(el){
                el.value = toNumeric(el);
            });
        });
    }
})();
</script>
