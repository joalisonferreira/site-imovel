<?php
/**
 * Override Houzez parent: template-parts/dashboard/profile/package.php
 * Esconde o bloco "Permitir que os agentes usem o pacote" no perfil da imobiliária (houzez_agency).
 * O controle de herança de pacote foi centralizado em Gestão > Pacote da agência
 * (Imovel_Parceiro_Agency_Inherit) e o toggle nativo causa confusão/duplicidade.
 */
if ( function_exists( 'houzez_is_agency' ) && houzez_is_agency() ) {
    return;
}

// Para os demais papéis (admin) também não exibe — gestão centralizada.
// Se precisar reativar para admin, troque o return abaixo por lógica condicional.
// Ex.: if ( ! function_exists('houzez_is_admin') || ! houzez_is_admin() ) return;
return;
