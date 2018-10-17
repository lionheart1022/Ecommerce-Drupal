<?php

namespace Drupal\address_helper\Plugin\Field\FieldWidget;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Plugin implementation of the 'address_helper' widget.
 *
 * @FieldWidget(
 *   id = "address_helper",
 *   label = @Translation("Address Suggestion"),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class AddressWidget extends AddressDefaultWidget implements ContainerFactoryPluginInterface {

  /**
   * Address Suggestion Service plugin manager.
   *
   * @var \Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface
   */
  protected $addressServicePluginManager;

  /**
   * Constructs a AddressDefaultWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface $service_plugin_manager
   *   Address Suggestion Service plugin manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CountryRepositoryInterface $country_repository, EventDispatcherInterface $event_dispatcher, ConfigFactoryInterface $config_factory, AutocompleteServiceManagerInterface $service_plugin_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $country_repository, $event_dispatcher, $config_factory);

    $this->addressServicePluginManager = $service_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('address.country_repository'),
      $container->get('event_dispatcher'),
      $container->get('config.factory'),
      $container->get('plugin.manager.address_helper_autocomplete_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'address_helper_service' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['address_helper_service'] = [
      '#type' => 'select',
      '#title' => $this->t('Address suggestion service'),
      '#options' => $this->addressServicePluginManager->getOptionsList(),
      '#default_value' => $this->getSetting('address_helper_service'),
      '#empty_value' => '',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $service_plugin_id = $this->getSetting('address_helper_service');
    try {
      if ($service_plugin_id) {
        $service_plugin = $this->addressServicePluginManager->getDefinition($service_plugin_id)['label'];
      }
      else {
        $service_plugin = $this->t('None');
      }
    }
    catch (PluginNotFoundException $e) {
      $service_plugin = $this->t('Broken/missing plugin @id', ['@id' => $service_plugin_id]);
    }

    $summary['address_helper_service'] = $this->t('Address suggestion service: @service', ['@service' => $service_plugin]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $build = parent::formElement($items, $delta, $element, $form, $form_state);
    $service_id = $this->getSetting('address_helper_service');
    if ($service_id
      && $this->addressServicePluginManager->hasDefinition($service_id)) {
      $build['address']['#address_helper_service'] = $this->getSetting('address_helper_service');
    }
    return $build;
  }

}
