<?php
/**
 * Override do rodapé de e-mail WooCommerce — visual premium Imovel Parceiro.
 *
 * Mantém a hierarquia de fechamento exata do template padrão e aplica o
 * conteúdo de rodapé configurado no tema Houzez.
 */
defined( 'ABSPATH' ) || exit;

$email = isset( $email ) ? $email : null;

$ipc_footer = '';
if ( function_exists( 'fave_option' ) ) {
	$ipc_footer = (string) fave_option( 'email_footer_content' );
}
?>
																		</div>
																	</td>
																</tr>
															</table>
															<!-- End Content -->
														</td>
													</tr>
												</table>
												<!-- End Body -->
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									<!-- Footer -->
									<table border="0" cellpadding="10" cellspacing="0" width="100%" id="template_footer" role="presentation">
										<tr>
											<td valign="top">
												<table border="0" cellpadding="10" cellspacing="0" width="100%" role="presentation">
													<tr>
														<td colspan="2" valign="middle" id="credit">
															<?php
															if ( '' !== $ipc_footer ) {
																echo wp_kses_post( wpautop( wptexturize( $ipc_footer ) ) );
															} else {
																$email_footer_text = get_option( 'woocommerce_email_footer_text' );
																if ( apply_filters( 'woocommerce_is_email_preview', false ) ) {
																	$text_transient    = get_transient( 'woocommerce_email_footer_text' );
																	$email_footer_text = false !== $text_transient ? $text_transient : $email_footer_text;
																}
																echo wp_kses_post(
																	wpautop(
																		wptexturize(
																			apply_filters( 'woocommerce_email_footer_text', $email_footer_text, $email )
																		)
																	)
																);
															}
															?>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
									<!-- End Footer -->
								</td>
							</tr>
						</table>
					</div>
				</td>
				<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
			</tr>
		</table>
	</body>
</html>
