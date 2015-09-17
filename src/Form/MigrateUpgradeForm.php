<?php

/**
 * @file
 * Contains \Drupal\migrate_upgrade\Form\MigrateUpgradeForm.
 */

namespace Drupal\migrate_upgrade\Form;

use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\ConfirmFormInterface;
use Drupal\Core\Installer\Form\SiteSettingsForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate_upgrade\MigrationCreationTrait;

/**
 * Form for performing direct site upgrades. Since we have the same need for
 * obtaining (source) database credentials on the install process, we build off
 * its form.
 */
class MigrateUpgradeForm extends SiteSettingsForm implements ConfirmFormInterface {

  use MigrationCreationTrait;

  /**
   * The submitted data needing to be confirmed.
   *
   * @var array
   */
  protected $data = [];

  /**
   * @todo: Find a mechanism to derive this information from the migrations
   *   themselves.
   *
   * @var array
   */
  protected $moduleUpgradePaths = [
    'd6_action_settings' => [
      'source_module' => 'system',
      'destination_module' => 'action'
    ],
    'd6_aggregator_feed' => [
      'source_module' => 'aggregator',
      'destination_module' => 'aggregator',
    ],
    'd6_aggregator_item' => [
      'source_module' => 'aggregator',
      'destination_module' => 'aggregator',
    ],
    'd6_aggregator_settings' => [
      'source_module' => 'aggregator',
      'destination_module' => 'aggregator',
    ],
    'd7_aggregator_settings' => [
      'source_module' => 'aggregator',
      'destination_module' => 'aggregator',
    ],
    'd7_blocked_ips' => [
      'source_module' => 'system',
      'destination_module' => 'ban',
    ],
    'd6_block' => [
      'source_module' => 'block',
      'destination_module' => 'block',
    ],
    'block_content_body_field' => [
      'source_module' => 'block',
      'destination_module' => 'block_content',
    ],
    'block_content_type' => [
      'source_module' => 'block',
      'destination_module' => 'block_content',
    ],
    'd6_custom_block' => [
      'source_module' => 'block',
      'destination_module' => 'block_content',
    ],
    'd6_book' => [
      'source_module' => 'book',
      'destination_module' => 'book',
    ],
    'd6_book_settings' => [
      'source_module' => 'book',
      'destination_module' => 'book',
    ],
    'd6_comment' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_entity_display' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_entity_form_display' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_entity_form_display_subject' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_field' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_field_instance' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_comment_type' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_entity_display' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_entity_form_display' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_entity_form_display_subject' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_field' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_field_instance' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd7_comment_type' => [
      'source_module' => 'comment',
      'destination_module' => 'comment',
    ],
    'd6_contact_category' => [
      'source_module' => 'contact',
      'destination_module' => 'contact',
    ],
    'd6_contact_settings' => [
      'source_module' => 'contact',
      'destination_module' => 'contact',
    ],
    'd6_dblog_settings' => [
      'source_module' => 'dblog',
      'destination_module' => 'dblog',
    ],
    'd7_dblog_settings' => [
      'source_module' => 'dblog',
      'destination_module' => 'dblog',
    ],
    'd6_field' => [
      'source_module' => 'content',
      'destination_module' => 'field',
    ],
    'd6_field_formatter_settings' => [
      'source_module' => 'content',
      'destination_module' => 'field',
    ],
    'd6_field_instance' => [
      'source_module' => 'content',
      'destination_module' => 'field',
    ],
    'd6_field_instance_widget_settings' => [
      'source_module' => 'content',
      'destination_module' => 'field',
    ],
    'd7_field' => [
      'source_module' => 'field',
      'destination_module' => 'field',
    ],
    'd7_field_formatter_settings' => [
      'source_module' => 'field',
      'destination_module' => 'field',
    ],
    'd7_field_instance' => [
      'source_module' => 'field',
      'destination_module' => 'field',
    ],
    'd7_field_instance_widget_settings' => [
      'source_module' => 'field',
      'destination_module' => 'field',
    ],
    'd7_view_modes' => [
      'source_module' => 'field',
      'destination_module' => 'field',
    ],
    'd6_file' => [
      'source_module' => 'system',
      'destination_module' => 'file',
    ],
    'd6_file_settings' => [
      'source_module' => 'system',
      'destination_module' => 'file',
    ],
    'd6_upload' => [
      'source_module' => 'upload',
      'destination_module' => 'file',
    ],
    'd6_upload_entity_display' => [
      'source_module' => 'upload',
      'destination_module' => 'file',
    ],
    'd6_upload_entity_form_display' => [
      'source_module' => 'upload',
      'destination_module' => 'file',
    ],
    'd6_upload_field' => [
      'source_module' => 'upload',
      'destination_module' => 'file',
    ],
    'd6_upload_field_instance' => [
      'source_module' => 'upload',
      'destination_module' => 'file',
    ],
    'd7_file' => [
      'source_module' => 'file',
      'destination_module' => 'file',
    ],
    'd6_filter_format' => [
      'source_module' => 'filter',
      'destination_module' => 'filter',
    ],
    'd7_filter_format' => [
      'source_module' => 'filter',
      'destination_module' => 'filter',
    ],
    'd6_forum_settings' => [
      'source_module' => 'forum',
      'destination_module' => 'forum',
    ],
    'd6_imagecache_presets' => [
      'source_module' => 'imagecache',
      'destination_module' => 'image',
    ],
    'd7_image_settings' => [
      'source_module' => 'image',
      'destination_module' => 'image',
    ],
    'd7_language_negotiation_settings' => [
      'source_module' => 'locale',
      'destination_module' => 'language',
    ],
    'locale_settings' => [
      'source_module' => 'locale',
      'destination_module' => 'locale',
    ],
    'd6_menu_links' => [
      'source_module' => 'menu',
      'destination_module' => 'menu_link_content',
    ],
    'd7_menu_links' => [
      'source_module' => 'menu',
      'destination_module' => 'menu_link_content',
    ],
    'menu_settings' => [
      'source_module' => 'menu',
      'destination_module' => 'menu_ui',
    ],
    'd6_node' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_revision' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_setting_promote' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_setting_status' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_setting_sticky' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_settings' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_node_type' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_view_modes' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd7_node' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd7_node_revision' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd7_node_settings' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd7_node_title_label' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd7_node_type' => [
      'source_module' => 'node',
      'destination_module' => 'node',
    ],
    'd6_url_alias' => [
      'source_module' => 'path',
      'destination_module' => 'path',
    ],
    'd6_search_page' => [
      'source_module' => 'search',
      'destination_module' => 'search',
    ],
    'd6_search_settings' => [
      'source_module' => 'search',
      'destination_module' => 'search',
    ],
    'd7_search_settings' => [
      'source_module' => 'search',
      'destination_module' => 'search',
    ],
    'd6_simpletest_settings' => [
      'source_module' => 'simpletest',
      'destination_module' => 'simpletest',
    ],
    'd7_simpletest_settings' => [
      'source_module' => 'simpletest',
      'destination_module' => 'simpletest',
    ],
    'd6_statistics_settings' => [
      'source_module' => 'statistics',
      'destination_module' => 'statistics',
    ],
    'd6_syslog_settings' => [
      'source_module' => 'syslog',
      'destination_module' => 'syslog',
    ],
    'd7_syslog_settings' => [
      'source_module' => 'syslog',
      'destination_module' => 'syslog',
    ],
    'd6_date_formats' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_cron' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_date' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_file' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_image' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_image_gd' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_logging' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_maintenance' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_performance' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_rss' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'd6_system_site' => [
      'source_module' => 'system',
      'destination_module' => 'system',
    ],
    'menu' => [
      'source_module' => 'menu',
      'destination_module' => 'system',
    ],
    'taxonomy_settings' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_taxonomy_term' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_taxonomy_vocabulary' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_term_node' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_term_node_revision' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_vocabulary_entity_display' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_vocabulary_entity_form_display' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_vocabulary_field' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'd6_vocabulary_field_instance' => [
      'source_module' => 'taxonomy',
      'destination_module' => 'taxonomy',
    ],
    'text_settings' => [
      'source_module' => 'text',
      'destination_module' => 'text',
    ],
    'd7_tracker_settings' => [
      'source_module' => 'tracker',
      'destination_module' => 'tracker',
    ],
    'd6_update_settings' => [
      'source_module' => 'update',
      'destination_module' => 'update',
    ],
    'd6_profile_values' => [
      'source_module' => 'profile',
      'destination_module' => 'user',
    ],
    'd6_user' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd6_user_contact_settings' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd6_user_mail' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd6_user_picture_file' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd6_user_role' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd6_user_settings' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd7_user' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd7_user_flood' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd7_user_mail' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'd7_user_role' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'user_picture_entity_display' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'user_picture_entity_form_display' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'user_picture_field' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'user_picture_field_instance' => [
      'source_module' => 'user',
      'destination_module' => 'user',
    ],
    'user_profile_entity_display' => [
      'source_module' => 'profile',
      'destination_module' => 'user',
    ],
    'user_profile_entity_form_display' => [
      'source_module' => 'profile',
      'destination_module' => 'user',
    ],
    'user_profile_field' => [
      'source_module' => 'profile',
      'destination_module' => 'user',
    ],
    'user_profile_field_instance' => [
      'source_module' => 'profile',
      'destination_module' => 'user',
    ],
  ];

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
    // When this is the confirmation step, present the confirmation form.
    if ($this->data) {
      return $this->buildConfirmForm($form, $form_state);
    }

    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Drupal Upgrade');

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
    $form['actions']['save']['#value'] = $this->t('Review upgrade');

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
  public function buildConfirmForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->getQuestion();

    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = ['#markup' => $this->getDescription()];
    $form[$this->getFormName()] = ['#type' => 'hidden', '#value' => 1];

    $form['module_list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Source module'), $this->t('Destination module'), $this->t('Data to be upgraded')],
    ];

    $table_data = [];
    $system_data = [];
    foreach ($this->data['migration_ids'] as $migration_id) {
      /** @var MigrationInterface $migration */
      $migration = Migration::load($migration_id);
      // Fetch the system data at the first opportunity.
      if (empty($system_data) && is_a($migration->getSourcePlugin(), '\Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase')) {
        $system_data = $migration->getSourcePlugin()->getSystemData();
      }
      $template_id = $migration->get('template');
      $source_module = $this->moduleUpgradePaths[$template_id]['source_module'];
      $destination_module = $this->moduleUpgradePaths[$template_id]['destination_module'];
      $table_data[$source_module][$destination_module][$migration_id] = $migration->label();
    }
    ksort($table_data);
    foreach ($table_data as $source_module => $destination_module_info) {
      ksort($table_data[$source_module]);
    }
    $last_source_module = $last_destination_module = '';
    foreach ($table_data as $source_module => $destination_module_info) {
      foreach ($destination_module_info as $destination_module => $migration_ids) {
        foreach ($migration_ids as $migration_id => $migration_label) {
          if ($source_module == $last_source_module) {
            $display_source_module = '';
          }
          else {
            $display_source_module = $source_module;
            $last_source_module = $source_module;
          }
          if ($destination_module == $last_destination_module) {
            $display_destination_module = '';
          }
          else {
            $display_destination_module = $destination_module;
            $last_destination_module = $destination_module;
          }
          $form['module_list'][$migration_id] = [
            'source_module' => ['#plain_text' => $display_source_module],
            'destination_module' => ['#plain_text' => $display_destination_module],
            'migration' => ['#plain_text' => $migration_label],
          ];
        }
      }
    }

    $unmigrated_source_modules = array_diff_key($system_data['module'], $table_data);
    ksort($unmigrated_source_modules);
    foreach ($unmigrated_source_modules as $source_module => $module_data) {
      if ($module_data['status']) {
        $form['module_list'][$source_module] = [
          'source_module' => ['#plain_text' => $source_module],
          'destination_module' => ['#plain_text' => ''],
          'migration' => ['#plain_text' => 'No upgrade path available'],
        ];
      }
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, $this->getRequest());

    // By default, render the form using theme_confirm_form().
    if (!isset($form['#theme'])) {
      $form['#theme'] = 'confirm_form';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // The confirmation step needs no additional validation.
    if ($this->data) {
      return;
    }

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

      // Also save the computed upgrade path info.
      $form_state->setValue('valid_upgrade_paths', $this->validUpgradePaths);
      $form_state->setValue('missing_destinations', $this->missingDestinations);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(NULL, $this->t($e->getMessage()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If this form has not yet been confirmed, store the values and rebuild.
    if (!$this->data) {
      $form_state->setRebuild();
      $this->data = $form_state->getValues();
      return;
    }

    $batch = [
      'title' => $this->t('Running upgrade'),
      'progress_message' => '',
      'operations' => [
        [['Drupal\migrate_upgrade\MigrateUpgradeRunBatch', 'run'], [$this->data['migration_ids']]],
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

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('migrate_upgrade.upgrade');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('<p><strong>This operation cannot be undone - be sure you have backed up your site database before proceeding</strong></p>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Perform upgrade');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    return 'confirm';
  }

}
