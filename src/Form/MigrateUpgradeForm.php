<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Installer\Form\SiteSettingsForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_upgrade\MigrationCreationTrait;

/**
 * Form for performing direct site upgrades. Since we have the same need for
 * obtaining (source) database credentials on the install process, we build off
 * its form.
 */
class MigrateUpgradeForm extends SiteSettingsForm {

  use MigrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_upgrade_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Drupal Upgrade: Source site information');

    $form['database'] = [
      '#type' => 'details',
      '#title' => $this->t('Source database'),
      '#description' => $this->t('Provide credentials for the database of the Drupal site you want to upgrade.'),
      '#open' => TRUE,
    ];

    // Copy the values from the parent form into our structure.
    $form['database']['driver'] = $form['driver'];
    $form['database']['settings'] = $form['settings'];
    $form['database']['settings']['mysql']['host'] = $form['database']['settings']['mysql']['advanced_options']['host'];
    $form['database']['settings']['mysql']['host']['#title'] = 'Database host';
    $form['database']['settings']['mysql']['host']['#weight'] = 0;

    // Remove the values from the parent form.
    unset($form['driver']);
    unset($form['database']['settings']['mysql']['database']['#default_value']);
    unset($form['settings']);
    unset($form['database']['settings']['mysql']['advanced_options']['host']);

    $form['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Source files'),
      '#open' => TRUE,
    ];
    $form['source']['source_base_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Files directory'),
      '#description' => $this->t('To import files from your current Drupal site, enter a local file directory containing your site (e.g. /var/www/docroot), or your site address (e.g. http://example.com).'),
    ];

/*
    // @todo: Not yet implemented, depends on https://www.drupal.org/node/2547125.
    $form['files']['private_file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private file path'),
      '#description' => $this->t('To import private files from your current Drupal site, enter a local file directory containing your files (e.g. /var/private_files).'),
    ];
*/

    // Rename the submit button.
    $form['actions']['save']['#value'] = $this->t('Perform upgrade');

    // The parent form uses #limit_validation_errors to avoid validating the
    // unselected database drivers. This makes it difficult for us to handle
    // database errors in our validation, and does not appear to actually be
    // necessary with the current implementation, so we remove it.
    unset($form['actions']['save']['#limit_validation_errors']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the database driver from the form, use reflection to get the
    // namespace and then construct a valid database array the same as in
    // settings.php.
    $driver = $form_state->getValue('driver');
    $drivers = $this->getDatabaseTypes();
    $reflection = new \ReflectionClass($drivers[$driver]);
    $install_namespace = $reflection->getNamespaceName();

    $database = $form_state->getValue($driver);
    // Cut the trailing \Install from namespace.
    $database['namespace'] = substr($install_namespace, 0, strrpos($install_namespace, '\\'));
    $database['driver'] = $driver;

    // Validate the driver settings and just end here if we have any issues.
    if ($errors = $drivers[$driver]->validateDatabaseSettings($database)) {
      foreach ($errors as $name => $message) {
        $form_state->setErrorByName($name, $message);
      }
      return;
    }

    try {
      // Create all the relevant migrations and get their IDs so we can run them.
      $migration_ids = $this->createMigrations($database, $form_state->getValue('source_base_path'));

      // Store the retrieved migration ids on the form state.
      $form_state->setValue('migration_ids', $migration_ids);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(NULL, $this->t($e->getMessage()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Running upgrade'),
      'progress_message' => '',
      'operations' => [
        [['Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'run'], [$form_state->getValue('migration_ids')]],
      ],
      'finished' => ['Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'finished'],
    ];
    batch_set($batch);
    $form_state->setRedirect('<front>');
  }

  /**
   * Returns all supported database driver installer objects.
   *
   * @return \Drupal\Core\Database\Install\Tasks[]
   *   An array of available database driver installer objects.
   */
  protected function getDatabaseTypes() {
    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.core.inc';
    return drupal_get_database_types();
  }

}
