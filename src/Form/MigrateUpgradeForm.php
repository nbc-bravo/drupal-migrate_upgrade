<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Installer\Form\SiteSettingsForm;
use Drupal\Core\Form\FormStateInterface;

class MigrateUpgradeForm extends SiteSettingsForm {

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

    $form['source'] = array(
      '#type' => 'details',
      '#title' => $this->t('Source site'),
      '#open' => TRUE,
      '#weight' => 0,
    );
    $form['source']['site_address'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Source site address'),
      '#default_value' => 'http://',
      '#description' => $this->t('Enter the address of your current Drupal ' .
        'site (e.g. "http://www.example.com"). This address will be used to ' .
        'retrieve any public files from the site.'),
    );
    $form['files'] = array(
      '#type' => 'details',
      '#title' => $this->t('Files'),
      '#open' => TRUE,
      '#weight' => 2,
    );
    $form['files']['private_file_directory'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Private file directory'),
      '#description' => $this->t('If you have private files on your current ' .
        'Drupal site which you want imported, please copy the complete private ' .
        'file directory to a place accessible by your new Drupal 8 web server. ' .
        'Enter the address of the directory (e.g., "/home/legacy_files/private" ' .
        'or "http://private.example.com/legacy_files/private") here.'),
    );
    $form['database'] = array(
      '#type' => 'details',
      '#title' => $this->t('Source database'),
      '#description' => $this->t('Provide credentials for the database of the Drupal site you want to migrate.'),
      '#open' => TRUE,
      '#weight' => 1,
    );

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

    // OK, the connection is good, add it to the global connections and store
    // on the form state.
    Database::addConnectionInfo('migrate', 'default', $database);
    $form_state->setStorage(array('database' => $database));

    // The easiest way to know if the connection works is to just try connect.
    try  {
      $connection = Database::getConnection('default', 'migrate');
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(NULL, $this->t($e->getMessage()));
      return;
    }

    // Make sure that we can detect the drupal version.
    if (!$drupal_version = $this->getLegacyDrupalVersion($connection)) {
      $form_state->setErrorByName(NULL, $this->t('Source database does not contain a recognizable Drupal version.'));
      return;
    }

    // Now lets make sure have at least 1 migration for this version.
    if (!$migration_ids = $this->getMigrationIds($drupal_version)) {
      $form_state->setErrorByName(NULL, $this->t('Upgrade from version !version of Drupal is not supported.', array('!version' => $drupal_version)));
      return;
    }
    // Store the retrieved migration ids on the form state?
    $form_state->setValue('migration_ids', $migration_ids);

    foreach ($migration_ids as $migration_id) {
      // Set some per config migration settings. Should we be using
      // $config->setSettingsOverride(). Also, Drush is doing something very
      // similar to this right now. Maybe we can share some code.
      $config = \Drupal::configFactory()
        ->getEditable('migrate.migration.' . $migration_id)
        // @TODO What is this for?
        ->set('source.key', 'migrate' . $drupal_version)
        ->set('source.database', $database);

      if ($migration_id === 'd6_file') {
        // Configure the file migration so it can find the files.
        // @todo: Handle D7.
        if ($site_address_value = $form_state->getValue('site_address')) {
          $site_address = rtrim($site_address_value, '/') . '/';
          $config->set('destination.source_base_path', $site_address);
        }
      }
      $config->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = array(
      'title' => $this->t('Running migrations'),
      'progress_message' => '',
      'operations' => array(
        array(array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'run'), array($form_state->getValue('migration_ids'))),
      ),
      'finished' => array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'finished'),
    );
    batch_set($batch);
    $form_state->setRedirect('<front>');
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
    // @TODO, this shouldn't even be on a form.
    $version_string = $connection->query('SELECT schema_version FROM {system} WHERE name = :module', [':module' => 'system'])->fetchField();
    return substr($version_string, 0, 1);
  }

  /**
   * Gets migration configurations for the Drupal version being imported.
   *
   * @param $drupal_version
   *  Version number for filtering migrations.
   *
   * @return array
   *   An array of migration names.
   */
  protected function getMigrationIds($drupal_version) {
    $group_name = 'Drupal ' . $drupal_version;
    $migration_ids = \Drupal::entityQuery('migration')
      ->condition('migration_groups.*', $group_name)
      ->execute();

    // We need the migration ids in order because they're passed directly to the
    // batch runner which loads one migration at a time.
    $migrations = entity_load_multiple('migration', $migration_ids);
    $ordered_ids = [];
    foreach ($migrations as $migration) {
      $ordered_ids[] = $migration->id();
    }

    return $ordered_ids;
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
