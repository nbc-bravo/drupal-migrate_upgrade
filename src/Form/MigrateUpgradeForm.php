<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Installer\Form\SiteSettingsForm;
use Drupal\Core\Database\Install\TaskException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

class MigrateUpgradeForm extends SiteSettingsForm {
  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

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
      '#title' => t('Source site'),
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
      '#title' => t('Files'),
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

    $form['database']['driver'] = $form['driver'];
    unset($form['driver']);
    unset($form['settings']['mysql']['database']['#default_value']);
    $form['database']['settings'] = $form['settings'];
    unset($form['settings']);
    $form['database']['settings']['mysql']['host'] = $form['database']['settings']['mysql']['advanced_options']['host'];
    unset($form['database']['settings']['mysql']['advanced_options']['host']);
    $form['database']['settings']['mysql']['host']['#title'] = 'Database host';
    $form['database']['settings']['mysql']['host']['#weight'] = 0;

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
    $driver = $form_state->getValue('driver');
    if (isset($driver)) {
      // Ideally we would just call parent::validateForm(), but it will
      // add the source database as the 'default' connection and chaos will
      // ensue. We must replicate the logic here, setting the 'migrate'
      // connection instead.

      // Make sure the install API is available.
      include_once DRUPAL_ROOT . '/core/includes/install.core.inc';

      $database = $form_state->getValue($driver);
      $drivers = drupal_get_database_types();
      $reflection = new \ReflectionClass($drivers[$driver]);
      $install_namespace = $reflection->getNamespaceName();
      // Cut the trailing \Install from namespace.
      $database['namespace'] = substr($install_namespace, 0, strrpos($install_namespace, '\\'));
      $database['driver'] = $driver;
      $errors = array();

      // Check database type.
      $database_types = drupal_get_database_types();
      // Run driver specific validation
      $errors += $database_types[$driver]->validateDatabaseSettings($database);
      if (empty($errors)) {
        // Run tasks associated with the database type. Any errors are caught in the
        // calling function.
        Database::addConnectionInfo('migrate', 'default', $database);
        try {
          db_run_tasks($driver);
        }
        catch (TaskException $e) {
          // These are generic errors, so we do not have any specific key of the
          // database connection array to attach them to; therefore, we just put
          // them in the error array with standard numeric keys.
          $errors[$driver . '][0'] = $e->getMessage();
        }
        $form_state->setStorage(array('database' => $database));
        $errors = install_database_errors($database, $form_state->getValue('settings_file'));
      }
      foreach ($errors as $name => $message) {
        $form_state->setErrorByName($name, $message);
      }

      // Don't go any farther if we have errors with the database configuration.
      if (!empty($errors)) {
        return;
      }

      try {
        $connection = Database::getConnection('default', 'migrate');
      }
      catch (\Exception $e) {
        $form_state->setErrorByName(NULL, $e->getMessage());
        return;
      }

      $drupal_version = NULL;
      if (!$connection->schema()->tableExists('node')) {
        $form_state->setErrorByName(NULL, t('Source database does not ' .
          'contain a Drupal installation.'));
      }
      // Note we check D8 first, because it's reintroduced the menu_router
      // table we have used as the signature of D6.
      elseif ($connection->schema()->tableExists('key_value')) {
        $form_state->setErrorByName(NULL, t('Upgrade from this version ' .
                  'of Drupal is not supported.'));
      }
      elseif ($connection->schema()->tableExists('filter_format')) {
        $drupal_version = 7;
      }
      elseif ($connection->schema()->tableExists('menu_router')) {
        $drupal_version = 6;
      }
      else {
        $form_state->setErrorByName(NULL, t('Upgrade from this version of Drupal is not supported.'));
      }

      $migration_ids = $this->getDestinationIds($drupal_version);
      if (!empty($migration_ids)) {
        $form_state->setValue('migration_ids', $migration_ids);
      }
      else {
        $form_state->setErrorByName(NULL, t('Upgrade from this version of Drupal is not supported.'));
      }

      // Configure the file migration so it can find the files.
      // @todo: Handle D7.
      $site_address_value = $form_state->getValue('site_address');
      if (!empty($site_address_value)) {
        $site_address = rtrim($site_address_value, '/') . '/';
        $d6_file_config = \Drupal::configFactory()->getEditable('migrate.migration.d6_file');
        $d6_file_config->set('destination.source_base_path', $site_address);
        $d6_file_config->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $batch = array(
      'title' => t('Running migrations'),
      'progress_message' => '',
      'operations' => array(
        array(array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'run'),
              array($form_state->getValue('migration_ids'), $form_state->getStorage('database'))),
      ),
      'finished' => array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch',
                          'finished'),
    );
    batch_set($batch);
    $form_state->setRedirect('<front>');
  }

  /**
   * @return EntityStorageInterface
   */
  protected function storage() {
    if (!isset($this->storage)) {
      $this->storage = \Drupal::entityManager()->getStorage('migration');
    }
    return $this->storage;
  }

  /**
   * Returns the properties to be serialized
   *
   * @return array
   */
  public function __sleep() {
    // This apparently contains a PDOStatement somewhere.
    unset($this->storage);
    return parent::__sleep();
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
  protected function getDestinationIds($drupal_version) {
    $group_name = 'Drupal ' . $drupal_version;
    $query = \Drupal::entityQuery('migration')
      ->condition('migration_groups.*', $group_name);
    $names = $query->execute();
    // Order the migrations according to their dependencies.
    $migrations = \Drupal::entityManager()->getStorage('migration')->loadMultiple($names);
    $migration_ids = array();
    foreach ($migrations as $migration) {
      $migration_ids[] = $migration->id();
    }

    return $migration_ids;
  }
}
