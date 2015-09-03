<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\MigrateUpgradeRunBatch.
 */

namespace Drupal\migrate_upgrade;

use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\Url;

class MigrateUpgradeRunBatch {

  /**
   * Run a single migration batch.
   *
   * @param $initial_ids
   *   The full set of migration IDs to import.
   * @param $context
   *   The batch context.
   */
  public static function run($initial_ids, &$context) {
    if (!isset($context['sandbox']['migration_ids'])) {
      $context['sandbox']['max'] = count($initial_ids);
      $context['sandbox']['current'] = 1;
      // migration_ids will be the list of IDs remaining to run.
      $context['sandbox']['migration_ids'] = $initial_ids;
      $context['sandbox']['messages'] = [];
      $context['results']['failures'] = 0;
      $context['results']['successes'] = 0;
    }

    $migration_id = reset($context['sandbox']['migration_ids']);
    /** @var \Drupal\migrate\Entity\Migration $migration */
    $migration = Migration::load($migration_id);
    if ($migration) {
      $messages = new MigrateMessageCapture();
      $executable = new MigrateExecutable($migration, $messages);

      $migration_name = $migration->label() ? $migration->label() : $migration_id;

      try  {
        $migration_status = $executable->import();
      }
      catch (\Exception $e) {
        // PluginNotFoundException is when the D8 module is disabled, maybe that
        // should be a RequirementsException instead.
        static::logger()->error($e->getMessage());
        $migration_status = MigrationInterface::RESULT_FAILED;
      }

      switch ($migration_status) {
        case MigrationInterface::RESULT_COMPLETED:
          $context['sandbox']['messages'][] = t('Imported @migration (@current of @max)',
            ['@migration' => $migration_name, '@current' => $context['sandbox']['current'],
             '@max' => $context['sandbox']['max']]);
          $context['results']['successes']++;
          static::logger()->notice('Imported @migration', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_INCOMPLETE:
          $context['sandbox']['messages'][] = t('Importing @migration (@current of @max)',
            ['@migration' => $migration_name, '@current' => $context['sandbox']['current'],
             '@max' => $context['sandbox']['max']]);
          break;

        case MigrationInterface::RESULT_STOPPED:
          $context['sandbox']['messages'][] = t('Import stopped by request');
          break;

        case MigrationInterface::RESULT_FAILED:
          $context['sandbox']['messages'][] = t('Import of @migration failed', ['@migration' => $migration_name]);
          $context['results']['failures']++;
          static::logger()->error('Import of @migration failed', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_SKIPPED:
          $context['sandbox']['messages'][] = t('Import of @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          static::logger()->error('Import of @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_DISABLED:
          // Skip silently if disabled.
          break;
      }

      // Unless we're continuing on with this migration, take it off the list.
      if ($migration_status != MigrationInterface::RESULT_INCOMPLETE) {
        array_shift($context['sandbox']['migration_ids']);
        $context['sandbox']['current']++;
      }

      // Add and log any captured messages.
      foreach ($messages->getMessages() as $message) {
        $context['sandbox']['messages'][] = $message;
        static::logger()->error($message);
      }

      // Only display the last 10 messages, in reverse order.
      $message_count = count($context['sandbox']['messages']);
      $context['message'] = '';
      for ($index = max(0, $message_count - 10); $index < $message_count; $index++) {
        $context['message'] = $context['sandbox']['messages'][$index]. "<br />\n" . $context['message'];
      }
      if ($message_count > 10) {
        // Indicate there are earlier messages not displayed.
        $context['message'] .= '&hellip;';
      }
      // At the top of the list, display the next one (which will be the one
      // that is running while this message is visible).
      if (!empty($context['sandbox']['migration_ids'])) {
        $migration_id = reset($context['sandbox']['migration_ids']);
        $migration = Migration::load($migration_id);
        $migration_name = $migration->label() ? $migration->label() : $migration_id;
        $context['message'] = t('Currently importing @migration (@current of @max)',
          ['@migration' => $migration_name, '@current' => $context['sandbox']['current'],
           '@max' => $context['sandbox']['max']]) . "<br />\n" . $context['message'];
      }
    }
    else {
      array_shift($context['sandbox']['migration_ids']);
      $context['sandbox']['current']++;
    }

    $context['finished'] = 1 - count($context['sandbox']['migration_ids']) / $context['sandbox']['max'];
  }

  /**
   * A helper method to grab the logger using the migrate_upgrade channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger instance.
   */
  protected static function logger() {
    return \Drupal::logger('migrate_upgrade');
  }

  /**
   * Implementation of the Batch API finished method.
   */
  public static function finished($success, $results, $operations, $elapsed) {
    static::displayResults($results);
  }

  /**
   * Display counts of success/failures on the migration upgrade complete page.
   *
   * @param $results
   *   An array of result data built during the batch.
   */
  protected static function displayResults($results) {
    $successes = $results['successes'];
    $failures = $results['failures'];
    $translation = \Drupal::translation();

    // If we had any successes lot that for the user.
    if ($successes > 0) {
      drupal_set_message(t('Import completed @count successfully.', ['@count' => $translation->formatPlural($successes, '1 migration', '@count migrations')]));
    }

    // If we had failures, log them and show the migration failed.
    if ($failures > 0) {
      drupal_set_message(t('@count failed', ['@count' => $translation->formatPlural($failures, '1 migration', '@count migrations')]), 'error');
      drupal_set_message(t('Import process not completed'), 'error');
    }
    else {
      // Everything went off without a hitch. We may not have had successes but
      // we didn't have failures so this is fine.
      drupal_set_message(t('Congratulations, you upgraded Drupal!'));
    }

    if (\Drupal::moduleHandler()->moduleExists('dblog')) {
      $url = Url::fromRoute('migrate_upgrade.log');
      drupal_set_message(\Drupal::l(t('Review the detailed migration log'), $url), $failures ? 'error' : 'status');
    }
  }

}
