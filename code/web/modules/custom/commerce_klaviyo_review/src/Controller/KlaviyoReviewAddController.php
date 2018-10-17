<?php

namespace Drupal\commerce_klaviyo_review\Controller;

use Drupal\commerce_klaviyo_review\CommerceKlaviyoReviewConfigHelperInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements controller for add Klaviyo review route.
 */
class KlaviyoReviewAddController extends ControllerBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, CommerceKlaviyoReviewConfigHelperInterface $commerce_klaviyo_review_helper) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->commerceKlaviyoReviewHelper = $commerce_klaviyo_review_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('commerce_klaviyo_review.config_helper')
    );
  }

  /**
   * Generates add review multistep form.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   Commerce order.
   * @param string $token
   *   Security token.
   *
   * @return array
   *   Render array.
   */
  public function addReview(OrderInterface $commerce_order, $token) {
    $order_id = $commerce_order->id();
    $config = $this->config('commerce_klaviyo_review.config');

    $review = $this->getReview($order_id, $token);
    $reviewed_products = [];
    foreach ($review->getComments() as $comment) {
      $reviewed_products[] = $comment->getCommentedEntityId();
    }

    foreach ($commerce_order->getItems() as $order_item) {
      if (!$purchased_entity = $order_item->getPurchasedEntity()) {
        continue;
      }
      if (!$product = $purchased_entity->getProduct()) {
        continue;
      }

      foreach ($config->get('fields_config') as $fields_config) {
        if ($fields_config['entity_type'] == $product->getEntityTypeId() && $fields_config['bundle'] == $product->bundle()) {
          $field_name = $fields_config['field_name'];
          // No sense to continue as field name is defined.
          break;
        }
      }

      $product_id = $product->id();
      // Skip order item if it has been already reviewed.
      if (in_array($product_id, $reviewed_products)) {
        continue;
      }
      $comment = $this->entityTypeManager->getStorage('comment')->create([
        'entity_type' => $product->getEntityTypeId(),
        'entity_id' => $product_id,
        'field_name' => $field_name,
        'uid' => $commerce_order->getCustomer()->id(),
      ]);

      // Flag to mark this form as klaviyo_review form.
      $form_state_additions = [
        'klaviyo_review' => TRUE,
        'commerce_order' => $order_id,
        'token' => $token,
        'review' => $review->id(),
      ];
      $form_state_additions['klaviyo_review'] = TRUE;

      $build['form'] = $this->entityFormBuilder()->getForm($comment, 'default', $form_state_additions);

      // Overrides hardcoded value in Drupal\comment\Entity\Comment::form().
      $build['form']['#action'] = Url::fromRoute('commerce_klaviyo_review.order_review', ['commerce_order' => $order_id, 'token' => $token])->toString();

      return $build;
    }

    // Display "Thank you" message in case all products have been reviewed.
    $build['thank_you'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['thank-you-wrapper', 'commerce-klaviyo-review'],
      ],
      'tick' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '',
        '#attributes' => ['class' => ['tick']],
      ],
      'ty' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Thank you!'),
        '#attributes' => ['class' => ['ty']],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['ty-message']],
        '#value' => $this->t('Your review has been submitted!'),
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Go home'),
        '#url' => Url::fromRoute('<front>'),
        '#attributes' => ['class' => ['gohome-link']],
      ],
    ];
    return $build;
  }

  /**
   * Obrains list products to review.
   *
   * @param int $order_id
   *   Commerce order.
   * @param string $token
   *   Security token.
   *
   * @return \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface
   *   Klaviyo review entity.
   */
  protected function getReview($order_id, $token) {
    $klaviyo_storage = $this->entityTypeManager->getStorage('klaviyo_review');
    $review_ids = $klaviyo_storage->getQuery()
      ->condition('order_id', $order_id)->execute();

    if (empty($review_ids) || !$this->commerceKlaviyoReviewHelper->isEnabled()) {
      // 404 if there is no klaviyo review associated with passed order id.
      $cache_metadata = new CacheableMetadata();
      $cache_metadata->addCacheContexts(['url']);
      throw new CacheableNotFoundHttpException($cache_metadata);
    }
    $review = $klaviyo_storage->load(reset($review_ids));
    if ($review && !Crypt::hashEquals($review->get('token')->getString(), $token)) {
      // 403 page in case of incorrect token.
      throw new CacheableAccessDeniedHttpException();
    }

    return $review;
  }

}
