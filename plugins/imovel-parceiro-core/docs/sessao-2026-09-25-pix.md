# Sessão 25/09/2026 — Pix no checkout + deploy Git/cPanel

Registro do que foi feito nesta sessão (IA + usuário). Branch `main`,
repositório `joalisonferreira/site-imovel`.

> Nota: outra sessão ativa trabalha no mesmo repo (commits de layout do
> checkout e `class-agency-inherit.php` sem require). Os commits abaixo
> marcados **[nossa sessão]** são os desta sessão; os demais são citados
> só como contexto.

## 1. Integração Git → cPanel (início da sessão)

- Criado `wp-content/.cpanel.yml` (receita de deploy: copia
  `plugins/imovel-parceiro-core` + `themes/houzez-child` para
  `/home/imobiliaria/public_html/wp-content`).
- `.gitignore` ajustado para versionar o `.cpanel.yml`.
- Criado `C:\xampp\htdocs\tutorial-git-cpanel.md` (tutorial humano, pt-BR).
- Commit inicial + push; clone no cPanel (`~/repositories/site-wp`);
  **Update from Remote → Deploy HEAD Commit** executado com sucesso.
- Criado `C:\xampp\htdocs\AGENTS.md` (memória do opencode — fora do Git):
  caminhos do servidor, rotina de deploy, regra "banco primeiro, código
  depois" e regras de sincronização entre sessões.

## 2. Pesquisa em sessões arquivadas

- Localizado banco do opencode (`opencode.db`); 25 sessões, 1 arquivada
  (`ses_f793e40…`, sync de planos 09–21/09). Mapeados 218 prompts;
  aprendizado principal: conflito marca-d'água × WebP Express e fix
  `wp_get_current_user()` (commit `9cebe16`).

## 3. Pacote da agência (Houzez)

- Mapeado mecanismo nativo (`houzez_is_agent_can_use_agency_package`,
  funções pluggables em `profile_functions.php`, template `profile/package.php`).
- Implementação desta sessão foi sobrescrita pelo trabalho paralelo da
  outra sessão (`class-agency-inherit.php`, sem require — inativo).
  **Não mexer sem alinhar.**

## 4. Exclusão de corretor + backup de produção

- Confirmado: `class-user-deletion.php` já purga tudo (parcerias, pedidos
  HPOS, assinaturas, posts). Lacunas: `audit_logs`/`contact_access_log`/
  `admin_alerts` não purgados.
- **[nossa sessão]** `7fa1f5c` — fix do `prepare()` sem placeholder (linha 72).
- Aceito backup de produção como vigente: **[nossa sessão]** `0c04896`
  (checkout-cleanup, contact-visibility, checkout.css/js etc.).

## 5. Pix do Asaas no checkout (tema principal da sessão)

Problema: `asaas-pix` ativo mas invisível no checkout de assinaturas.

- **[nossa sessão]** `fe16fc7` — novo `includes/class-pix-subscriptions.php`:
  adiciona `supports` de assinatura só ao `asaas-pix` (sem
  `gateway_scheduled_payments` → renovações manuais; cartão/boleto intactos).
- **[nossa sessão]** `349ada0` — causa raiz real: `WC_Asaas\Cart`
  (`cart/class-cart.php:175-177`) dá `unset` incondicional no `asaas-pix`;
  filtro prior. 20 recoloca o gateway após o unset.
- **[nossa sessão]** `68cb4e6` — QR + copia-e-cola no dashboard
  ("Pagamento pendente", `pending_payment_info()` + `render_pending_payments()`).
- **[nossa sessão]** `03de95a` — fix `get_related_orders('ids', ['any'])`
  (fatal Order→int no dashboard).
- **[nossa sessão]** `b68791f` — botão "Já paguei, atualizar status" + PT-BR
  nas telas nativas do Pix (filtros gettext do domínio `woo-asaas`).
- **[nossa sessão]** `a644727` — página dedicada de pagamento Pix
  (`includes/class-pix-payment-page.php`: QR, polling AJAX 5s do webhook,
  redirect ao dashboard; `redirect_order_received` redireciona pedidos
  `asaas-pix` pendentes).
- **[nossa sessão]** `56ce44b` — fix `extract_pix()`: `__ASAAS_ORDER` é
  string JSON com `payload`/`encodedImage`/`expirationDate` (não array).
- **[nossa sessão]** `4c67726` — contador de expiração com dias/data.
- **[nossa sessão]** `92a9d68` — **contador removido**: QR dinâmico do Asaas
  vale 12 meses após o vencimento (doc oficial); exibir validade confundia.
  Janela real de pagamento = ajuste "Validade do Pix" do woo-asaas.

## 6. Patch manual em produção (FORA do Git)

- `woo-asaas/includes/gateway/class-pix.php` (pós-loop do `process_payment`):
  guarda que pula o `pix_info` quando a resposta é a lista de pagamentos da
  assinatura (evitava o 404 `payments//pixQrCode` que quebrava o checkout).
- Espelho local com o mesmo patch existe, invisível ao Git (plugin ignorado).
- **Reaplicar a cada atualização do woo-asaas**; cópia original baixada = rollback.

## 7. Validações

- Local 25/09: Pix aparece no checkout; QR exibe no dashboard; marcar pedido
  como Processando ativa assinatura + libera plano (webhook não chega em
  localhost — `siteurl` local).
- Produção: checkout Pix passou a finalizar sem erro após o patch manual;
  redirect à página do QR validado.
- **Falta:** 1 compra-teste real em produção (única que valida webhook +
  `billingType=PIX` no Asaas).

## 8. Rollbacks

- Pix em assinaturas: `git revert fe16fc7` (+ push + redeploy) ou remover o
  `require` da classe no plugin principal.
- Patch manual prod: restaurar a cópia original do `class-pix.php`.

## 9. Pendências

- Deploy `92a9d68` em produção (Update from Remote → Deploy HEAD Commit) + reteste.
- `class-agency-inherit.php` (outra sessão): sem require, inativo — alinhar.
- Purge de `audit_logs`/`contact_access_log`/`admin_alerts` no user-deletion.
- Ticket ao fornecedor do woo-asaas (bug do `pix_info` pós-loop).
