<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Substitutos dos widgets de overview (v1/v2) do Houzez.
 *
 * O método original houzez_meta_field() imprime custom fields monetários
 * (ex.: fave_valor-do-condominio) crus, sem passar por nenhuma formatação.
 * Aqui reaproveitamos 100% do HTML pai e trocamos apenas o valor exibido
 * pelo formato pt-BR com R$.
 *
 * Carregado de forma tardia por Imovel_Parceiro_Overview_Prices, somente
 * após as classes originais existirem.
 */

class Imovel_Parceiro_Overview_V1 extends \Elementor\Property_Overview {

    protected function houzez_meta_field( $item, $i ) {
        $html = parent::houzez_meta_field( $item, $i );
        return Imovel_Parceiro_Overview_Prices::replace_meta_value_in_html( $item, $html );
    }
}

class Imovel_Parceiro_Overview_V2 extends \Elementor\Property_Overview_v2 {

    protected function houzez_meta_field( $item, $i ) {
        $html = parent::houzez_meta_field( $item, $i );
        return Imovel_Parceiro_Overview_Prices::replace_meta_value_in_html( $item, $html );
    }
}
