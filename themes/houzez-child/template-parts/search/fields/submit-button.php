<button type="submit" class="btn btn-search btn-secondary w-100 <?php if( houzez_is_half_map() ) { echo 'half-map-search-js-btn'; }?>"><?php 
$label = houzez_option('srh_btn_search', 'Pesquisar');
if ( $label === 'Pesquisa' || $label === 'Search' ) { $label = 'Pesquisar'; }
echo esc_html( $label ); 
?></button>
