<?php

namespace Drupal\dcom_back_in_stock\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\Language;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the stock_notification entity edit forms.
 *
 * @ingroup stock_notification
 */
class StockNotificationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\dcom_back_in_stock\Entity\StockNotification */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.stock_notification.collection');
    $entity = $this->getEntity();
    $entity->save();
  }

}
