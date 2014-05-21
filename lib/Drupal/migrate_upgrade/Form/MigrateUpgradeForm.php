<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\Yaml\Yaml;

class MigrateUpgradeForm extends FormBase {
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
   * Step 1 of the form - gather database credentials.
   */
  public function credentialStep() {
    $form['#title'] = $this->t('Upgrade step 1: Source site information');

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

    $form['database']['database_description'] = array(
      '#markup' => $this->t('Enter the database credentials for the legacy Drupal ' .
        'site you are upgrading into this Drupal 8 instance:'),
    );
    // The following is stolen from install.core.inc. If the install process
    // would use form classes (https://drupal.org/node/2112569), we could inherit.
    global $databases;

    $database = isset($databases['default']['default']) ? $databases['default']['default'] : array();

    require_once DRUPAL_ROOT . '/core/includes/install.inc';
    $drivers = drupal_get_database_types();
    $drivers_keys = array_keys($drivers);

    $form['database']['driver'] = array(
      '#type' => 'radios',
      '#title' => t('Database type'),
      '#required' => TRUE,
      '#default_value' => !empty($database['driver']) ? $database['driver'] : current($drivers_keys),
    );
    if (count($drivers) == 1) {
      $form['database']['driver']['#disabled'] = TRUE;
    }

    // Add driver specific configuration options.
    foreach ($drivers as $key => $driver) {
      $form['database']['driver']['#options'][$key] = $driver->name();

      $form['database']['settings'][$key] = $driver->getFormOptions($database);
      $form['database']['settings'][$key]['#prefix'] = '<h2 class="js-hide">' .
        $this->t('@driver_name settings', array('@driver_name' => $driver->name())) . '</h2>';
      $form['database']['settings'][$key]['#type'] = 'container';
      $form['database']['settings'][$key]['#tree'] = TRUE;
      $form['database']['settings'][$key]['advanced_options']['#parents'] = array($key);
      $form['database']['settings'][$key]['#states'] = array(
        'visible' => array(
          ':input[name=driver]' => array('value' => $key),
        )
      );
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['save'] = array(
      '#type' => 'submit',
      '#value' => t('Next'),
      '#button_type' => 'primary',
      '#limit_validation_errors' => array(
        array('driver'),
        array(isset($form_state['input']['driver']) ? $form_state['input']['driver'] : current($drivers_keys)),
      ),
    );
    return $form;
  }

  /**
   * Prepare to import configuration.
   */
  public function configurationStep(array &$form_state) {
    Database::addConnectionInfo('migrate', 'default', $form_state['database']);
    $version = $form_state['drupal_version'];

    $form['#title'] = $this->t('Upgrade step 2: Import configuration');

    $form['description'] = array(
      '#markup' => $this->t('We will now import configuration, including ' .
        'system settings and any vocabularies and content type and field ' .
        'definitions, from the Drupal @version version of your site into this ' .
        'new Drupal 8 site.', array('@version' => $version)),
      '#suffix' => '<br />',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Import configuration'),
    );

    return $form;
  }

  /**
   * Prepare to import configuration.
   */
  public function contentStep(array &$form_state) {
    Database::addConnectionInfo('migrate', 'default', $form_state['database']);
    $version = $form_state['drupal_version'];

    $form['#title'] = $this->t('Upgrade step 3: Import content');

    $form['description'] = array(
      '#markup' => $this->t('We will now import content, including any nodes, ' .
          'comments, users, and taxonomy terms, from the Drupal @version ' .
          'version of your site into this new Drupal 8 site.',
         array('@version' => $version)),
      '#suffix' => '<br />',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Import content'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    // The multistep is for testing only. The final version will run a fixed
    // set of migrations.
    // @todo: Skip credential step if 'migrate' connection already defined.
    if (!isset($form_state['database'])) {
      $form = $this->credentialStep();
    }
    elseif (isset($form_state['step'])) {
      $step = $form_state['step'];
      switch ($step) {
        case 'configuration':
          // @todo: Skip configuration step if configuration import is complete.
          $form = $this->configurationStep($form_state);
          break;
        case 'content':
          $form = $this->contentStep($form_state);
          break;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    if (isset($form_state['values']['driver'])) {
      // Verify we have a valid connection to a Drupal database supported for
      // upgrade.
      $driver = $form_state['values']['driver'];
      $form_state['database'] = $form_state['values'][$driver];
      $form_state['database']['driver'] = $driver;
      // @todo: There should be a DrupalSqlBase method to use to
      // determine the version.
      try {
        Database::addConnectionInfo('migrate', 'default', $form_state['database']);
        $connection = Database::getConnection('default', 'migrate');
      }
      catch (\Exception $e) {
        $message = t('Unable to connect to the source database. %message',
          array('%message' => $e->getMessage()));
        $this->setFormError(NULL, $form_state, $message);
        return;
      }
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
    if (isset($form_state['values']['driver'])) {
      $form_state['rebuild'] = TRUE;
      $form_state['step'] = 'configuration';
    }
    elseif (isset($form_state['step'])) {
      $migration_ids = $this->getDestinationIds($form_state['step']);
      $batch = array(
        'title' => t('Running migrations'),
        'operations' => array(
          array(array('Drupal\migrate_drupal\MigrateDrupalRunBatch', 'run'),
                array($migration_ids, $form_state['database'])),
        ),
        'progress_message' => '',
      );
      if ($form_state['step'] == 'configuration') {
        $form_state['rebuild'] = TRUE;
        $form_state['step'] = 'content';
        $batch['finished'] =
          array('Drupal\migrate_drupal\MigrateDrupalRunBatch', 'configurationFinished');
      }
      else {
        $form_state['redirect'] = '/';
        $batch['finished'] =
          array('Drupal\migrate_drupal\MigrateDrupalRunBatch', 'contentFinished');
      }
      $this->batchSet($batch);
    }
  }

  /**
   * Set a batch.
   *
   * @param $batch
   */
  protected function batchSet($batch) {
    batch_set($batch);
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
   * Gets migrate_drupal configurations.
   * @todo: remove(?)
   *
   * @param string $step
   *   Migration configuration destination type form step.
   *
   * @return array
   *   An array of configuration and content migrations.
   */
  function getDestinationIds($step) {
    $manifest = drupal_get_path('module', 'migrate_upgrade') . '/migrate.';
    if ($step == 'content') {
      $manifest .= 'content';
    }
    else {
      $manifest .= 'config';
    }
    $manifest .= '.yml';
    $list = Yaml::parse($manifest);
    $names = $list[$step];
    return $names;
  }
}
