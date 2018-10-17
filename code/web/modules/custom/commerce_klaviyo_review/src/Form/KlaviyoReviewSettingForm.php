<?php

namespace Drupal\commerce_klaviyo_review\Form;

use Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements an ConfigFormBase form.
 */
class KlaviyoReviewSettingForm extends ConfigFormBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Field config storage.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorageConfigStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager, FieldConfigStorage $field_config_storage) {
    parent::__construct($config_factory);
    $this->entityFieldManager = $entity_field_manager;
    $this->fieldConfigStorage = $field_config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity.manager')->getStorage('field_config')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_klaviyo_review_setting_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_klaviyo_review.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_klaviyo_review.config');
    $form['#tree'] = TRUE;

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Klaviyo reviews functionality'),
      '#default_value' => $config->get('enabled', FALSE),
    ];
    $form['klaviyo_review_url_property'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of custom Klaviyo property which will be used as review URL'),
      '#description' => $this->t('Leave this field empty to use "@default_property" as property.', ['@default_property' => CommerceKlaviyoReviewInterface::DEFAULT_URL_PROPERTY]),
      '#default_value' => $config->get('klaviyo_review_url_property', ''),
    ];
    $comment_fields = $this->entityFieldManager->getFieldMapByFieldType('comment');
    if ($comment_fields) {
      $default_fields_config = $config->get('fields_config', []);
      foreach ($comment_fields as $entity_type => $field) {
        foreach ($field as $field_name => $field_settings) {
          foreach ($comment_fields[$entity_type][$field_name]['bundles'] as $bundle) {
            $config_name = "{$entity_type}-{$bundle}-{$field_name}";
            $field_config = $this->fieldConfigStorage->load("{$entity_type}.{$bundle}.{$field_name}");

            $default = isset($default_fields_config[$config_name]) ? $default_fields_config[$config_name] : [];
            if ($field_config->getThirdPartySetting('commerce_klaviyo_review', 'klaviyo_reviews', FALSE)) {
              $form['review_scores'][$config_name] = [
                '#header' => [],
                '#type' => 'details',
                '#title' => $this->t('Entity type: @entity_type. Bundle: @bundle. Field name: @field_name', [
                  '@entity_type' => $field_config->getTargetEntityTypeId(),
                  '@bundle' => $field_config->getTargetBundle(),
                  '@field_name' => $field_config->getName(),
                ]),
                '#open' => FALSE,
                'conf' => [
                  '#type' => 'table',
                  '#header' => [
                    $this->t('Name'),
                    $this->t('From'),
                    $this->t('To'),
                  ],
                  '#empty' => $this->t('Pls configure at least one comment field to be used for Klaviyo reviews.'),
                ],
              ];
              foreach (['Good', 'Neutral', 'Bad'] as $review_type) {
                $form['review_scores'][$config_name]['conf'][$review_type]['event_name'] = [
                  '#type' => 'textfield',
                  '#title' => $this->t('Klaviyo event name for @review_type reviews', ['@review_type' => $review_type]),
                  '#default_value' => isset($default[$review_type]['event_name']) ? $default[$review_type]['event_name'] : '',
                  '#required' => TRUE,
                ];
                $form['review_scores'][$config_name]['conf'][$review_type]['min'] = [
                  '#type' => 'textfield',
                  '#title' => $this->t('@review_type reviews minimum rating', ['@review_type' => $review_type]),
                  '#default_value' => isset($default[$review_type]['min']) ? $default[$review_type]['min'] : '',
                  '#required' => TRUE,
                ];
                $form['review_scores'][$config_name]['conf'][$review_type]['max'] = [
                  '#type' => 'textfield',
                  '#title' => $this->t('@review_type reviews maximum rating', ['@review_type' => $review_type]),
                  '#default_value' => isset($default[$review_type]['max']) ? $default[$review_type]['max'] : '',
                  '#required' => TRUE,
                ];
              }
            }
          }
        }
      }
    }
    else {
      // Shouldn't be possible to enable without configured comments fields.
      $form['enabled']['#disabled'] = TRUE;
    }
    if (empty($form['review_scores'])) {
      drupal_set_message($this->t('Please configure at least one comment field to be used for Klaviyo reviews.'), 'warning');
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_klaviyo_review.config');
    $fields_config = [];
    if ($review_scores = $form_state->getValue('review_scores')) {
      foreach ($review_scores as $field_key => $item) {
        list($entity_type, $bundle, $field_name) = explode('-', $field_key);
        $item['conf']['entity_type'] = $entity_type;
        $item['conf']['bundle'] = $bundle;
        $item['conf']['field_name'] = $field_name;
        $fields_config[$field_key] = $item['conf'];
      }
    }
    $config
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('fields_config', $fields_config)
      ->set('klaviyo_review_url_property', $form_state->getValue('klaviyo_review_url_property'))
      ->save();
  }

}
