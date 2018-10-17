<?php

namespace Drupal\cpl_commerce_payment;

use Drupal\commerce_payment\PaymentMethodListBuilder as DefaultPaymentMethodListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserStorageInterface;

/**
 * Defines the list builder for payment methods.
 */
class PaymentMethodListBuilder extends DefaultPaymentMethodListBuilder {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * User entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * PaymentMethodListAlterBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form builder service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The user storage handler.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $formBuilder, AccountInterface $currentUser, UserStorageInterface $userStorage) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $formBuilder;
    $this->userStorage = $userStorage;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('entity.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['view'] = views_embed_view('cpl_commerce_payment_methods', 'cpl_commerce_payment_block');

    // TODO Uncomment the payment add form once it is ready.
    // $build['form'] = $this->formBuilder->getForm('Drupal\commerce_payment\Form\PaymentMethodAddForm', $this->userStorage->load($this->currentUser->id()));

    return $build;
  }

}
