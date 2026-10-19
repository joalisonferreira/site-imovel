# Sessão — Checkout Fresh (stitch) — 25/09/2026

> Arquivo de registro da sessão. Versionado com `git add -f` (a raiz é ignorada
> pelo `.gitignore`). **Não é enviado à produção** — o `.cpanel.yml` copia
> apenas `plugins/imovel-parceiro-core` e `themes/houzez-child`.

## Contexto

Página de checkout (assinaturas Woo + Asaas) refeita no padrão do mockup
("checkout fresh / stitch"): header com breadcrumb + etapas, card de cupom,
coluna 7/5 (cobrança + resumo), PF/PJ, pagamento no resumo. Sem alteração de
banco em nenhum commit (só CSS/template/filtro de fields).

## Commits da sessão (todos em `main`, em sync com `origin/main`)

| Commit | Título | O que faz |
|---|---|---|
| `ff52ef1` | fix(checkout): grid 12col p/ todos os campos BR, esconde titulos/cupons duplicados | Grid cobre `persontype/company/cnpj/ie/neighborhood/address_2`; esconde título do tema, `h3` nativos do Woo e bloco adicional injetado em `#customer_details`; remove `do_action` de order notes que duplicava o "2. Informação adicional" |
| `b543792` | fix(checkout): cupom toggle+apply, radios pagamento, remove complemento | Cupom vira `<form method="post">` real com `apply_coupon` (o JS procurava `form.checkout_coupon` mas o template tinha `div`); link usa classe própria `ipc-showcoupon`; radios reposicionados (20px, `accent-color`); `billing/shipping_address_2` removidos no filtro; Número em linha integral |
| `fc5910c` | fix(checkout): order_review 100% e place_order sem padding | `#order_review`/`#order_review_heading` 100% (conteúdo interno estava em ~50%); `#place_order` com `padding: 0` (texto ASSINAR vazava) |
| `fb587bb` | fix(checkout): padding no bloco de termos | `.woocommerce-terms-and-conditions-wrapper { padding: 15px }` |
| `16868f8` | fix(checkout): order_review 100% com padding lateral 30px | Ajuste fino do padding lateral do `#order_review` (30px desktop) |
| `2d1fe1a` | fix(checkout): remove bloco info adicional e card suporte | Card "2. Informação adicional" fora do template + `unset(order_comments)`; card "Suporte Especializado CRECI" fora do template |
| `e9837e3` | fix(checkout): select2 100%, min-width grid, pills wrap, review padding mobile | `.select2-container` travado em 100% com visual dos inputs (o inline-style do Select2 estourava o grid); `min-width: 0` nas colunas; pílulas de etapa com `flex-wrap`; `#order_review` com padding 16px no mobile |
| `6ef9e09` | feat(checkout): icones e subtitulos nos metodos de pagamento | Ícones SVG (cartão/boleto-Pix) + subtítulos ("Aprovação imediata", "Compensação em 1–2 dias úteis", "Aprovação instantânea"), hover, tudo PT-BR, só CSS |
| `1a43b9b` | fix(checkout): esconde asterisco duplo nos labels do cartao Asaas | `.asaas-cc-form-wrapper label .required { display:none }` (Asaas já embute `*` no texto) |

## Arquivos tocados (referência)

- `themes/houzez-child/woocommerce/checkout/form-checkout.php` — template stitch
  (breadcrumb/etapas, cupom, grid 7/5, toggle PF/PJ `#ipc-tab-pf/pj`, resumo).
- `themes/houzez-child/assets/css/checkout.css` — todo o visual fresh
  (grid 12 col, spans por campo, pagamento, `#place_order`, select2, responsivo).
- `themes/houzez-child/assets/js/checkout.js` — sync toggle PF/PJ ↔
  `#billing_persontype` + toggle do cupom (`.ipc-showcoupon`).
- `plugins/imovel-parceiro-core/includes/class-checkout-cleanup.php`
  (`layout_field_sizes`, prioridades 10–110, `unset` de `address_2` e
  `order_comments`, `hide_recurring_totals`, `print_styles`).

## Pendente / conhecido (não feito nesta sessão)

1. **Mês + Ano do cartão empilhados** — o reset global `.form-row`
   (`float:none;width:auto`) quebra as colunas `first/last` do Asaas dentro do
   `payment_box`. Correção prevista: escopar o reset a billing/shipping + grid
   própria p/ `.asaas-cc-form-wrapper`.
2. Labels do cartão em vermelho quando `woocommerce-invalid` (padrão do Woo;
   some ao preencher).
3. Toggle PF/PJ + select nativo `#billing_persontype` duplicados visualmente —
   oferecido ocultar o select, aguardando decisão.
4. `main` contém commits da outra sessão (pagamentos Pix); pushes desta sessão
   foram fast-forward, sem conflito (arquivos distintos).

## Deploy

Padrão: cPanel → Git Version Control → Update from Remote → Deploy HEAD Commit
→ limpar cache (WordPress + Redis). Sem migração de banco.
