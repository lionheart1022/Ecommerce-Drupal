<?php

/**
 * @file
 * Diamond Commerce - Odoo Migrate post update file.
 */

/**
 * Force re-import images deleted by Drupal garbage collector.
 */
function dcom_odoo_migrate_post_update_fix_temporary_files() {
  $database = \Drupal::database();
  $missing_files = $database->query('SELECT `m`.`sourceid1`
FROM {migrate_map_odoo_product_images}  `m`
LEFT JOIN {file_managed} `f` ON `m`.`destid1` = `f`.`fid`
WHERE `f`.`fid` IS NULL')->fetchCol();
  $database
    ->delete('migrate_map_odoo_product_images')
    ->condition('sourceid1', $missing_files, 'IN')
    ->execute();
}
