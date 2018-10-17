<?php

namespace Drupal\dcom_checkout\Plugin\Commerce\Condition;

use CommerceGuys\Addressing\Zone\Zone;
use Drupal\commerce_shipping\Plugin\Commerce\Condition\ShipmentAddress;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the shipping address condition for shipments.
 *
 * Does the same as the "shipment_address" commerce condition.
 * Adds an additional condition to check whether is address line a PO Box.
 *
 * @CommerceCondition(
 *   id = "dcom_shipment_address_usps",
 *   label = @Translation("Shipping address"),
 *   display_label = @Translation("Limit by shipping address or address line is PO Box with the ability to negate"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_shipment",
 * )
 */
class DcomShipmentAddressUsps extends ShipmentAddress {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'negate' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['negate'] = [
      '#type' => 'checkbox',
      '#title' => t('Negate'),
      '#description' => t('If checked, the value(s) selected should not match.'),
      '#default_value' => $this->configuration['negate'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['negate'] = $values['negate'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $entity;
    $shipping_profile = $shipment->getShippingProfile();
    if (!$shipping_profile) {
      return FALSE;
    }
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $shipping_profile->get('address')->first();
    if (!$address) {
      // The conditions can't be applied until the shipping address is known.
      return FALSE;
    }
    $zone = new Zone([
      'id' => 'shipping',
      'label' => 'N/A',
    ] + $this->configuration['zone']);
    $po_box = $this->isPoBox($address->getAddressLine1()) || $this->isPoBox($address->getAddressLine2());
    $result = $zone->match($address) || $po_box;

    return $this->configuration['negate'] ? !$result : $result;
  }

  /**
   * Checks whether the address line is PO Box.
   *
   * @param string $address_line
   *   The address line.
   *
   * @return bool
   *   TRUE if the address line is PO Box.
   */
  protected function isPoBox($address_line) {
    $pattern = '/\bbox(?:\b$|([\s|\-]+)?[0-9]+)|(p[\-\.\s]*o[\-\.\s]*|(post office|post)\s)b(\.|ox|in)?\b|(^p[\.]?(o|b)[\.]?$)/is';
    return (bool) preg_match($pattern, $address_line);
  }

}
