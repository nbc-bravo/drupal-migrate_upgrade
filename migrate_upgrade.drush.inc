<?php

/**
 * @file
 * Command-line tools to aid performing and developing upgrade migrations.
 */

use Drupal\migrate_upgrade\MigrateUpgradeDrushRunner;

/**
 * Implements hook_drush_command().
 */
function migrate_upgrade_drush_command() {
  $items['migrate-upgrade'] = array(
    'description' => 'Perform one or more migration processes.',
    'options' => array(
      'legacy-db-url' => 'A Drupal 6 style database URL. Required.',
      'legacy-root' => 'Site address or root of legacy Drupal installation',
      'no-import' => 'Set up the appropriate migrations but do not run the import',
    ),
    'examples' => array(
      'migrate-upgrade --legacy-db-url=\'mysql://root:pass@127.0.0.1/d6\'' => 'Import a Drupal 6 database',
    ),
    'drupal dependencies' => array('migrate_upgrade'),
  );

  return $items;
}

/**
 * @param string $migration_names
 */
function drush_migrate_upgrade() {
  $no_import = drush_get_option('no-import');

  $runner = new MigrateUpgradeDrushRunner();

  $runner->configure();
  if (!$no_import) {
    $runner->import();
  }
}
