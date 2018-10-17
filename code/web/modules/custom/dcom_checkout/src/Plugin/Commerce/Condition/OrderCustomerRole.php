<?php

namespace Drupal\dcom_checkout\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderCustomerRole as OrderCustomerRoleBase;

/**
 * Provides the customer role condition for orders with the ability to negate.
 *
 * TODO Remove it once issue #2946334 is fixed.
 * We are not going to create a patch because it takes more time as per:
 * https://drupal.org/project/commerce_shipping/issues/2916954#comment-12305488
 *
 * @CommerceCondition(
 *   id = "dcom_order_customer_role",
 *   label = @Translation("Role"),
 *   display_label = @Translation("Limit by role with the ability to negate"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderCustomerRole extends OrderCustomerRoleBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['negate' => FALSE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate'),
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
    $evaluate = parent::evaluate($entity);
    return $this->configuration['negate'] ? !$evaluate : $evaluate;
  }

}
