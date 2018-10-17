<?php

namespace Drupal\cpl_commerce_shop\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Product breadcrumbs configuration form.
 */
class BreadcrumbsForm extends ConfigFormBase {

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $field_manager, EntityTypeBundleInfoInterface $bundle_info) {
    parent::__construct($config_factory);
    $this->fieldManager = $field_manager;
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cpl_commerce_shop.breadcrumbs',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'product_breadcrumbs_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['taxonomy_reference_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy reference field'),
      '#options' => $this->getTaxonomyReferenceFieldOptions(),
      '#default_value' => $this->config('cpl_commerce_shop.breadcrumbs')->get('taxonomy_reference_field'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('cpl_commerce_shop.breadcrumbs')
      ->set('taxonomy_reference_field', $form_state->getValue('taxonomy_reference_field'))
      ->save();
  }

  /**
   * Get list of product entity type taxonomy reference fields.
   *
   * @return array
   *   A list of taxonomy reference fields, keyed by field machine name.
   */
  protected function getTaxonomyReferenceFieldOptions() {
    $options = [];

    $product_bundles = array_keys($this->bundleInfo->getBundleInfo('commerce_product'));
    foreach ($product_bundles as $bundle) {
      $bundle_fields = $this->fieldManager->getFieldDefinitions('commerce_product', $bundle);
      foreach ($bundle_fields as $field_config) {
        if ($field_config->getType() != 'entity_reference') {
          continue;
        }

        $item_definition = $field_config->getItemDefinition();
        if ($item_definition->getSetting('target_type') == 'taxonomy_term') {
          $options[$field_config->getName()] = $field_config->label();
        }
      }
    }

    return $options;
  }

}
