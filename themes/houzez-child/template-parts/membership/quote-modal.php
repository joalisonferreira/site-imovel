<?php
/**
 * Modal "Solicitar personalização" para planos com preço sob consulta.
 * Reutilizável: incluído pela página de pacotes e pelo card de plano.
 */

if ( ! class_exists( 'Imovel_Parceiro_Package_Extras' ) ) {
	return;
}

static $ipc_quote_modal_rendered = false;
if ( $ipc_quote_modal_rendered ) {
	return;
}
$ipc_quote_modal_rendered = true;

$ipc_current_user = wp_get_current_user();
$ipc_prefill      = array(
	'name'  => $ipc_current_user->exists() ? $ipc_current_user->display_name : '',
	'email' => $ipc_current_user->exists() ? $ipc_current_user->user_email : '',
	'phone' => $ipc_current_user->exists() ? get_user_meta( $ipc_current_user->ID, 'fave_author_mobile', true ) : '',
);
?>
<div class="ipc-quote-modal" id="ipc-quote-modal" aria-hidden="true">
	<div class="ipc-quote-backdrop" data-ipc-close="1"></div>
	<div class="ipc-quote-dialog" role="dialog" aria-modal="true" aria-labelledby="ipc-quote-title">
		<button type="button" class="ipc-quote-close" data-ipc-close="1" aria-label="<?php esc_attr_e( 'Fechar', 'houzez' ); ?>">&times;</button>
		<h3 id="ipc-quote-title" class="ipc-quote-heading"><?php esc_html_e( 'Solicitar personalização', 'houzez' ); ?></h3>
		<p class="ipc-quote-sub"><?php esc_html_e( 'Conte o que você precisa e nossa equipe entra em contato.', 'houzez' ); ?></p>
		<form class="ipc-quote-form">
			<input type="hidden" name="package_id" value="" />
			<div class="ipc-quote-plan">
				<strong><?php esc_html_e( 'Plano:', 'houzez' ); ?></strong> <span class="ipc-quote-plan-name"></span>
			</div>
			<div class="ipc-quote-row">
				<label><?php esc_html_e( 'Nome', 'houzez' ); ?>
					<input type="text" name="name" value="<?php echo esc_attr( $ipc_prefill['name'] ); ?>" required />
				</label>
				<label><?php esc_html_e( 'E-mail', 'houzez' ); ?>
					<input type="email" name="email" value="<?php echo esc_attr( $ipc_prefill['email'] ); ?>" required />
				</label>
			</div>
			<div class="ipc-quote-row">
				<label><?php esc_html_e( 'Telefone', 'houzez' ); ?>
					<input type="text" name="phone" value="<?php echo esc_attr( $ipc_prefill['phone'] ); ?>" required />
				</label>
				<label><?php esc_html_e( 'Tipo de personalização', 'houzez' ); ?>
					<input type="text" name="customization_type" placeholder="<?php esc_attr_e( 'Ex.: integração, marca, quantidade de anúncios', 'houzez' ); ?>" required />
				</label>
			</div>
			<label class="ipc-quote-message"><?php esc_html_e( 'Detalhes (opcional)', 'houzez' ); ?>
				<textarea name="message" rows="4"></textarea>
			</label>
			<div class="ipc-quote-feedback" role="status"></div>
			<button type="submit" class="btn btn-primary ipc-quote-submit"><?php esc_html_e( 'Enviar solicitação', 'houzez' ); ?></button>
		</form>
	</div>
</div>
<style>
.ipc-quote-modal{position:fixed;inset:0;z-index:100000;display:none}
.ipc-quote-modal.is-open{display:block}
.ipc-quote-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55)}
.ipc-quote-dialog{position:relative;max-width:560px;margin:6vh auto 0;background:#fff;border-radius:16px;padding:28px;box-shadow:0 24px 60px rgba(15,23,42,.25);max-height:88vh;overflow:auto}
.ipc-quote-close{position:absolute;top:12px;right:16px;border:0;background:none;font-size:28px;line-height:1;color:#64748b;cursor:pointer}
.ipc-quote-heading{margin:0 0 6px;font-size:20px;font-weight:700;color:#0f172a}
.ipc-quote-sub{margin:0 0 18px;color:#64748b;font-size:14px}
.ipc-quote-plan{margin-bottom:14px;font-size:14px;color:#334155}
.ipc-quote-row{display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap}
.ipc-quote-row label{flex:1 1 220px;display:block;font-size:13px;font-weight:600;color:#334155}
.ipc-quote-row input,.ipc-quote-message textarea{width:100%;margin-top:6px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
.ipc-quote-message{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:14px}
.ipc-quote-feedback{margin:6px 0 12px;font-size:14px;font-weight:600}
.ipc-quote-feedback.is-error{color:#b91c1c}
.ipc-quote-feedback.is-success{color:#047857}
.ipc-quote-submit{width:100%}
</style>
<script>
(function () {
	var modal = document.getElementById('ipc-quote-modal');
	if (!modal) { return; }
	var form = modal.querySelector('.ipc-quote-form');
	var feedback = modal.querySelector('.ipc-quote-feedback');
	var planName = modal.querySelector('.ipc-quote-plan-name');
	var packageInput = form.querySelector('input[name="package_id"]');
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ipc_request_quote_nonce' ) ); ?>;

	function open(packId, packName) {
		packageInput.value = packId || '';
		planName.textContent = packName || '';
		feedback.textContent = '';
		feedback.className = 'ipc-quote-feedback';
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
	}
	function close() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
	}

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('.ipc-request-quote');
		if (trigger) {
			e.preventDefault();
			open(trigger.getAttribute('data-packid'), trigger.getAttribute('data-packname'));
			return;
		}
		if (e.target.closest('[data-ipc-close]')) {
			close();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { close(); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		feedback.textContent = '';
		feedback.className = 'ipc-quote-feedback';
		var data = new FormData(form);
		data.append('action', 'ipc_request_quote');
		data.append('nonce', nonce);
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					feedback.className = 'ipc-quote-feedback is-success';
					feedback.textContent = res.data && res.data.message ? res.data.message : 'Enviado!';
					form.reset();
				} else {
					feedback.className = 'ipc-quote-feedback is-error';
					feedback.textContent = res && res.data && res.data.message ? res.data.message : 'Não foi possível enviar.';
				}
			})
			.catch(function () {
				feedback.className = 'ipc-quote-feedback is-error';
				feedback.textContent = 'Não foi possível enviar. Tente novamente.';
			});
	});
})();
</script>
