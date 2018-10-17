<?php

namespace Drupal\commerce_klaviyo_review\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Commerce Klaviyo Review edit forms.
 *
 * @ingroup commerce_klaviyo_review
 */
class CommerceKlaviyoReviewForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface */
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Commerce Klaviyo Review.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Commerce Klaviyo Review.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.klaviyo_review.canonical', ['klaviyo_review' => $entity->id()]);
  }

}
