<?php

namespace Drupal\dcom_back_in_stock\Entity\Controller;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for stock_notification entity.
 *
 * @ingroup stock_notification
 */
class StockNotificationListBuilder extends EntityListBuilder {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * Constructs a new StockNotificationListBuilder object.
   *   The url generator.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('StockNotification Entity.', [
        '@adminlink' => $this->urlGenerator->generateFromRoute('stock_notification.settings'),
      ]),
    ];
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['email'] = $this->t('Email');
    $header['user_id'] = $this->t('User Name');
    $header['session_id'] = $this->t('Session ID');
    $header['email_sent'] = $this->t('Email Sent');
    $header['email_created'] = $this->t('Email Created');
    $header['product_variation'] = $this->t('Product Variation');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\dcom_back_in_stock\Entity\StockNotification */
    if (!empty($entity->user_id->getValue())) {
      $user = User::load($entity->user_id->getValue()[0]['target_id']);
    }
    $product_variation = ProductVariation::load($entity->product_variation->getValue()[0]['target_id']);

    $row['id'] = $entity->id();
    $row['email'] = $entity->email->value;
    $row['user_id'] = isset($user) ? $user->getUsername() : '';
    $row['session_id'] = $entity->session_id->value;
    $row['email_sent'] = $entity->email_sent->value;
    $row['email_created'] = $entity->email_created->value;
    $row['product_variation'] = !empty($product_variation) ? $product_variation->getTitle() : '';
    return $row + parent::buildRow($entity);
  }

}
