<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registration/profile extra fields:
 * - "Tipo de pessoa" (CPF/CNPJ) with input mask.
 * - CRECI (stored in the existing Houzez license meta) mandatory for corretor/imobiliaria.
 * - Audit log entry when the Terms & Conditions are accepted.
 */
class Imovel_Parceiro_User_Fields {
    const PERSON_TYPE_META = 'imovel_parceiro_person_type';
    const TAX_NUMBER_META  = 'fave_author_tax_no';
    const LICENSE_META     = 'fave_author_license';
    const FULL_NAME_META   = 'imovel_parceiro_full_name';
    const AUDIT_EVENT      = 'user_terms_accepted';

    public function __construct() {
        add_action( 'houzez_register_form_fields', array( $this, 'render_register_fields' ) );
        add_action( 'houzez_before_register', array( $this, 'validate_registration' ) );
        add_action( 'houzez_after_register', array( $this, 'save_registration_fields' ) );
        add_action( 'wp_footer', array( $this, 'render_footer_assets' ) );

        // Native Houzez account verification is mandatory for brokers (houzez_agent)
        // and real estate agencies (houzez_agency) before creating or editing a listing.
        add_filter( 'redux/options/houzez_options/global_variable', array( $this, 'force_native_verification_options' ) );
        add_action( 'init', array( $this, 'force_native_verification_options_fallback' ), 999 );
        add_filter( 'houzez_before_submit_property', array( $this, 'guard_property_submission_verification' ) );
        add_filter( 'houzez_before_update_property', array( $this, 'guard_property_submission_verification' ) );
        add_action( 'wp_ajax_save_as_draft', array( $this, 'guard_save_as_draft_verification' ), 1 );
    }

    /**
     * Whether the current request belongs to a broker/agency that must be verified.
     */
    private static function current_user_requires_verification() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();

        return $user && self::is_agent_or_agency( (array) $user->roles );
    }

    /**
     * Force the native Houzez verification options on the Redux global for
     * broker/agency requests so the theme's own verification guard applies.
     */
    public function force_native_verification_options( $options ) {
        if ( ! is_array( $options ) || ! self::current_user_requires_verification() ) {
            return $options;
        }

        $options['enable_user_verification'] = 1;
        $options['verification_required_for_property'] = 1;

        return $options;
    }

    /**
     * Safety net for requests where the Redux global is populated after init.
     */
    public function force_native_verification_options_fallback() {
        global $houzez_options;

        if ( ! is_array( $houzez_options ) || ! self::current_user_requires_verification() ) {
            return;
        }

        $houzez_options['enable_user_verification'] = 1;
        $houzez_options['verification_required_for_property'] = 1;
    }

    private static function is_user_natively_verified( $user_id ) {
        return 'approved' === get_user_meta( absint( $user_id ), 'houzez_verification_status', true );
    }

    /**
     * Server-side guard: broker/agency must be verified to publish or edit.
     */
    public function guard_property_submission_verification( $property ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! self::is_agent_or_agency( self::user_roles( $user_id ) ) ) {
            return $property;
        }

        if ( current_user_can( 'manage_options' ) || ( function_exists( 'houzez_is_editor' ) && houzez_is_editor() ) ) {
            return $property;
        }

        if ( self::is_user_natively_verified( $user_id ) ) {
            return $property;
        }

        wp_die( esc_html__( 'Sua conta precisa ser verificada antes de cadastrar ou editar imóveis. Conclua a verificação de conta para continuar.', 'imovel-parceiro-core' ) );

        return $property;
    }

    /**
     * Server-side guard for the "save as draft" endpoint.
     */
    public function guard_save_as_draft_verification() {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! self::is_agent_or_agency( self::user_roles( $user_id ) ) ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) || ( function_exists( 'houzez_is_editor' ) && houzez_is_editor() ) ) {
            return;
        }

        if ( self::is_user_natively_verified( $user_id ) ) {
            return;
        }

        wp_send_json(
            array(
                'success' => false,
                'msg'     => __( 'Sua conta precisa ser verificada antes de salvar imóveis como rascunho.', 'imovel-parceiro-core' ),
            )
        );
    }

    private static function user_roles( $user_id ) {
        $user = get_userdata( $user_id );

        return $user ? (array) $user->roles : array();
    }

    private static function is_agent_or_agency( $roles ) {
        return (bool) array_intersect( array( 'houzez_agent', 'houzez_agency' ), (array) $roles );
    }

    /**
     * Extra fields injected in the Houzez registration form (modal and membership checkout).
     */
    public function render_register_fields() {
        ?>
        <input type="hidden" name="ipc_user_fields" value="1" />
        <?php if ( empty( $GLOBALS['ipc_register_full_name_rendered'] ) ) : ?>
        <div class="form-group">
            <div class="form-group-field">
                <input type="text" class="form-control ipc-full-name" name="ipc_full_name" placeholder="<?php esc_attr_e( 'Nome completo', 'imovel-parceiro-core' ); ?>" autocomplete="name" required />
            </div>
        </div>
        <input type="hidden" name="first_name" value="" />
        <input type="hidden" name="last_name" value="" />
        <?php endif; ?>
        <div class="form-group">
            <div class="form-group-field">
                <select name="person_type" class="form-control ipc-person-type" title="<?php esc_attr_e( 'Tipo de pessoa', 'imovel-parceiro-core' ); ?>">
                    <option value=""><?php esc_html_e( 'Tipo de pessoa', 'imovel-parceiro-core' ); ?></option>
                    <option value="cpf"><?php esc_html_e( 'CPF', 'imovel-parceiro-core' ); ?></option>
                    <option value="cnpj"><?php esc_html_e( 'CNPJ', 'imovel-parceiro-core' ); ?></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="form-group-field">
                <input type="text" class="form-control ipc-person-document" name="person_document" placeholder="<?php esc_attr_e( 'CPF/CNPJ', 'imovel-parceiro-core' ); ?>" inputmode="numeric" autocomplete="off" />
            </div>
        </div>
        <div class="form-group">
            <div class="form-group-field">
                <input type="text" class="form-control ipc-creci" name="creci" placeholder="<?php esc_attr_e( 'CRECI', 'imovel-parceiro-core' ); ?>" autocomplete="off" />
            </div>
        </div>
        <?php
    }

    /**
     * Server-side guard: CRECI is mandatory for corretor/imobiliaria.
     * Only enforced when our fields were actually rendered (ipc_user_fields marker).
     */
    public function validate_registration() {
        if ( ! isset( $_POST['ipc_user_fields'] ) ) {
            return;
        }

        $role = '';
        if ( isset( $_POST['role'] ) ) {
            $role = sanitize_text_field( wp_unslash( $_POST['role'] ) );
        } elseif ( isset( $_POST['user_role'] ) ) {
            $role = sanitize_text_field( wp_unslash( $_POST['user_role'] ) );
        }

        $full_name = isset( $_POST['ipc_full_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ipc_full_name'] ) ) ) : '';
        if ( '' === $full_name || strlen( $full_name ) < 3 ) {
            $this->registration_error( __( 'Informe seu nome completo.', 'imovel-parceiro-core' ) );
        }

        if ( isset( $_POST['register_pass'] ) ) {
            $password = (string) wp_unslash( $_POST['register_pass'] );
            if ( strlen( $password ) < 8 ) {
                $this->registration_error( __( 'A senha deve ter no minimo 8 caracteres.', 'imovel-parceiro-core' ) );
            }
            if ( ! preg_match( '/[A-Z]/', $password ) ) {
                $this->registration_error( __( 'A senha deve conter pelo menos uma letra maiuscula.', 'imovel-parceiro-core' ) );
            }
            if ( ! preg_match( '/[^A-Za-z0-9]/', $password ) ) {
                $this->registration_error( __( 'A senha deve conter pelo menos um simbolo.', 'imovel-parceiro-core' ) );
            }
        }

        $person_type = isset( $_POST['person_type'] ) ? sanitize_key( wp_unslash( $_POST['person_type'] ) ) : '';
        if ( 'houzez_agency' === $role ) {
            $person_type = 'cnpj';
        }

        $document = isset( $_POST['person_document'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['person_document'] ) ) ) : '';

        if ( ! in_array( $person_type, array( 'cpf', 'cnpj' ), true ) ) {
            $this->registration_error( __( 'Selecione o tipo de pessoa (CPF ou CNPJ).', 'imovel-parceiro-core' ) );
        }

        if ( '' === $document ) {
            $this->registration_error(
                'cnpj' === $person_type
                    ? __( 'Informe o CNPJ.', 'imovel-parceiro-core' )
                    : __( 'Informe o CPF.', 'imovel-parceiro-core' )
            );
        }

        if ( 'cnpj' === $person_type ) {
            if ( ! self::validate_cnpj( $document ) ) {
                $this->registration_error( __( 'O CNPJ informado é inválido.', 'imovel-parceiro-core' ) );
            }
        } elseif ( ! self::validate_cpf( $document ) ) {
            $this->registration_error( __( 'O CPF informado é inválido.', 'imovel-parceiro-core' ) );
        }

        if ( in_array( $role, array( 'houzez_agent', 'houzez_agency' ), true ) ) {
            $creci = isset( $_POST['creci'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['creci'] ) ) ) : '';
            if ( '' === $creci ) {
                $this->registration_error( __( 'O CRECI é obrigatório para corretores e imobiliárias.', 'imovel-parceiro-core' ) );
            }
            if ( self::license_taken( $creci ) ) {
                $this->registration_error( __( 'Este CRECI já está cadastrado por outro usuário.', 'imovel-parceiro-core' ) );
            }
        }
    }

    /**
     * Normalize a CRECI/license value for comparison.
     */
    public static function normalize_license( $value ) {
        $value = strtoupper( trim( (string) $value ) );

        return preg_replace( '/[^A-Z0-9]/', '', $value );
    }

    /**
     * Whether a CRECI/license is already used by another user.
     *
     * @param string $license          CRECI value to check.
     * @param int    $exclude_user_id  User that may keep its own value.
     * @return bool
     */
    public static function license_taken( $license, $exclude_user_id = 0 ) {
        $target = self::normalize_license( $license );
        if ( '' === $target ) {
            return false;
        }

        $users = get_users(
            array(
                'meta_key' => self::LICENSE_META,
                'fields'   => 'ID',
            )
        );

        foreach ( (array) $users as $uid ) {
            $uid = (int) $uid;
            if ( $uid === (int) $exclude_user_id ) {
                continue;
            }
            $stored = get_user_meta( $uid, self::LICENSE_META, true );
            if ( '' !== $stored && self::normalize_license( $stored ) === $target ) {
                return true;
            }
        }

        return false;
    }

    private function registration_error( $message ) {
        echo json_encode(
            array(
                'success' => false,
                'msg'     => $message,
            )
        );
        wp_die();
    }

    private static function only_digits( $value ) {
        return preg_replace( '/\D/', '', (string) $value );
    }

    private static function validate_cpf( $cpf ) {
        $cpf = self::only_digits( $cpf );

        if ( 11 !== strlen( $cpf ) || preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
            return false;
        }

        for ( $t = 9; $t < 11; $t++ ) {
            $sum = 0;
            for ( $i = 0; $i < $t; $i++ ) {
                $sum += (int) $cpf[ $i ] * ( ( $t + 1 ) - $i );
            }
            $digit = ( ( 10 * $sum ) % 11 ) % 10;
            if ( (int) $cpf[ $t ] !== $digit ) {
                return false;
            }
        }

        return true;
    }

    private static function validate_cnpj( $cnpj ) {
        $cnpj = self::only_digits( $cnpj );

        if ( 14 !== strlen( $cnpj ) || preg_match( '/^(\d)\1{13}$/', $cnpj ) ) {
            return false;
        }

        $weights = array(
            array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ),
            array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ),
        );

        foreach ( $weights as $index => $row ) {
            $sum = 0;
            foreach ( $row as $position => $weight ) {
                $sum += (int) $cnpj[ $position ] * $weight;
            }
            $remainder = $sum % 11;
            $digit     = $remainder < 2 ? 0 : 11 - $remainder;
            if ( (int) $cnpj[ 12 + $index ] !== $digit ) {
                return false;
            }
        }

        return true;
    }

    private static function format_document( $type, $digits ) {
        if ( 'cnpj' === $type ) {
            return preg_replace( '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits );
        }

        return preg_replace( '/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits );
    }

    /**
     * Persist the extra fields once the user has been created.
     *
     * @param int $user_id Newly created user id.
     */
    public function save_registration_fields( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! isset( $_POST['ipc_user_fields'] ) ) {
            return;
        }

        $roles     = self::user_roles( $user_id );
        $is_agency = in_array( 'houzez_agency', $roles, true );

        $full_name = isset( $_POST['ipc_full_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ipc_full_name'] ) ) ) : '';
        if ( '' !== $full_name ) {
            $full_name = preg_replace( '/\s+/', ' ', $full_name );
            update_user_meta( $user_id, self::FULL_NAME_META, $full_name );

            $parts = explode( ' ', $full_name );
            $first = array_shift( $parts );
            $last  = implode( ' ', $parts );

            update_user_meta( $user_id, 'first_name', $first );
            update_user_meta( $user_id, 'last_name', $last );

            wp_update_user(
                array(
                    'ID'           => $user_id,
                    'display_name' => $full_name,
                )
            );
        }

        $person_type = isset( $_POST['person_type'] ) ? sanitize_key( wp_unslash( $_POST['person_type'] ) ) : '';
        if ( ! in_array( $person_type, array( 'cpf', 'cnpj' ), true ) ) {
            $person_type = '';
        }
        if ( $is_agency ) {
            $person_type = 'cnpj';
        }

        $document       = isset( $_POST['person_document'] ) ? self::only_digits( sanitize_text_field( wp_unslash( $_POST['person_document'] ) ) ) : '';
        $document_store = '';
        $creci          = isset( $_POST['creci'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['creci'] ) ) ) : '';

        if ( '' !== $person_type ) {
            update_user_meta( $user_id, self::PERSON_TYPE_META, $person_type );
        }

        if ( '' !== $document ) {
            $is_valid = 'cnpj' === $person_type ? self::validate_cnpj( $document ) : self::validate_cpf( $document );
            if ( $is_valid ) {
                $document_store = self::format_document( $person_type, $document );
                update_user_meta( $user_id, self::TAX_NUMBER_META, $document_store );
            }
        }

        if ( '' !== $creci ) {
            update_user_meta( $user_id, self::LICENSE_META, $creci );
        }

        if ( ! empty( $_POST['term_condition'] ) ) {
            $this->insert_terms_audit_log( $user_id, $person_type, $document_store );
        }
    }

    private function insert_terms_audit_log( $user_id, $person_type, $document ) {
        global $wpdb;

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $table           = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(80) NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            other_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            property_id bigint(20) unsigned NOT NULL DEFAULT 0,
            partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY property_id (property_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql );

        $type_label = 'cnpj' === $person_type ? 'CNPJ' : ( 'cpf' === $person_type ? 'CPF' : __( 'Documento', 'imovel-parceiro-core' ) );
        $message    = sprintf( '%s: %s', $type_label, '' !== $document ? $document : '-' );

        $payload = array(
            'ip'     => $this->get_request_ip(),
            'origin' => wp_doing_ajax() ? 'ajax' : 'frontend',
            'meta'   => array(
                'message'     => $message,
                'person_type' => $person_type,
                'document'    => $document,
            ),
        );

        $wpdb->insert(
            $table,
            array(
                'event_type'     => self::AUDIT_EVENT,
                'actor_user_id'  => $user_id,
                'other_user_id'  => 0,
                'property_id'    => 0,
                'partnership_id' => 0,
                'meta'           => maybe_serialize( $payload ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    private function get_request_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            if ( 'HTTP_X_FORWARDED_FOR' === $key && false !== strpos( $raw, ',' ) ) {
                $parts = array_map( 'trim', explode( ',', $raw ) );
                $raw   = isset( $parts[0] ) ? $parts[0] : $raw;
            }

            if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
                return $raw;
            }
        }

        return '';
    }

    /**
     * Mask script for the registration fields and "required" hint on the profile CRECI field.
     */
    public function render_footer_assets() {
        if ( is_admin() ) {
            return;
        }

        $current_roles    = is_user_logged_in() ? (array) wp_get_current_user()->roles : array();
        $creci_required   = self::is_agent_or_agency( $current_roles ) ? 'true' : 'false';
        ?>
        <script>
        (function () {
            var creciRequired = <?php echo $creci_required; ?>;

            function digits(value) {
                return (value || '').replace(/\D+/g, '');
            }

            function maskCPF(value) {
                value = digits(value).slice(0, 11);
                return value
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }

            function maskCNPJ(value) {
                value = digits(value).slice(0, 14);
                return value
                    .replace(/^(\d{2})(\d)/, '$1.$2')
                    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1/$2')
                    .replace(/(\d{4})(\d)/, '$1-$2');
            }

            function maskFor(type, value) {
                if (type === 'cnpj') {
                    return maskCNPJ(value);
                }
                if (type === 'cpf') {
                    return maskCPF(value);
                }
                return value;
            }

            function formFields(form) {
                return {
                    type: form.querySelector('select[name="person_type"]'),
                    doc: form.querySelector('input[name="person_document"]'),
                    creci: form.querySelector('input[name="creci"]'),
                    role: form.querySelector('select[name="role"]') || form.querySelector('select[name="user_role"]')
                };
            }

            function syncForm(form) {
                var fields = formFields(form);
                if (!fields.type || !fields.doc) {
                    return;
                }

                if (fields.role && fields.role.value === 'houzez_agency') {
                    fields.type.value = 'cnpj';
                    fields.type.disabled = true;
                } else if (fields.type.disabled) {
                    fields.type.disabled = false;
                }

                var type = fields.type.value;
                fields.doc.setAttribute('placeholder', type === 'cnpj' ? 'CNPJ' : (type === 'cpf' ? 'CPF' : 'CPF/CNPJ'));
                fields.doc.value = maskFor(type, fields.doc.value);

                if (fields.creci && fields.role) {
                    var mandatory = fields.role.value === 'houzez_agent' || fields.role.value === 'houzez_agency';
                    if (mandatory) {
                        fields.creci.setAttribute('required', 'required');
                    } else {
                        fields.creci.removeAttribute('required');
                    }
                }
            }

            function eachRegisterForm(callback) {
                var forms = document.querySelectorAll('form');
                Array.prototype.forEach.call(forms, function (form) {
                    if (form.querySelector('select[name="person_type"]')) {
                        callback(form);
                    }
                });
            }

            function syncFullName(form) {
                var full = form.querySelector('input[name="ipc_full_name"]');
                if (!full) {
                    return;
                }
                var first = form.querySelector('input[name="first_name"]');
                var last = form.querySelector('input[name="last_name"]');
                if (!first && !last) {
                    return;
                }
                var value = (full.value || '').trim().replace(/\s+/g, ' ');
                var parts = value ? value.split(' ') : [];
                var firstName = parts.shift() || '';
                var lastName = parts.join(' ');
                if (first) {
                    first.value = firstName;
                }
                if (last) {
                    last.value = lastName;
                }
            }

            document.addEventListener('change', function (event) {
                var target = event.target;
                if (!target || !target.closest) {
                    return;
                }
                if (target.matches('select[name="person_type"]') || target.matches('select[name="role"]') || target.matches('select[name="user_role"]')) {
                    var form = target.closest('form');
                    if (form) {
                        syncForm(form);
                    }
                }
            });

            document.addEventListener('input', function (event) {
                var target = event.target;
                if (target && target.matches && target.matches('input[name="person_document"]')) {
                    var form = target.closest('form');
                    if (form) {
                        var fields = formFields(form);
                        var type = fields.type ? fields.type.value : '';
                        var masked = maskFor(type, target.value);
                        if (masked !== target.value) {
                            target.value = masked;
                        }
                    }
                }
                if (target && target.matches && target.matches('input[name="ipc_full_name"]')) {
                    var nameForm = target.closest('form');
                    if (nameForm) {
                        syncFullName(nameForm);
                    }
                }
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (form && form.querySelector && form.querySelector('input[name="ipc_full_name"]')) {
                    syncFullName(form);
                }
            }, true);

            function init() {
                eachRegisterForm(function (form) {
                    syncForm(form);
                    syncFullName(form);
                });

                Array.prototype.forEach.call(document.querySelectorAll('input[name="term_condition"]'), function (checkbox) {
                    checkbox.setAttribute('required', 'required');
                });

                if (creciRequired) {
                    var license = document.querySelector('input[name="license"]');
                    if (license) {
                        license.setAttribute('required', 'required');
                    }
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }
}

new Imovel_Parceiro_User_Fields();
