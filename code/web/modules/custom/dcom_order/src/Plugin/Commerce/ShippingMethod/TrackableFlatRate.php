<?php

namespace Drupal\dcom_order\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\FlatRate;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the Trackable FlatRate shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "trackable_flat_rate",
 *   label = @Translation("Trackable flat rate"),
 * )
 */
class TrackableFlatRate extends FlatRate implements SupportsTrackingInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'options' => [
        'tracking_url' => '',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Additional options'),
    ];
    $form['options']['tracking_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tracking URL base'),
      '#description' => $this->t('The base URL for assembling a tracking URL. If the [tracking_code] token is omitted, the code will be appended to the end of the URL.'),
      '#default_value' => $this->configuration['options']['tracking_url'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['options'] = $values['options'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingUrl(ShipmentInterface $shipment) {
    $code = $shipment->getTrackingCode();
    if (!empty($code)) {
      // If the tracking code token exists, replace it with the code.
      if (strstr($this->configuration['options']['tracking_url'], '[tracking_code]')) {
        $url = str_replace('[tracking_code]', $code, $this->configuration['options']['tracking_url']);
      }
      else {
        // Otherwise, append the tracking code to the end of the URL.
        $url = $this->configuration['options']['tracking_url'] . $code;
      }

      return Url::fromUri($url);
    }
    return NULL;
  }

}
