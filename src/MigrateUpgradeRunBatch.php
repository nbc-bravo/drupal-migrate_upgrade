<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\MigrateUpgradeRunBatch.
 */

namespace Drupal\migrate_upgrade;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\Url;

class MigrateUpgradeRunBatch {

  /**
   * @param $initial_ids
   *   The initial migration IDs.
   * @param $context
   *   The batch context.
   */
  public static function run($initial_ids, &$context) {
    if (!isset($context['sandbox']['migration_ids'])) {
      $context['sandbox']['max'] = count($initial_ids);
      $context['sandbox']['migration_ids'] = $initial_ids;
    }
    $migration_id = reset($context['sandbox']['migration_ids']);
    $migration = entity_load('migration', $migration_id);
    if ($migration) {
      $messages = new MigrateMessageCapture();
      $executable = new MigrateExecutable($migration, $messages);
      $migration_name = $migration->label() ? $migration->label() : $migration_id;
      \Drupal::logger('migrate_upgrade')->notice('Importing @migration',
                         array('@migration' => $migration_name));
      $migration_status = $executable->import();
      switch ($migration_status) {
        case MigrationInterface::RESULT_COMPLETED:
          $context['message'] = t('Imported @migration',
            array('@migration' => $migration_name));
          $context['results'][$migration_name] = 'success';
          \Drupal::logger('migrate_upgrade')->notice('Imported @migration',
                   array('@migration' => $migration_name));
          break;
        case MigrationInterface::RESULT_INCOMPLETE:
          $context['message'] = t('Importing @migration',
            array('@migration' => $migration_name));
          break;
        case MigrationInterface::RESULT_STOPPED:
          $context['message'] = t('Import stopped by request');
          break;
        case MigrationInterface::RESULT_FAILED:
          $context['message'] = t('Import of @migration failed',
            array('@migration' => $migration_name));
          $context['results'][$migration_name] = 'failure';
          \Drupal::logger('migrate_upgrade')->error('Import of @migration failed',
                   array('@migration' => $migration_name));
          break;
        case MigrationInterface::RESULT_SKIPPED:
          $context['message'] = t('Import of @migration skipped due to unfulfilled dependencies',
            array('@migration' => $migration_name));
          \Drupal::logger('migrate_upgrade')->error('Import of @migration skipped due to unfulfilled dependencies',
                   array('@migration' => $migration_name));
          break;
        case MigrationInterface::RESULT_DISABLED:
          // Skip silently if disabled.
          break;
      }

      // Add any captured messages.
      foreach ($messages->getMessages() as $message) {
        $context['message'] .= "<br />\n" . $message;
      }

      // Unless we're continuing on with this migration, take it off the list.
      if ($executable->import() != MigrationInterface::RESULT_INCOMPLETE) {
        array_shift($context['sandbox']['migration_ids']);
      }
    }
    else {
      array_shift($context['sandbox']['migration_ids']);
    }
    $context['finished'] = 1 - count($context['sandbox']['migration_ids']) / $context['sandbox']['max'];
  }

  /**
   * @param $success
   * @param $results
   * @param $operations
   * @param $elapsed
   */
  public static function finished($success, $results, $operations, $elapsed) {
    self::displayResults($results);
  }

  /**
   * Display counts of success/failures.
   *
   * @param $results
   */
  protected static function displayResults($results) {
    $successes = $failures = 0;
    $status_type = 'status';
    foreach ($results as $result) {
      if ($result == 'success') {
        $successes++;
      }
      else {
        $failures++;
      }
    }
    if ($successes > 0 && $failures == 0) {
      drupal_set_message(t('Import complete.'));
      drupal_set_message(t('@count succeeded',
        array('@count' => \Drupal::translation()->formatPlural($successes,
          '1 migration', '@count migrations'))));
      drupal_set_message(t('Congratulations, you upgraded Drupal!'));
    }
    if ($failures > 0) {
      drupal_set_message(t('Import process not completed'), 'error');
      drupal_set_message(t('@count succeeded',
        array('@count' => \Drupal::translation()->formatPlural($successes,
          '1 migration', '@count migrations'))), 'error');
      drupal_set_message(t('@count failed',
        array('@count' => \Drupal::translation()->formatPlural($failures,
          '1 migration', '@count migrations'))), 'error');
      $status_type = 'error';
    }
    if (\Drupal::moduleHandler()->moduleExists('dblog')) {
      $url = new URL('migrate_upgrade.log');
      drupal_set_message(\Drupal::l(t('Review the detailed migration log'), $url), $status_type);
    }
  }
}
