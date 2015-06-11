<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\MigrateUpgradeTrait.
 */

namespace Drupal\migrate_upgrade;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

/**
 * Trait providing functionality to instantiate the appropriate migrations for
 * a given source Drupal database. Note the class using the trait must
 * implement TranslationInterface (i.e., define t()).
 */
trait MigrateUpgradeTrait {

  /**
   * Set up the relevant migrations for the provided database connection.
   *
   * @param \Drupal\Core\Database\Database $database
   *   Database array representing the source Drupal database.
   * @param string $site_address
   *   Address of the source Drupal site (e.g., http://example.com/).
   *
   * @return array
   */
  protected function configureMigrations(array $database, $site_address) {
    // Set up the connection.
    Database::addConnectionInfo('upgrade', 'default', $database);
    $connection = Database::getConnection('default', 'upgrade');

    if (!$drupal_version = $this->getLegacyDrupalVersion($connection)) {
      throw new \Exception($this->t('Source database does not contain a recognizable Drupal version.'));
    }

    // Now lets make sure have at least 1 migration for this version.
    if (!$migration_ids = $this->getMigrationIds($drupal_version)) {
      throw new \Exception($this->t('Upgrade from version !version of Drupal is not supported.', array('!version' => $drupal_version)));
    }

    foreach ($migration_ids as $migration_id) {
      // Set some per config migration settings. Should we be using
      // $config->setSettingsOverride()?
      $config = \Drupal::configFactory()
        ->getEditable('migrate.migration.' . $migration_id)
        ->set('source.key', 'upgrade' . $drupal_version)
        ->set('source.database', $database);

      // Configure file migrations so they can find the files.
      // @todo: Handle D7.
      if ($migration_id === 'd6_file' || $migration_id === 'd6_user_picture_file') {
        if ($site_address) {
          // Make sure we have a single trailing slash.
          $site_address = rtrim($site_address, '/') . '/';
          $config->set('destination.source_base_path', $site_address);
        }
      }
      $config->save();
    }

    return $migration_ids;
  }

  /**
   * Determine what version of Drupal the source database contains, based on
   * what tables are present.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *
   * @return int|null
   */
  protected function getLegacyDrupalVersion(Connection $connection) {
    $version_string = FALSE;

    // @todo: Don't assume because a table of that name exists, that it has
    // the columns we're querying. Catch exceptions and report that the source
    // database is not Drupal.

    // Detect Drupal 5/6/7.
    if ($connection->schema()->tableExists('system')) {
      $version_string = $connection->query('SELECT schema_version FROM {system} WHERE name = :module', [':module' => 'system'])->fetchField();
      if ($version_string && $version_string[0] == '1') {
        // @todo: This misidentifies 4.x as 5.
        $version_string = '5';
      }
    }
    // Detect Drupal 8.
    elseif ($connection->schema()->tableExists('key_value')) {
      $result = $connection->query("SELECT value FROM {key_value} WHERE collection = :system_schema  and name = :module", [':system_schema' => 'system.schema', ':module' => 'system'])->fetchField();
      $version_string = unserialize($result);
    }

    // @TODO I wonder if a hook here would help contrib support other version?

    return $version_string ? substr($version_string, 0, 1) : FALSE;
  }

  /**
   * Gets a list of candidate migrations for the Drupal version being imported.
   *
   * @param $drupal_version
   *  Version number for filtering migrations.
   *
   * @return array
   *   An array of migration names.
   */
  protected function getMigrationIds($drupal_version) {
    $group_name = 'Drupal ' . $drupal_version;
    // @todo: Should be replaced by a value on the source.
    $migration_ids = \Drupal::entityQuery('migration')
      ->condition('migration_tags.*', $group_name)
      ->execute();

    // We need the migration ids in order because they're passed directly to the
    // batch runner which loads one migration at a time.
    // @todo: To be replaced by templates.
    $migrations = entity_load_multiple('migration', $migration_ids);
    $ordered_ids = [];
    foreach ($migrations as $migration) {
      $ordered_ids[] = $migration->id();
    }

    return $ordered_ids;
  }

}
