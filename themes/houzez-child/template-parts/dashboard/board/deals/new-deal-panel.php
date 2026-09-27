<?php global $deal_settings; 
$agency_id = get_user_meta(get_current_user_id(), 'fave_author_agency_id', true);
?>
<div class="offcanvas offcanvas-end offcanvas-lead" data-bs-scroll="true" data-bs-backdrop="true" tabindex="-1" id="offcanvasDeal" aria-labelledby="offcanvasDealLabel">
	<?php get_template_part('template-parts/dashboard/loader'); ?>
	<div class="offcanvas-head">
		<h5><?php esc_html_e('Add New Deal', 'houzez'); ?></h5>  
		<a href="#" class="close-btn" data-bs-dismiss="offcanvas"><i class="houzez-icon icon-close"></i></a> 
	</div>	
	<div class="offcanvas-body">
		<div class="filter-inner">
			<div class="alert alert-info" role="alert" style="font-size:13px;"><?php esc_html_e('Ofertas: cadastro de leads exclusivos do corretor. Estes leads são visíveis apenas para você e não são compartilhados em parcerias.', 'houzez'); ?></div>
			<form id="deal-form">
				<div class="offcanvas-box">
					<div class="mb-3">
						<label><?php esc_html_e('Group', 'houzez'); ?></label>
						<select name="deal_group" class="selectpicker form-control bs-select-hidden" title="<?php esc_html_e('Select', 'houzez'); ?>" data-live-search="false">
							<option value="active"><?php esc_html_e('Active Deals', 'houzez'); ?></option>
							<option value="won"><?php esc_html_e('Won Deals', 'houzez'); ?></option>
							<option value="lost"><?php esc_html_e('Lost Deals', 'houzez'); ?></option>
						</select><!-- selectpicker -->
					</div><!-- mb-3 -->
					<div class="mb-3">
						<label><?php esc_html_e('Title', 'houzez'); ?></label>
						<input class="form-control" name="deal_title" placeholder="<?php esc_html_e('Enter the deal title', 'houzez'); ?>" type="text">
					</div>
					<div class="mb-3">
						<label><?php esc_html_e('Contact Name', 'houzez'); ?></label>
						<select name="deal_contact" class="selectpicker form-control bs-select-hidden" title="<?php esc_html_e('Select', 'houzez'); ?>" data-live-search="true">
							<option value=""><?php esc_html_e('Select', 'houzez'); ?></option>
							<?php 
							$all_leads = Houzez_leads::get_all_leads();
							foreach ($all_leads as $lead) {
								echo '<option value="'.intval($lead->lead_id).'">'.$lead->display_name.'</option>';
							}
							?>

						</select><!-- selectpicker -->
					</div><!-- mb-3 -->

					<?php if( houzez_is_admin() ) { ?>
					<div class="mb-3">
						<label><?php esc_html_e('Agent', 'houzez'); ?></label>
						<select name="deal_agent" class="selectpicker form-control bs-select-hidden" title="<?php esc_html_e('Select', 'houzez'); ?>" data-live-search="true">
							<option value=""><?php esc_html_e('Select', 'houzez'); ?></option>
							<?php 
							$args = array(
								'post_type' => 'houzez_agent',
								'posts_per_page' => -1,
								'post_status' => 'publish'
							);

							$agent_qry = new WP_Query($args);

							if($agent_qry->have_posts()): 
								while ($agent_qry->have_posts()): $agent_qry->the_post();
									
									if ( houzez_is_agency() ) {
										if( $agency_id == get_post_meta(get_the_ID(), 'fave_agent_agencies', true) ) {
											echo '<option value="'.get_the_ID().'">'.get_the_title().'</option>';
										}

									} else {
										echo '<option value="'.get_the_ID().'">'.get_the_title().'</option>';
									}
									

								endwhile;
							endif; 
							wp_reset_postdata();
							?>
						</select><!-- selectpicker -->
					</div><!-- mb-3 -->
					<?php } ?>
					
					<div class="mb-3">
						<label><?php esc_html_e('Deal Value', 'houzez'); ?></label>
						<input class="form-control" name="deal_value" placeholder="<?php esc_html_e('Enter the deal value', 'houzez'); ?>" type="text">
					</div>
					<div class="mb-3">
						<label><?php esc_html_e('Visibilidade', 'houzez'); ?></label>
						<input class="form-control" type="text" value="<?php esc_attr_e('Exclusivo do corretor', 'houzez'); ?>" disabled readonly>
						<small class="text-muted"><?php esc_html_e('Este lead é exclusivo do corretor e não aparece para outros usuários.', 'houzez'); ?></small>
					</div>
					
					<?php get_template_part('template-parts/overlay-loader'); ?>
					
					<input type="hidden" name="action" value="houzez_crm_add_deal">
					<br/>
					<div id="deal-msgs"></div>
				</div>
			</form>
		</div>
   </div>
   <div class="offcanvas-footer">
		<button id="add_deal" type="button" class="btn btn-primary w-100">
			<?php esc_html_e('Save', 'houzez'); ?>		
		</button>
  </div>
</div><!-- dashboard-slide-panel-wrap -->