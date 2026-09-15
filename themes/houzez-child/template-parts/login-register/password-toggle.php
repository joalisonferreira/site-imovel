<?php
/**
 * Reusable "show / hide password" toggle.
 *
 * Renders two inline SVG icons; assets/js/auth.js swaps them and flips the
 * related input between "password" and "text". The button is absolutely
 * positioned by auth.css, so it must live inside a wrapper carrying the
 * "ipc-password-field" class.
 *
 * @package Houzez Child
 */
?>
<button type="button" class="ipc-password-toggle"
    aria-label="<?php esc_attr_e( 'Mostrar senha', 'houzez' ); ?>"
    aria-pressed="false"
    data-ipc-show-label="<?php esc_attr_e( 'Mostrar senha', 'houzez' ); ?>"
    data-ipc-hide-label="<?php esc_attr_e( 'Ocultar senha', 'houzez' ); ?>">
    <svg class="ipc-password-toggle__icon ipc-password-toggle__icon--eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="ipc-password-toggle__icon ipc-password-toggle__icon--eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
        <path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/>
        <path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/>
        <line x1="2" y1="2" x2="22" y2="22"/>
    </svg>
</button>
