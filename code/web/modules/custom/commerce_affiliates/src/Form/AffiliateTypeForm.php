<?php

namespace Drupal\commerce_affiliates\Form;

use Drupal\commerce_affiliates\CommerceAffiliatesPluginManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AffiliateTypeForm.
 *
 * @package Drupal\commerce_affiliates\Form
 */
class AffiliateTypeForm extends EntityForm {

  /**
   * The affiliate type plugin manager.
   *
   * @var \Drupal\commerce_affiliates\CommerceAffiliatesPluginManager
   */
  protected $pluginManager;

  /**
   * Constructs a new AffiliateTypeForm object.
   *
   * @param \Drupal\commerce_affiliates\CommerceAffiliatesPluginManager $plugin_manager
   *   The affiliate type plugin manager.
   */
  public function __construct(CommerceAffiliatesPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_affiliates')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_affiliates\Entity\AffiliateTypeInterface $type */
    $type = $this->entity;
    $plugins = array_column($this->pluginManager->getDefinitions(), 'label', 'id');
    asort($plugins);

    // Use the first available plugin as the default value.
    if (!$type->getPluginId()) {
      $plugin_ids = array_keys($plugins);
      $plugin = reset($plugin_ids);
      $type->setPluginId($plugin);
    }
    // The form state will have a plugin value if #ajax was used.
    $plugin = $form_state->getValue('plugin', $type->getPluginId());
    // Pass the plugin configuration only if
    // the plugin hasn't been changed via #ajax.
    $plugin_configuration = $type->getPluginId() == $plugin ? $type->getPluginConfiguration() : [];

    $wrapper_id = Html::getUniqueId('affiliate-type-form');
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_affiliates\Entity\AffiliateType::load',
      ],
      '#disabled' => !$type->isNew(),
    ];
    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => $this->t('Plugin'),
      '#options' => $plugins,
      '#default_value' => $plugin,
      '#required' => TRUE,
      '#disabled' => !$type->isNew(),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];
    $form['configuration'] = [
      '#type' => 'commerce_plugin_configuration',
      '#plugin_type' => 'commerce_affiliates',
      '#plugin_id' => $plugin,
      '#default_value' => $plugin_configuration,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $type->status(),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\commerce_affiliates\Entity\AffiliateTypeInterface $type */
    $type = $this->entity;
    $type->setPluginConfiguration($form_state->getValue(['configuration']));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addMessage($this->t('Saved the %label affiliate type.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_affiliate_type.collection');
  }

}
