<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\MigrateUpgradeDrushRunner.
 */

namespace Drupal\migrate_upgrade;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\MigrateIdMapInterface;

class MigrateUpgradeDrushRunner {

  use MigrationCreationTrait;
  use StringTranslationTrait;

  /**
   * The list of migrations to run and their configuration.
   *
   * @var array
   */
  protected $migrationList;

  /**
   * From the provided source information, instantiate the appropriate migrations
   * in the active configuration.
   *
   * @throws \Exception
   */
  public function configure() {
    $db_url = drush_get_option('legacy-db-url');
    $db_spec = drush_convert_db_from_db_url($db_url);
    $db_prefix = drush_get_option('legacy-db-prefix');
    $db_spec['prefix'] = $db_prefix;

    $this->migrationList = $this->createMigrations($db_spec, drush_get_option('legacy-root'));
  }

  /**
   * Run the configured migrations.
   */
  public function import() {
    $log = new DrushLogMigrateMessage();
    foreach ($this->migrationList as $migration_id) {
      /** @var MigrationInterface $migration */
      $migration = Migration::load($migration_id);
      drush_print(dt('Upgrading @migration', ['@migration' => $migration_id]));
      $executable = new MigrateExecutable($migration, $log);
      // drush_op() provides --simulate support.
      drush_op([$executable, 'import']);
    }
  }

  /**
   * Rolls back the configured migrations.
   */
  public function rollback() {
    $log = new DrushLogMigrateMessage();
    $query = \Drupal::entityQuery('migration');
    $names = $query->execute();

    // Order the migrations according to their dependencies.
    /** @var MigrationInterface[] $migrations */
    $migrations = \Drupal::entityManager()
       ->getStorage('migration')
       ->loadMultiple($names);
    // Assume we want all those tagged 'Drupal %'.
    foreach ($migrations as $migration_id => $migration) {
      $keep = FALSE;
      $tags = $migration->get('migration_tags');
      foreach ($tags as $tag) {
        if (strpos($tag, 'Drupal ') === 0) {
          $keep = TRUE;
          break;
        }
      }
      if (!$keep) {
        unset($migrations[$migration_id]);
      }
    }
    // Roll back in reverse order.
    $this->migrationList = array_reverse($migrations);

    foreach ($this->migrationList as $migration_id => $migration) {
      drush_print(dt('Rolling back @migration', ['@migration' => $migration_id]));
      $executable = new MigrateExecutable($migration, $log);
      // drush_op() provides --simulate support.
      drush_op([$executable, 'rollback']);
      $migration->delete();
    }
  }

}
