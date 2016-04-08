<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\MigrateUpgradeDrushRunner.
 */

namespace Drupal\migrate_upgrade;

use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate_drupal\MigrationCreationTrait;

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
   * MigrateMessage instance to display messages during the migration process.
   *
   * @var \Drupal\migrate_upgrade\DrushLogMigrateMessage
   */
  protected static $messages;

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

    $connection = $this->getConnection($db_spec);
    $version = $this->getLegacyDrupalVersion($connection);
    $this->createDatabaseStateSettings($db_spec, $version);
    $migrations = $this->getMigrations('migrate_drupal_' . $version, $version);
    $this->migrationList = [];
    foreach ($migrations as $migration) {
      $destination = $migration->get('destination');
      if ($destination['plugin'] === 'entity:file') {
        // Make sure we have a single trailing slash.
        $source_base_path = rtrim(drush_get_option('legacy-root'), '/') . '/';
        $destination['source_base_path'] = $source_base_path;
        $migration->set('destination', $destination);
      }
      $this->migrationList[$migration->id()] = $migration;
    }
  }

  /**
   * Run the configured migrations.
   */
  public function import() {
    static::$messages = new DrushLogMigrateMessage();
    if (drush_get_option('debug')) {
      \Drupal::service('event_dispatcher')->addListener(MigrateEvents::IDMAP_MESSAGE,
        [get_class(), 'onIdMapMessage']);
    }
    foreach ($this->migrationList as $migration_id => $migration) {
      drush_print(dt('Upgrading @migration', ['@migration' => $migration_id]));
      $executable = new MigrateExecutable($migration, static::$messages);
      // drush_op() provides --simulate support.
      drush_op([$executable, 'import']);
    }
  }

  /**
   * Rolls back the configured migrations.
   */
  public function rollback() {
    static::$messages = new DrushLogMigrateMessage();
    $database_state_key = \Drupal::state()->get('migrate.fallback_state_key');
    $database_state = \Drupal::state()->get($database_state_key);
    $db_spec = $database_state['database'];
    $connection = $this->getConnection($db_spec);
    $version = $this->getLegacyDrupalVersion($connection);
    $migrations = $this->getMigrations('migrate_drupal_' . $version, $version);

    // Roll back in reverse order.
    $this->migrationList = array_reverse($migrations);

    foreach ($migrations as $migration) {
      drush_print(dt('Rolling back @migration', ['@migration' => $migration->id()]));
      $executable = new MigrateExecutable($migration, static::$messages);
      // drush_op() provides --simulate support.
      drush_op([$executable, 'rollback']);
    }
  }

  /**
   * Display any messages being logged to the ID map.
   *
   * @param \Drupal\migrate\Event\MigrateIdMapMessageEvent $event
   *   The message event.
   */
  public static function onIdMapMessage(MigrateIdMapMessageEvent $event) {
    if ($event->getLevel() == MigrationInterface::MESSAGE_NOTICE ||
        $event->getLevel() == MigrationInterface::MESSAGE_INFORMATIONAL) {
      $type = 'status';
    }
    else {
      $type = 'error';
    }
    $source_id_string = implode(',', $event->getSourceIdValues());
    $message = t('Source ID @source_id: @message',
      ['@source_id' => $source_id_string, '@message' => $event->getMessage()]);
    static::$messages->display($message, $type);
  }

}
