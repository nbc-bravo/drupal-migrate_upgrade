<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Installer\Form\SiteSettingsForm;
use Drupal\Core\Database\Install\TaskException;

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
  public function buildForm(array $form, array &$form_state) {
    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Drupal Upgrade: Source site information');

    $form['files'] = array(
      '#type' => 'details',
      '#title' => t('Files'),
      '#open' => TRUE,
      '#weight' => 2,
    );

    $form['files']['site_address'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Source site address'),
      '#description' => $this->t('Enter the address of your current Drupal ' .
        'site (e.g. "http://www.example.com"). This address will be used to ' .
        'retrieve any public files from the site.'),
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
      '#title' => t('Database'),
      '#open' => TRUE,
      '#weight' => 1,
    );

    $form['database']['driver'] = $form['driver'];
    unset($form['driver']);
    $form['database']['settings'] = $form['settings'];
    unset($form['settings']);

    // Rename the submit button.
    $form['actions']['save']['#value'] = $this->t('Perform upgrade');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    if (isset($form_state['values']['driver'])) {
      // Ideally we would just call parent::validateForm(), but it will
      // add the source database as the 'default' connection and chaos will
      // ensue. We must replicate the logic here, setting the 'migrate'
      // connection instead.

      // Make sure the install API is available.
      include_once DRUPAL_ROOT . '/core/includes/install.core.inc';

      $driver = $form_state['values']['driver'];
      $database = $form_state['values'][$driver];
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
        $form_state['storage']['database'] = $database;
        $errors = install_database_errors($database, $form_state['values']['settings_file']);
      }
      foreach ($errors as $name => $message) {
        $this->setFormError($name, $form_state, $message);
      }

      // Don't go any farther if we have errors with the database configuration.
      if (!empty($errors)) {
        return;
      }

      $connection = Database::getConnection('default', 'migrate');
      if (!$connection->schema()->tableExists('node')) {
        $this->setFormError(NULL, $form_state, t('Source database does not ' .
          'contain a Drupal installation.'));
      }
      // Note we check D8 first, because it's reintroduced the menu_router
      // table we have used as the signature of D6.
      elseif ($connection->schema()->tableExists('key_value')) {
        $this->setFormError(NULL, $form_state, t('Upgrade from this version ' .
                  'of Drupal is not supported.'));
      }
      elseif ($connection->schema()->tableExists('filter_format')) {
        $form_state['drupal_version'] = 7;
      }
      elseif ($connection->schema()->tableExists('menu_router')) {
        $form_state['drupal_version'] = 6;
      }
      else {
        $this->setFormError(NULL, $form_state, t('Upgrade from this version ' .
          'of Drupal is not supported.'));
      }

      // Configure the file migration so it can find the files.
      // @todo: Handle D7.
      if (!empty($form_state['values']['site_address'])) {
        $site_address = rtrim($form_state['values']['site_address'], '/') . '/';
        $d6_file_config = \Drupal::config('migrate.migration.d6_file');
        $d6_file_config->set('destination.source_base_path', $site_address);
        $d6_file_config->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $migration_ids = $this->getDestinationIds();

    $batch = array(
      'title' => t('Running migrations'),
      'progress_message' => '',
      'operations' => array(
        array(array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'run'),
              array($migration_ids, $form_state['storage']['database'])),
      ),
      'finished' => array('Drupal\migrate_upgrade\MigrateUpgradeRunBatch',
                          'finished'),
    );
    batch_set($batch);
    $form_state['redirect'] = '/';
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
   * Gets migration configurations.
   *
   * @return array
   *   An array of migration names.
   */
  function getDestinationIds() {
    $manifest = drupal_get_path('module', 'migrate_upgrade') . '/migrate.';
    $config_manifest = $manifest . 'config.yml';
    $content_manifest = $manifest . 'content.yml';
    $config_list = Yaml::parse($config_manifest);
    $content_list = Yaml::parse($content_manifest);
    $names = array_merge($config_list['configuration'], $content_list['content']);
    return $names;
  }
}
