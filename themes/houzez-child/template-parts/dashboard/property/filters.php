<?php
// Child override of houzez/template-parts/dashboard/property/filters.php.
// Difference from parent: bulk action options carry stable value attributes.
// The parent JS compares the selected value against the hardcoded English
// string 'Delete'; without values, .val() returns the translated label
// (e.g. "Excluir") and bulk delete never runs on non-English sites.
global $properties_query, $delete_properties_nonce;
$total_posts = $properties_query->found_posts;
?>
<div class="houzez-table-filters d-flex align-items-center justify-content-between p-3">
  <form method="get" class="d-flex align-items-center justify-content-between w-100">
    
    <div class="dashboard-filter-right d-flex align-items-center gap-2">
      <select id="bulk-action-select" class="form-select">
        <option value=""><?php echo esc_html__('Bulk Actions', 'houzez'); ?></option>
        <option value="Delete"><?php echo esc_html__('Delete', 'houzez'); ?></option>
      </select>
      <input type="hidden" id="bulk-action-nonce" value="<?php echo esc_attr($delete_properties_nonce); ?>">
      <a class="btn btn-primary" href="#" id="bulk-action-apply"><?php echo esc_html__('Apply', 'houzez'); ?></a>
    </div>
    
    <?php if ( isset($_GET['is_search_result']) ): ?>
      <p class="small"><i class="houzez-icon icon-single-neutral-flag-2 me-2"></i> <?php echo esc_html($total_posts); ?> <?php echo esc_html__('Results Found', 'houzez'); ?></p>
    <?php endif; ?>

    <div class="dashboard-search-filter d-flex align-items-center gap-2">
      <div class="relative">
        <input type="text" name="keyword" value="<?php echo isset($_GET['keyword']) ? esc_attr($_GET['keyword']) : '';?>" class="form-control dashboard-search" placeholder="<?php echo esc_html__('Search properties...', 'houzez'); ?>" />
        <span><i class="houzez-icon icon-search"></i></span>
      </div>
      <div class="dropdown">
        <input type="hidden" name="post_status" value="<?php echo isset($_GET['post_status']) ? esc_attr($_GET['post_status']) : '';?>">
        <button type="submit" class="btn btn-secondary"><?php echo esc_html__('Search', 'houzez'); ?></button> 
      </div>
      <div class="dropdown">
        <a href="#" class="btn btn-primary btn-secondary-outlined" data-bs-toggle="dropdown" aria-expanded="false"><i class="houzez-icon icon-cog"></i> <?php echo esc_html__('Filters', 'houzez'); ?></a>
        <div class="dropdown-menu dropdown-menu2 filter-inner"> 
          <?php get_template_part('template-parts/dashboard/property/filter-form'); ?>
          <input type="hidden" name="is_search_result" value="1">
        </div> 
      </div>
    </div>
  </form>
</div> 
