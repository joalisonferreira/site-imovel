<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integração ImobiSec — validação automática de CRECI (corretores/imobiliárias).
 *
 * Contrato: https://api.imobisec.com.br (Creci API v1.0.0).
 * Auth em todas as chamadas (exceto /check): header X-API-Token-Key.
 * A CHAVE DE API NUNCA É VERSIONADA — mora apenas na option
 * `imovel_parceiro_imobisec_settings`, preenchida em Gestão → CRECI/Cofeci.
 *
 * Fluxo:
 * - GET /crecies/{uf}/{numero}/{PF|PJ} (dado armazenado, barato) no envio da
 *   verificação e via botão "Consultar" na Gestão → Verificação.
 * - ATIVO + nome compatível => auto-aprova (se habilitado).
 * - ATIVO + nome divergente (PF, dados completos, modo híbrido/auto) =>
 *   POST /crecies/ownership-verification (2 créditos) e aprova se verified.
 * - SUSPENSO/CANCELADO/não encontrado/erro => mantém pendente e escala ao admin.
 * - Revalidação periódica (padrão 1 ano, configurável) via PUT .../freshness
 *   assíncrono + webhook; perda da vigência revoga automaticamente.
 *
 * Pré-pago (pay-per-use): o GET .../freshness síncrono é 403 (só pós-pago),
 * por isso a revalidação usa SEMPRE o PUT assíncrono (com webhook quando há
 * URL pública, com polling como fallback).
 * Apenas options + usermeta — nenhum schema novo.
 */
class Imovel_Parceiro_ImobiSec {

    const BASE_URL        = 'https://api.imobisec.com.br';
    const OPT_SETTINGS    = 'imovel_parceiro_imobisec_settings';
    const OPT_COLLECTORS  = 'imovel_parceiro_imobisec_collectors';
    const OPT_NONCE       = 'imovel_parceiro_imobisec_nonce';

    const META_STATUS     = 'imovel_parceiro_imobisec_status';
    const META_NAME       = 'imovel_parceiro_imobisec_nome';
    const META_CHECKED_AT = 'imovel_parceiro_imobisec_consultado_em';
    const META_OWNERSHIP  = 'imovel_parceiro_imobisec_ownership';
    const META_NOTE       = 'imovel_parceiro_imobisec_nota';

    const UF_META         = 'imovel_parceiro_creci_uf';
    const BIRTH_META      = 'imovel_parceiro_birth_date';

    const CRON_HOOK       = 'imovel_parceiro_imobisec_revalidate';
    const REST_ROUTE      = 'imovel-parceiro/v1/imobisec-webhook';

    public function __construct() {
        // Captura UF + nascimento no envio (inicial e info adicional).
        add_action( 'houzez_before_verification_request', array( $this, 'capture_identity_fields' ), 10, 2 );
        add_action( 'houzez_before_additional_info_submission', array( $this, 'capture_identity_fields' ), 10, 2 );
        add_filter( 'houzez_verification_request_save_data', array( $this, 'merge_identity_into_save_data' ), 10, 2 );
        add_action( 'houzez_after_additional_info_submission', array( $this, 'merge_identity_after_additional_info' ), 10, 2 );

        // Auto-check após o envio da verificação.
        add_action( 'houzez_after_verification_request', array( $this, 'maybe_auto_check' ), 20, 2 );
        add_action( 'houzez_after_additional_info_submission', array( $this, 'maybe_auto_check' ), 20, 2 );

        // Revalidação periódica + webhook.
        add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
        add_action( self::CRON_HOOK, array( $this, 'revalidate_due' ) );
        add_action( 'rest_api_init', array( $this, 'register_webhook' ) );
    }

    /* ------------------------------------------------------------------ */
    /* Config                                                              */
    /* ------------------------------------------------------------------ */

    public static function states() {
        return array(
            'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
            'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN',
            'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
        );
    }

    public static function defaults() {
        return array(
            'api_key'       => '',
            'purpose'       => 'verificacao-cadastral',
            'auto_approve'  => 1,
            'strong_mode'   => 'hybrid', // auto|manual|hybrid
            'reval_number'  => 1,
            'reval_unit'    => 'years', // months|years
            'min_credits'   => 10,
            'mock_mode'     => 0,
            'webhook_secret'=> '',
            'last_credits'  => '',
        );
    }

    public static function settings() {
        $saved = get_option( self::OPT_SETTINGS, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }
        return wp_parse_args( $saved, self::defaults() );
    }

    public static function save_settings( $input ) {
        $current = self::settings();
        $out     = $current;

        if ( isset( $input['api_key'] ) ) {
            $key = trim( (string) $input['api_key'] );
            if ( '' !== $key ) {
                // Nunca sobrescreve a chave com campo vazio (placeholder ********).
                $out['api_key'] = sanitize_text_field( $key );
            }
        }
        if ( isset( $input['purpose'] ) ) {
            $out['purpose'] = substr( sanitize_text_field( $input['purpose'] ), 0, 120 );
            if ( '' === $out['purpose'] ) {
                $out['purpose'] = 'verificacao-cadastral';
            }
        }
        $out['auto_approve'] = ! empty( $input['auto_approve'] ) ? 1 : 0;
        if ( isset( $input['strong_mode'] ) && in_array( $input['strong_mode'], array( 'auto', 'manual', 'hybrid' ), true ) ) {
            $out['strong_mode'] = $input['strong_mode'];
        }
        $out['reval_number'] = max( 1, absint( isset( $input['reval_number'] ) ? $input['reval_number'] : 1 ) );
        if ( isset( $input['reval_unit'] ) && in_array( $input['reval_unit'], array( 'months', 'years' ), true ) ) {
            $out['reval_unit'] = $input['reval_unit'];
        }
        $out['min_credits'] = max( 0, absint( isset( $input['min_credits'] ) ? $input['min_credits'] : 0 ) );
        $out['mock_mode']   = ! empty( $input['mock_mode'] ) ? 1 : 0;
        if ( ! empty( $input['regenerate_secret'] ) || '' === $current['webhook_secret'] ) {
            $out['webhook_secret'] = wp_generate_password( 32, false );
        }

        update_option( self::OPT_SETTINGS, $out, false );
        return $out;
    }

    public static function revalidation_days() {
        $s = self::settings();
        $n = max( 1, (int) $s['reval_number'] );
        return 'months' === $s['reval_unit'] ? $n * 30 : $n * 365;
    }

    public static function is_configured() {
        $s = self::settings();
        return '' !== trim( (string) $s['api_key'] );
    }

    /* ------------------------------------------------------------------ */
    /* HTTP client                                                         */
    /* ------------------------------------------------------------------ */

    protected function api_request( $method, $path, $body = null, $extra_headers = array() ) {
        $settings = self::settings();
        $url      = self::BASE_URL . $path;

        $headers = array_merge(
            array(
                'X-API-Token-Key' => trim( (string) $settings['api_key'] ),
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ),
            $extra_headers
        );

        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 25,
        );
        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return array( 'code' => 0, 'data' => null, 'credits' => null, 'error' => $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        $credits = null;
        $headers_res = wp_remote_retrieve_headers( $response );
        if ( $headers_res ) {
            foreach ( array( 'x-remaining-credits', 'X-Remaining-Credits' ) as $hk ) {
                if ( isset( $headers_res[ $hk ] ) ) {
                    $credits = $headers_res[ $hk ];
                    break;
                }
            }
            if ( null === $credits && method_exists( $headers_res, 'getAll' ) ) {
                $all = $headers_res->getAll();
                foreach ( (array) $all as $k => $v ) {
                    if ( 'x-remaining-credits' === strtolower( (string) $k ) ) {
                        $credits = is_array( $v ) ? reset( $v ) : $v;
                        break;
                    }
                }
            }
        }
        if ( null !== $credits && '' !== $credits ) {
            $settings['last_credits'] = sanitize_text_field( (string) $credits );
            update_option( self::OPT_SETTINGS, $settings, false );
        }

        $error = null;
        if ( $code < 200 || $code >= 300 ) {
            if ( is_array( $data ) ) {
                foreach ( array( 'message', 'error', 'detail', 'title' ) as $k ) {
                    if ( isset( $data[ $k ] ) && is_string( $data[ $k ] ) && '' !== $data[ $k ] ) {
                        $error = $data[ $k ];
                        break;
                    }
                }
            }
            if ( null === $error ) {
                $error = sprintf( __( 'HTTP %d', 'imovel-parceiro-core' ), $code );
            }
        }

        return array( 'code' => $code, 'data' => $data, 'credits' => $credits, 'error' => $error );
    }

    protected function creci_path( $state, $number, $type ) {
        $settings = self::settings();
        $base     = ! empty( $settings['mock_mode'] ) ? '/crecies-mock' : '/crecies';
        return $base . '/' . rawurlencode( $state ) . '/' . rawurlencode( $number ) . '/' . rawurlencode( $type );
    }

    public function get_creci( $state, $number, $type ) {
        return $this->api_request( 'GET', $this->creci_path( $state, $number, $type ) );
    }

    /**
     * Dispara revalidação assíncrona (PUT .../freshness[/webhook]).
     * Retorna array com 'fresh' (bool), 'collector_id' ou 'error'.
     */
    public function request_freshness( $state, $number, $type, $user_id = 0 ) {
        $settings   = self::settings();
        $webhookurl = rest_url( self::REST_ROUTE );
        $res        = $this->api_request(
            'PUT',
            $this->creci_path( $state, $number, $type ) . '/freshness/webhook',
            array(
                'freshnessDays' => self::revalidation_days(),
                'webhookUrl'    => $webhookurl,
                'webhookSecret' => $settings['webhook_secret'],
            )
        );

        if ( 404 === $res['code'] || ( 400 <= $res['code'] && 404 !== $res['code'] && 403 !== $res['code'] && 409 !== $res['code'] ) ) {
            // Endpoint com webhook pode não existir no plano — tenta o PUT simples.
            $res = $this->api_request(
                'PUT',
                $this->creci_path( $state, $number, $type ) . '/freshness',
                array( 'freshnessDays' => self::revalidation_days() )
            );
        }

        if ( is_array( $res['data'] ) && isset( $res['data']['collectorId'] ) ) {
            $this->map_collector( (string) $res['data']['collectorId'], (int) $user_id );
            update_user_meta( (int) $user_id, self::META_NOTE, '' );
            return array( 'fresh' => false, 'collector_id' => (string) $res['data']['collectorId'] );
        }
        if ( 200 === $res['code'] ) {
            return array( 'fresh' => true, 'collector_id' => '' );
        }
        return array( 'fresh' => false, 'collector_id' => '', 'error' => $res['error'] ? $res['code'] . ' — ' . $res['error'] : (string) $res['code'] );
    }

    public function check_collector( $collector_id ) {
        return $this->api_request( 'GET', '/crecies/collector/' . rawurlencode( $collector_id ) );
    }

    /**
     * Verificação forte (ownership): 2 créditos por chamada.
     */
    public function ownership_check( $candidate ) {
        $settings = self::settings();
        $body     = array(
            'cpf'       => $candidate['cpf'],
            'birthDate' => $candidate['birth'],
            'creci'     => array(
                'number' => (int) $candidate['number'],
                'state'  => $candidate['state'],
                'type'   => $candidate['type'],
            ),
        );
        if ( '' !== $candidate['name'] ) {
            $body['name'] = $candidate['name'];
        }
        return $this->api_request(
            'POST',
            '/crecies/ownership-verification',
            $body,
            array( 'X-Purpose' => $settings['purpose'] )
        );
    }

    /* ------------------------------------------------------------------ */
    /* Candidato (dados locais do usuário)                                 */
    /* ------------------------------------------------------------------ */

    public static function is_agent_or_agency( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return false;
        }
        return (bool) array_intersect( (array) $user->roles, array( 'houzez_agent', 'houzez_agency' ) );
    }

    /**
     * Monta o candidato à consulta. Retorna array ou WP_Error (dado faltante).
     */
    public static function candidate_for( $user_id ) {
        $user_id = absint( $user_id );
        $license = preg_replace( '/\D/', '', (string) get_user_meta( $user_id, 'fave_author_license', true ) );
        $uf      = strtoupper( trim( (string) get_user_meta( $user_id, self::UF_META, true ) ) );
        $person  = strtolower( trim( (string) get_user_meta( $user_id, 'imovel_parceiro_person_type', true ) ) );
        $type    = ( 'cnpj' === $person ) ? 'PJ' : 'PF';
        $cpf     = preg_replace( '/\D/', '', (string) get_user_meta( $user_id, 'fave_author_tax_no', true ) );
        $birth   = trim( (string) get_user_meta( $user_id, self::BIRTH_META, true ) );

        $vdata = get_user_meta( $user_id, 'houzez_verification_data', true );
        $name  = '';
        if ( is_array( $vdata ) && ! empty( $vdata['full_name'] ) ) {
            $name = (string) $vdata['full_name'];
        }
        if ( '' === $name ) {
            $name = (string) get_user_meta( $user_id, 'imovel_parceiro_full_name', true );
        }
        if ( '' === $name ) {
            $u = get_userdata( $user_id );
            $name = $u ? (string) $u->display_name : '';
        }

        if ( '' === $license ) {
            return new WP_Error( 'no_creci', __( 'CRECI não informado no cadastro.', 'imovel-parceiro-core' ) );
        }
        if ( '' === $uf || ! in_array( $uf, self::states(), true ) ) {
            return new WP_Error( 'no_uf', __( 'UF do CRECI não informada. Envie novamente a verificação com a UF.', 'imovel-parceiro-core' ) );
        }

        return array(
            'number' => ltrim( $license, '0' ) === '' ? $license : ltrim( $license, '0' ),
            'state'  => $uf,
            'type'   => $type,
            'cpf'    => ( 11 === strlen( $cpf ) ) ? $cpf : '',
            'birth'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth ) ? $birth : '',
            'name'   => $name,
        );
    }

    /* ------------------------------------------------------------------ */
    /* UF + nascimento no envio da verificação                             */
    /* ------------------------------------------------------------------ */

    public function capture_identity_fields( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::is_agent_or_agency( $user_id ) ) {
            return;
        }

        $uf = isset( $_POST['imovel_creci_uf'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['imovel_creci_uf'] ) ) ) : '';
        if ( '' !== $uf && in_array( $uf, self::states(), true ) ) {
            update_user_meta( $user_id, self::UF_META, $uf );
        } elseif ( doing_action( 'houzez_before_verification_request' ) ) {
            // No envio inicial a UF é obrigatória para corretor/imobiliária.
            wp_send_json_error( array( 'message' => __( 'Informe a UF do seu CRECI.', 'imovel-parceiro-core' ) ) );
        }

        if ( isset( $_POST['imovel_birth_date'] ) && '' !== trim( (string) wp_unslash( $_POST['imovel_birth_date'] ) ) ) {
            $birth = sanitize_text_field( wp_unslash( $_POST['imovel_birth_date'] ) );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth ) ) {
                update_user_meta( $user_id, self::BIRTH_META, $birth );
            }
        }
    }

    public function merge_identity_into_save_data( $verification_data, $user_id ) {
        if ( ! is_array( $verification_data ) ) {
            return $verification_data;
        }
        $uf    = get_user_meta( absint( $user_id ), self::UF_META, true );
        $birth = get_user_meta( absint( $user_id ), self::BIRTH_META, true );
        if ( '' !== $uf ) {
            $verification_data['creci_uf'] = $uf;
        }
        if ( '' !== $birth ) {
            $verification_data['birth_date'] = $birth;
        }
        return $verification_data;
    }

    public function merge_identity_after_additional_info( $user_id ) {
        $user_id = absint( $user_id );
        $vdata   = get_user_meta( $user_id, 'houzez_verification_data', true );
        if ( ! is_array( $vdata ) ) {
            return;
        }
        $merged = $this->merge_identity_into_save_data( $vdata, $user_id );
        if ( $merged !== $vdata ) {
            update_user_meta( $user_id, 'houzez_verification_data', $merged );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Decisão                                                             */
    /* ------------------------------------------------------------------ */

    public static function normalize_name( $name ) {
        $name = mb_strtolower( (string) $name, 'UTF-8' );
        // Mapa manual (sem iconv//TRANSLIT, que falha em alguns builds de PHP).
        $map = array(
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        );
        $name = strtr( $name, $map );
        $name = preg_replace( '/[^a-z ]/', ' ', $name );
        $name = preg_replace( '/\s+/', ' ', (string) $name );
        return trim( (string) $name );
    }

    /**
     * Nomes compatíveis: iguais ou um contém o outro (tolerância a
     * abreviações e ordem). A prova real é a verificação forte.
     */
    public static function names_compatible( $local, $api ) {
        $a = self::normalize_name( $local );
        $b = self::normalize_name( $api );
        if ( '' === $a || '' === $b ) {
            return false;
        }
        if ( $a === $b ) {
            return true;
        }
        return ( false !== strpos( $a, $b ) ) || ( false !== strpos( $b, $a ) );
    }

    /**
     * Status ativo na base: a API retorna ATIVO (real) ou ACTIVE (mock).
     */
    public static function is_active_status( $status ) {
        $s = strtoupper( trim( (string) $status ) );
        $s = str_replace( array( 'Á', 'Â' ), 'A', $s );
        return ( 'ATIVO' === $s || 'ACTIVE' === $s );
    }

    public static function result_summary( $data ) {
        if ( ! is_array( $data ) ) {
            return '-';
        }
        $status = isset( $data['status'] ) ? (string) $data['status'] : '';
        $name   = isset( $data['name'] ) ? (string) $data['name'] : '';
        $label  = $status;
        if ( '' !== $name ) {
            $label .= ( '' !== $label ? ' — ' : '' ) . $name;
        }
        return '' !== $label ? $label : '-';
    }

    protected function persist_basic( $user_id, $data ) {
        update_user_meta( $user_id, self::META_STATUS, isset( $data['status'] ) ? sanitize_text_field( (string) $data['status'] ) : '' );
        if ( isset( $data['name'] ) ) {
            update_user_meta( $user_id, self::META_NAME, sanitize_text_field( (string) $data['name'] ) );
        }
        update_user_meta( $user_id, self::META_CHECKED_AT, current_time( 'mysql' ) );
    }

    protected function set_note( $user_id, $note ) {
        update_user_meta( $user_id, self::META_NOTE, sanitize_text_field( (string) $note ) );
    }

    public function approve_user( $user_id, $note = '' ) {
        $user_id = absint( $user_id );
        $vdata   = get_user_meta( $user_id, 'houzez_verification_data', true );
        if ( ! is_array( $vdata ) ) {
            return false;
        }
        $vdata['status']       = 'approved';
        $vdata['processed_on'] = current_time( 'mysql' );
        update_user_meta( $user_id, 'houzez_verification_data', $vdata );
        update_user_meta( $user_id, 'houzez_verification_status', 'approved' );

        if ( class_exists( 'Imovel_Parceiro_Verification_Notifications' ) ) {
            Imovel_Parceiro_Verification_Notifications::record_dashboard_history( $user_id, 'approve', $note );
            Imovel_Parceiro_Verification_Notifications::notify_dashboard_decision( $user_id, 'approve', $note, $vdata );
        }
        return true;
    }

    protected function admin_email() {
        $email = apply_filters( 'imovel_parceiro_platform_admin_email', '' );
        if ( ! is_email( $email ) ) {
            $email = get_option( 'admin_email' );
        }
        return $email;
    }

    protected function notify_admin_review( $user_id, $title, $lines ) {
        $user  = get_userdata( absint( $user_id ) );
        $email = $this->admin_email();
        if ( ! $user || ! is_email( $email ) || ! class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
            return;
        }
        $body  = sprintf( __( 'Usuário: %s (%s)', 'imovel-parceiro-core' ), $user->display_name, $user->user_email ) . "\n\n";
        foreach ( (array) $lines as $line ) {
            $body .= '• ' . $line . "\n";
        }
        $subject = '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $title;
        Imovel_Parceiro_Email_Template::send(
            $email,
            $subject,
            Imovel_Parceiro_Email_Template::text_to_html( $body ),
            array( 'title' => $subject )
        );
    }

    /**
     * Auto-check no envio da verificação (modo híbrido por padrão).
     */
    public function maybe_auto_check( $user_id, $verification_data = array() ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! self::is_agent_or_agency( $user_id ) ) {
            return;
        }
        // Só atua sobre pedidos ainda pendentes.
        if ( 'pending' !== get_user_meta( $user_id, 'houzez_verification_status', true ) ) {
            return;
        }
        if ( ! self::is_configured() ) {
            $this->set_note( $user_id, __( 'Consulta ImobiSec indisponível: chave de API não configurada.', 'imovel-parceiro-core' ) );
            return;
        }

        $candidate = self::candidate_for( $user_id );
        if ( is_wp_error( $candidate ) ) {
            $this->set_note( $user_id, $candidate->get_error_message() );
            return;
        }

        $res = $this->get_creci( $candidate['state'], $candidate['number'], $candidate['type'] );
        if ( 0 === $res['code'] ) {
            $this->set_note( $user_id, sprintf( __( 'Falha de rede na consulta ImobiSec: %s', 'imovel-parceiro-core' ), $res['error'] ) );
            return;
        }
        if ( 401 === $res['code'] || 403 === $res['code'] ) {
            $this->set_note( $user_id, __( 'Consulta ImobiSec negada (chave inválida ou sem permissão).', 'imovel-parceiro-core' ) );
            $this->notify_admin_review( $user_id, __( 'ImobiSec: chave de API rejeitada', 'imovel-parceiro-core' ), array( sprintf( __( 'HTTP %d na consulta do CRECI.', 'imovel-parceiro-core' ), $res['code'] ) ) );
            return;
        }
        if ( 404 === $res['code'] ) {
            $this->set_note( $user_id, sprintf( __( 'CRECI %s/%s não encontrado na base.', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'] ) );
            $this->notify_admin_review(
                $user_id,
                __( 'Verificação: CRECI não encontrado', 'imovel-parceiro-core' ),
                array(
                    sprintf( __( 'CRECI %s/%s (%s) retornou 404 na ImobiSec.', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'], $candidate['type'] ),
                    __( 'Confira o número e a UF com o solicitante.', 'imovel-parceiro-core' ),
                )
            );
            return;
        }
        if ( 200 !== $res['code'] || ! is_array( $res['data'] ) ) {
            $this->set_note( $user_id, sprintf( __( 'ImobiSec respondeu HTTP %d.', 'imovel-parceiro-core' ), $res['code'] ) );
            return;
        }

        $data = $res['data'];
        $this->persist_basic( $user_id, $data );
        $status = strtoupper( trim( (string) ( isset( $data['status'] ) ? $data['status'] : '' ) ) );
        $api_name = isset( $data['name'] ) ? (string) $data['name'] : '';

        if ( ! self::is_active_status( $status ) ) {
            $this->set_note( $user_id, sprintf( __( 'CRECI com status %s na base.', 'imovel-parceiro-core' ), $status ) );
            $this->notify_admin_review(
                $user_id,
                __( 'Verificação: CRECI não está ativo', 'imovel-parceiro-core' ),
                array(
                    sprintf( __( 'CRECI %s/%s (%s): status %s, nome na base: %s.', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'], $candidate['type'], $status, $api_name ),
                    __( 'A solicitação permanece pendente para decisão manual.', 'imovel-parceiro-core' ),
                )
            );
            return;
        }

        $settings = self::settings();
        if ( self::names_compatible( $candidate['name'], $api_name ) ) {
            if ( ! empty( $settings['auto_approve'] ) ) {
                $this->approve_user( $user_id, __( 'Aprovado automaticamente: CRECI ativo e nome compatível (ImobiSec).', 'imovel-parceiro-core' ) );
                $this->set_note( $user_id, __( 'Aprovado automaticamente pela ImobiSec.', 'imovel-parceiro-core' ) );
            } else {
                $this->set_note( $user_id, __( 'CRECI ativo e nome compatível — aguardando aprovação manual (auto-aprovação desligada).', 'imovel-parceiro-core' ) );
            }
            return;
        }

        // Nome diverge: verificação forte (híbrido = automática quando há dados).
        $strong_allowed = in_array( $settings['strong_mode'], array( 'auto', 'hybrid' ), true );
        if ( 'PF' === $candidate['type'] && '' !== $candidate['cpf'] && '' !== $candidate['birth'] && $strong_allowed ) {
            $own = $this->ownership_check( $candidate );
            update_user_meta( $user_id, self::META_OWNERSHIP, wp_json_encode( array( 'code' => $own['code'], 'at' => current_time( 'mysql' ) ) ) );
            if ( 200 === $own['code'] && is_array( $own['data'] ) && isset( $own['data']['result'] ) && true === $own['data']['result']['verified'] ) {
                $this->approve_user( $user_id, __( 'Aprovado automaticamente: verificação forte ImobiSec (CPF + nascimento + nome conferem).', 'imovel-parceiro-core' ) );
                $this->set_note( $user_id, __( 'Aprovado pela verificação forte (ownership).', 'imovel-parceiro-core' ) );
                return;
            }
            $detail = $own['error'] ? $own['error'] : sprintf( __( 'HTTP %d', 'imovel-parceiro-core' ), $own['code'] );
            $this->set_note( $user_id, sprintf( __( 'Verificação forte não confirmou titularidade (%s).', 'imovel-parceiro-core' ), $detail ) );
            $this->notify_admin_review(
                $user_id,
                __( 'Verificação: nome diverge do CRECI', 'imovel-parceiro-core' ),
                array(
                    sprintf( __( 'CRECI %s/%s ativo, mas o nome diverge — cadastro: %s | base: %s.', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'], $candidate['name'], $api_name ),
                    sprintf( __( 'Verificação forte: %s.', 'imovel-parceiro-core' ), $detail ),
                    __( 'A solicitação permanece pendente para decisão manual.', 'imovel-parceiro-core' ),
                )
            );
            return;
        }

        $reason = 'PF' !== $candidate['type']
            ? __( 'pessoa jurídica: titularidade automática indisponível', 'imovel-parceiro-core' )
            : __( 'CPF ou data de nascimento ausentes', 'imovel-parceiro-core' );
        $this->set_note( $user_id, sprintf( __( 'Nome diverge da base (%s) — aguardando decisão manual.', 'imovel-parceiro-core' ), $reason ) );
        $this->notify_admin_review(
            $user_id,
            __( 'Verificação: nome diverge do CRECI', 'imovel-parceiro-core' ),
            array(
                sprintf( __( 'CRECI %s/%s ativo — cadastro: %s | base: %s (%s).', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'], $candidate['name'], $api_name, $reason ),
                __( 'Use o botão “Verificação forte” na Gestão → Verificação ou decida manualmente.', 'imovel-parceiro-core' ),
            )
        );
    }

    /* ------------------------------------------------------------------ */
    /* Ações manuais (Gestão → Verificação)                                */
    /* ------------------------------------------------------------------ */

    public function consult_now( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::is_agent_or_agency( $user_id ) ) {
            return __( 'Usuário não é corretor nem imobiliária.', 'imovel-parceiro-core' );
        }
        if ( ! self::is_configured() ) {
            return __( 'Chave de API não configurada em Gestão → CRECI/Cofeci.', 'imovel-parceiro-core' );
        }
        $candidate = self::candidate_for( $user_id );
        if ( is_wp_error( $candidate ) ) {
            $this->set_note( $user_id, $candidate->get_error_message() );
            return $candidate->get_error_message();
        }
        $res = $this->get_creci( $candidate['state'], $candidate['number'], $candidate['type'] );
        if ( 0 === $res['code'] ) {
            $msg = sprintf( __( 'Falha de rede: %s', 'imovel-parceiro-core' ), $res['error'] );
            $this->set_note( $user_id, $msg );
            return $msg;
        }
        if ( 404 === $res['code'] ) {
            $msg = sprintf( __( 'CRECI %s/%s não encontrado.', 'imovel-parceiro-core' ), $candidate['number'], $candidate['state'] );
            $this->set_note( $user_id, $msg );
            return $msg;
        }
        if ( 200 !== $res['code'] || ! is_array( $res['data'] ) ) {
            $msg = sprintf( __( 'ImobiSec respondeu HTTP %d: %s', 'imovel-parceiro-core' ), $res['code'], $res['error'] );
            $this->set_note( $user_id, $msg );
            return $msg;
        }
        $this->persist_basic( $user_id, $res['data'] );
        $msg = sprintf( __( 'Consulta OK: %s.', 'imovel-parceiro-core' ), self::result_summary( $res['data'] ) );
        $this->set_note( $user_id, $msg );
        return $msg;
    }

    public function strong_now( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::is_configured() ) {
            return __( 'Chave de API não configurada em Gestão → CRECI/Cofeci.', 'imovel-parceiro-core' );
        }
        $candidate = self::candidate_for( $user_id );
        if ( is_wp_error( $candidate ) ) {
            $this->set_note( $user_id, $candidate->get_error_message() );
            return $candidate->get_error_message();
        }
        if ( 'PF' !== $candidate['type'] ) {
            return __( 'Verificação forte disponível apenas para pessoa física.', 'imovel-parceiro-core' );
        }
        if ( '' === $candidate['cpf'] || '' === $candidate['birth'] ) {
            return __( 'CPF ou data de nascimento ausentes no cadastro.', 'imovel-parceiro-core' );
        }
        $own = $this->ownership_check( $candidate );
        update_user_meta( $user_id, self::META_OWNERSHIP, wp_json_encode( array( 'code' => $own['code'], 'at' => current_time( 'mysql' ) ) ) );
        if ( 200 === $own['code'] && is_array( $own['data'] ) && isset( $own['data']['result'] ) ) {
            $r = $own['data']['result'];
            if ( true === $r['verified'] ) {
                $msg = __( 'Verificação forte confirmou a titularidade (2 créditos utilizados).', 'imovel-parceiro-core' );
                $this->set_note( $user_id, $msg );
                return $msg;
            }
            $msg = sprintf(
                __( 'Titularidade NÃO confirmada — nome: %s, nascimento: %s, CPF×CRECI: %s.', 'imovel-parceiro-core' ),
                isset( $r['nameMatch'] ) ? $r['nameMatch'] : '?',
                isset( $r['birthDateMatch'] ) ? ( $r['birthDateMatch'] ? __( 'confere', 'imovel-parceiro-core' ) : __( 'diverge', 'imovel-parceiro-core' ) ) : '?',
                isset( $r['cpfMatchesCreciDocument'] ) ? ( $r['cpfMatchesCreciDocument'] ? __( 'confere', 'imovel-parceiro-core' ) : __( 'diverge', 'imovel-parceiro-core' ) ) : '?'
            );
            $this->set_note( $user_id, $msg );
            return $msg;
        }
        $msg = sprintf( __( 'Verificação forte respondeu HTTP %d: %s (404 também consome 2 créditos).', 'imovel-parceiro-core' ), $own['code'], $own['error'] );
        $this->set_note( $user_id, $msg );
        return $msg;
    }

    /* ------------------------------------------------------------------ */
    /* Revalidação (cron) + webhook                                        */
    /* ------------------------------------------------------------------ */

    public function maybe_schedule_cron() {
        if ( ! self::is_configured() ) {
            return;
        }
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public function revalidate_due() {
        if ( ! self::is_configured() ) {
            return;
        }
        $days  = self::revalidation_days();
        $users = get_users(
            array(
                'role__in'   => array( 'houzez_agent', 'houzez_agency' ),
                'meta_key'   => 'houzez_verification_status',
                'meta_value' => 'approved',
                'number'     => 50,
                'fields'     => 'ID',
            )
        );
        foreach ( $users as $user_id ) {
            $checked = get_user_meta( $user_id, self::META_CHECKED_AT, true );
            if ( '' !== $checked && ( time() - strtotime( $checked ) ) < ( $days * DAY_IN_SECONDS ) ) {
                continue;
            }
            $candidate = self::candidate_for( $user_id );
            if ( is_wp_error( $candidate ) ) {
                continue;
            }
            // Segurança: no máximo 1 disparo por usuário/dia.
            $lock = get_user_meta( $user_id, 'imovel_parceiro_imobisec_reval_lock', true );
            if ( '' !== $lock && strtotime( $lock ) > ( time() - DAY_IN_SECONDS ) ) {
                continue;
            }
            update_user_meta( $user_id, 'imovel_parceiro_imobisec_reval_lock', current_time( 'mysql' ) );
            $fresh = $this->request_freshness( $candidate['state'], $candidate['number'], $candidate['type'], $user_id );
            if ( ! empty( $fresh['fresh'] ) ) {
                $this->consult_now( $user_id );
            } elseif ( empty( $fresh['collector_id'] ) && ! empty( $fresh['error'] ) ) {
                $this->set_note( $user_id, sprintf( __( 'Revalidação falhou: %s.', 'imovel-parceiro-core' ), $fresh['error'] ) );
            }
            // Com collector_id, o resultado chega pelo webhook.
        }
    }

    protected function map_collector( $collector_id, $user_id ) {
        $map = get_option( self::OPT_COLLECTORS, array() );
        if ( ! is_array( $map ) ) {
            $map = array();
        }
        $map[ $collector_id ] = array( 'user_id' => (int) $user_id, 'at' => current_time( 'mysql' ), 'done' => 0 );
        // Poda: mantém os 200 mais recentes.
        if ( count( $map ) > 200 ) {
            $map = array_slice( $map, -200, 200, true );
        }
        update_option( self::OPT_COLLECTORS, $map, false );
    }

    public function register_webhook() {
        register_rest_route(
            'imovel-parceiro/v1',
            '/imobisec-webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle_webhook( $request ) {
        $settings = self::settings();
        $token    = $request->get_header( 'X-Imobisec-Token' );
        if ( '' === $settings['webhook_secret'] || ! hash_equals( (string) $settings['webhook_secret'], (string) $token ) ) {
            return new WP_Error( 'forbidden', __( 'Assinatura inválida.', 'imovel-parceiro-core' ), array( 'status' => 403 ) );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = array();
        }
        $collector_id = (string) $request->get_header( 'X-Imobisec-Collector-ID' );
        if ( '' === $collector_id && isset( $body['collectorId'] ) ) {
            $collector_id = (string) $body['collectorId'];
        }

        $map = get_option( self::OPT_COLLECTORS, array() );
        if ( '' === $collector_id || ! isset( $map[ $collector_id ] ) ) {
            return new WP_Error( 'unknown', __( 'Collector desconhecido.', 'imovel-parceiro-core' ), array( 'status' => 404 ) );
        }
        if ( ! empty( $map[ $collector_id ]['done'] ) ) {
            return array( 'ok' => true, 'deduped' => true ); // at-least-once: confirma sem reprocessar.
        }

        $user_id = (int) $map[ $collector_id ]['user_id'];
        $result  = array();
        foreach ( array( 'result', 'creci', 'data' ) as $k ) {
            if ( isset( $body[ $k ] ) && is_array( $body[ $k ] ) ) {
                $result = array_merge( $result, $body[ $k ] );
            }
        }
        $status = '';
        foreach ( array( 'status', 'creciStatus' ) as $k ) {
            if ( isset( $result[ $k ] ) && '' !== $result[ $k ] ) {
                $status = strtoupper( trim( (string) $result[ $k ] ) );
                break;
            }
        }

        $map[ $collector_id ]['done'] = 1;
        update_option( self::OPT_COLLECTORS, $map, false );

        if ( '' !== $status ) {
            $this->persist_basic( $user_id, array( 'status' => $status, 'name' => isset( $result['name'] ) ? $result['name'] : '' ) );
        }
        if ( self::is_active_status( $status ) ) {
            $this->set_note( $user_id, __( 'Revalidação ImobiSec: CRECI segue ativo.', 'imovel-parceiro-core' ) );
            return array( 'ok' => true );
        }

        // Perdeu a vigência: revoga automaticamente e escala.
        $vdata = get_user_meta( $user_id, 'houzez_verification_data', true );
        if ( is_array( $vdata ) ) {
            $vdata['status']       = 'pending';
            $vdata['processed_on'] = current_time( 'mysql' );
            update_user_meta( $user_id, 'houzez_verification_data', $vdata );
        }
        update_user_meta( $user_id, 'houzez_verification_status', 'pending' );
        $note = sprintf( __( 'Revalidação ImobiSec: CRECI com status %s — verificação revogada automaticamente.', 'imovel-parceiro-core' ), $status );
        $this->set_note( $user_id, $note );
        if ( class_exists( 'Imovel_Parceiro_Verification_Notifications' ) ) {
            Imovel_Parceiro_Verification_Notifications::record_dashboard_history( $user_id, 'revoke', $note );
            Imovel_Parceiro_Verification_Notifications::notify_dashboard_decision( $user_id, 'revoke', $note, is_array( $vdata ) ? $vdata : array() );
        }
        $this->notify_admin_review(
            $user_id,
            __( 'ImobiSec: verificação revogada na revalidação', 'imovel-parceiro-core' ),
            array( $note )
        );
        return array( 'ok' => true, 'revoked' => true );
    }

    /* ------------------------------------------------------------------ */
    /* Leitura para a Gestão                                               */
    /* ------------------------------------------------------------------ */

    public static function list_data( $user_id ) {
        $user_id = absint( $user_id );
        $status  = strtoupper( trim( (string) get_user_meta( $user_id, self::META_STATUS, true ) ) );
        $name    = (string) get_user_meta( $user_id, self::META_NAME, true );
        $checked = (string) get_user_meta( $user_id, self::META_CHECKED_AT, true );
        $note    = (string) get_user_meta( $user_id, self::META_NOTE, true );

        $badge = 'secondary';
        $label = __( 'Não consultado', 'imovel-parceiro-core' );
        if ( '' !== $status ) {
            $label = $status . ( '' !== $name ? ' — ' . $name : '' );
            if ( false !== strpos( str_replace( array( 'Á', 'Â' ), 'A', $status ), 'ATIVO' ) || false !== strpos( $status, 'ACTIVE' ) ) {
                $badge = 'success';
            } elseif ( false !== strpos( $status, 'SUSPENSO' ) || false !== strpos( $status, 'CANCEL' ) || false !== strpos( $status, 'INATIVO' ) ) {
                $badge = 'danger';
            } else {
                $badge = 'warning text-dark';
            }
        }
        return array( 'badge' => $badge, 'label' => $label, 'checked' => $checked, 'note' => $note );
    }

    /* ------------------------------------------------------------------ */
    /* Seção Gestão → CRECI/Cofeci                                         */
    /* ------------------------------------------------------------------ */

    public static function render_dashboard_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $msg = '';
        if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' )
            && isset( $_POST['imovel_imobisec_settings_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['imovel_imobisec_settings_nonce'] ) ), self::OPT_NONCE ) ) {
            $in = array(
                'api_key'           => isset( $_POST['imovel_imobisec_key'] ) ? wp_unslash( $_POST['imovel_imobisec_key'] ) : '',
                'purpose'           => isset( $_POST['imovel_imobisec_purpose'] ) ? wp_unslash( $_POST['imovel_imobisec_purpose'] ) : '',
                'auto_approve'      => isset( $_POST['imovel_imobisec_auto'] ) ? 1 : 0,
                'strong_mode'       => isset( $_POST['imovel_imobisec_strong'] ) ? sanitize_key( wp_unslash( $_POST['imovel_imobisec_strong'] ) ) : 'hybrid',
                'reval_number'      => isset( $_POST['imovel_imobisec_reval_n'] ) ? absint( $_POST['imovel_imobisec_reval_n'] ) : 1,
                'reval_unit'        => isset( $_POST['imovel_imobisec_reval_u'] ) ? sanitize_key( wp_unslash( $_POST['imovel_imobisec_reval_u'] ) ) : 'years',
                'min_credits'       => isset( $_POST['imovel_imobisec_min'] ) ? absint( $_POST['imovel_imobisec_min'] ) : 0,
                'mock_mode'         => isset( $_POST['imovel_imobisec_mock'] ) ? 1 : 0,
                'regenerate_secret' => isset( $_POST['imovel_imobisec_regen'] ) ? 1 : 0,
            );
            // Campo máscara: vazio mantém a chave atual.
            if ( '' === trim( (string) $in['api_key'] ) || '********' === trim( (string) $in['api_key'] ) ) {
                unset( $in['api_key'] );
            }
            self::save_settings( $in );
            $msg = __( 'Configurações da ImobiSec salvas.', 'imovel-parceiro-core' );
        }

        $s          = self::settings();
        $key_masked = '' !== $s['api_key'];
        $webhookurl = rest_url( self::REST_ROUTE );
        $low        = ( '' !== $s['last_credits'] && (int) $s['last_credits'] <= (int) $s['min_credits'] );
        ?>
        <div class="dashboard-content-block rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h5 style="margin:0 0 4px;"><?php esc_html_e( 'CRECI/Cofeci (ImobiSec)', 'imovel-parceiro-core' ); ?></h5>
                    <p style="margin:0; color:#6b7280; font-size:14px;"><?php esc_html_e( 'Validação automática de CRECI no envio da verificação, com revalidação periódica.', 'imovel-parceiro-core' ); ?></p>
                </div>
                <?php if ( self::is_configured() ) : ?>
                    <span class="badge bg-success px-3 py-2"><?php esc_html_e( 'Chave configurada', 'imovel-parceiro-core' ); ?></span>
                <?php else : ?>
                    <span class="badge bg-warning text-dark px-3 py-2"><?php esc_html_e( 'Sem chave de API', 'imovel-parceiro-core' ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( '' !== $msg ) : ?>
                <div class="alert alert-success" style="border-radius:10px;"><?php echo esc_html( $msg ); ?></div>
            <?php endif; ?>
            <?php if ( $low ) : ?>
                <div class="alert alert-warning" style="border-radius:10px;"><?php echo esc_html( sprintf( __( 'Saldo baixo na ImobiSec: %s créditos restantes.', 'imovel-parceiro-core' ), $s['last_credits'] ) ); ?></div>
            <?php endif; ?>

            <form method="post" style="display:grid; gap:14px; max-width:640px;">
                <?php wp_nonce_field( self::OPT_NONCE, 'imovel_imobisec_settings_nonce' ); ?>
                <div>
                    <label for="imovel_imobisec_key" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Chave de API (X-API-Token-Key)', 'imovel-parceiro-core' ); ?></label>
                    <input type="password" id="imovel_imobisec_key" name="imovel_imobisec_key" value="" placeholder="<?php echo $key_masked ? '********' : ''; ?>" autocomplete="new-password" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    <p class="description" style="margin:6px 0 0; font-size:12px; color:#666;"><?php esc_html_e( 'Preencha apenas para trocar. A chave nunca aparece no código nem no Git.', 'imovel-parceiro-core' ); ?></p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="imovel_imobisec_purpose" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Finalidade (X-Purpose)', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" id="imovel_imobisec_purpose" name="imovel_imobisec_purpose" value="<?php echo esc_attr( $s['purpose'] ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                    <div class="col-md-6">
                        <label for="imovel_imobisec_min" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Alertar quando créditos ≤', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_imobisec_min" name="imovel_imobisec_min" value="<?php echo esc_attr( $s['min_credits'] ); ?>" min="0" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="imovel_imobisec_strong" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Verificação forte (2 créditos)', 'imovel-parceiro-core' ); ?></label>
                        <select id="imovel_imobisec_strong" name="imovel_imobisec_strong" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
                            <option value="hybrid" <?php selected( $s['strong_mode'], 'hybrid' ); ?>><?php esc_html_e( 'Híbrida: automática quando preciso + botão manual', 'imovel-parceiro-core' ); ?></option>
                            <option value="auto" <?php selected( $s['strong_mode'], 'auto' ); ?>><?php esc_html_e( 'Automática total', 'imovel-parceiro-core' ); ?></option>
                            <option value="manual" <?php selected( $s['strong_mode'], 'manual' ); ?>><?php esc_html_e( 'Só via botão do admin', 'imovel-parceiro-core' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <span style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Revalidar aprovações a cada', 'imovel-parceiro-core' ); ?></span>
                        <div style="display:flex; gap:8px;">
                            <input type="number" name="imovel_imobisec_reval_n" value="<?php echo esc_attr( $s['reval_number'] ); ?>" min="1" style="flex:1; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                            <select name="imovel_imobisec_reval_u" style="flex:1; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
                                <option value="months" <?php selected( $s['reval_unit'], 'months' ); ?>><?php esc_html_e( 'mês(es)', 'imovel-parceiro-core' ); ?></option>
                                <option value="years" <?php selected( $s['reval_unit'], 'years' ); ?>><?php esc_html_e( 'ano(s)', 'imovel-parceiro-core' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:18px; flex-wrap:wrap;">
                    <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                        <input type="checkbox" name="imovel_imobisec_auto" value="1" <?php checked( $s['auto_approve'], 1 ); ?> />
                        <?php esc_html_e( 'Auto-aprovar quando CRECI ativo e nome compatível', 'imovel-parceiro-core' ); ?>
                    </label>
                    <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                        <input type="checkbox" name="imovel_imobisec_mock" value="1" <?php checked( $s['mock_mode'], 1 ); ?> />
                        <?php esc_html_e( 'Modo homologação (mock gratuito)', 'imovel-parceiro-core' ); ?>
                    </label>
                    <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                        <input type="checkbox" name="imovel_imobisec_regen" value="1" />
                        <?php esc_html_e( 'Gerar novo segredo do webhook', 'imovel-parceiro-core' ); ?>
                    </label>
                </div>
                <div style="background:#f8fafc; border:1px solid #e6e6e6; border-radius:10px; padding:12px 14px; font-size:13px;">
                    <strong><?php esc_html_e( 'Webhook da revalidação:', 'imovel-parceiro-core' ); ?></strong>
                    <code style="word-break:break-all;"><?php echo esc_html( $webhookurl ); ?></code>
                    <?php if ( '' !== $s['last_credits'] ) : ?>
                        <div style="margin-top:6px;"><?php echo esc_html( sprintf( __( 'Último saldo informado: %s créditos.', 'imovel-parceiro-core' ), $s['last_credits'] ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="submit" class="btn btn-success"><?php esc_html_e( 'Salvar configurações', 'imovel-parceiro-core' ); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}

new Imovel_Parceiro_ImobiSec();
