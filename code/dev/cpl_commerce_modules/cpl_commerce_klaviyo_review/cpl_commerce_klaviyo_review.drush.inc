<?php

/**
 * @file
 * Drush commands for cpl_commerce_klaviyo_review module.
 */

use Drupal\commerce_klaviyo\CustomerProperties;
use Drupal\commerce_klaviyo\OrderProperties;
use Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface;
use Drupal\commerce_klaviyo_review\Event\GetReviewLinkEvent;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Url;

/**
 * Implements hook_drush_command().
 */
function cpl_commerce_klaviyo_review_drush_command() {
  $items = [];

  $items['commerce-create-klaviyo-reviews'] = [
    'description' => 'Creates klaviyo reviews entities for existing orders.',
    'options' => [
      'klaviyo-event' => [
        'description' => 'Name of Kalviyo revent',
        'example-value' => 'Placed Order',
        'required' => TRUE,
      ],
    ],
    'examples' => [
      'drush commerce-create-klaviyo-reviews --klaviyo-event="Placed Order"',
    ],
  ];
  return $items;
}

/**
 * Drush command callback for commerce-create-klaviyo-reviews.
 */
function drush_cpl_commerce_klaviyo_review_commerce_create_klaviyo_reviews() {
  $args = [drush_get_option('klaviyo-event')];
  $operations[] = ['cpl_commerce_create_klaviyo_reviews', $args];
  batch_set([
    'operations' => $operations,
    'finished' => 'cpl_commerce_create_klaviyo_reviews_batch_finished',
  ]);
  drush_backend_batch_process();
}

/**
 * Defines a batch process that syncs commerce_klaviyo historical data.
 */
function cpl_commerce_create_klaviyo_reviews($klaviyo_event, &$context) {
  $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
  $review_builder = \Drupal::service('commerce_klaviyo_review.review_builder');

  $query = \Drupal::database()->select('commerce_order', 'co');
  $query->leftJoin('klaviyo_review', 'kr', 'kr.order_id = co.order_id');
  $query->fields('co', ['order_id'])
    ->condition('co.state', 'completed')
    ->isNull('kr.order_id')
    ->orderBy('co.order_id', 'ASC');

  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_id'] = 0;
    $count = clone($query);
    $context['sandbox']['max'] = $count->countQuery()->execute()->fetchField();
  }

  $limit = 50;
  $ids = $query
    ->condition('co.order_id', $context['sandbox']['current_id'], '>')
    ->range(0, $limit)
    ->execute()
    ->fetchCol();

  $context['message'] = 'Running next batch of 50 orders';

  if ($ids) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $entities */
    $entities = $storage->loadMultiple($ids);
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::service('config.factory');
    $klaviyo = \Drupal::service('commerce_klaviyo.klaviyo_request');
    /** @var \Drupal\commerce_order\OrderTotalSummaryInterface $order_total_summary */
    $orderTotalSummary = \Drupal::service('commerce_order.order_total_summary');
    $event_dispatcher = \Drupal::service('event_dispatcher');

    foreach ($entities as $id => $order) {
      if (!empty($order)) {
        $account = $order->getCustomer();

        if (empty($account)) {
          continue;
        }

        // Setting correct host into request context and into global variable.
        // Getting host using GetReviewLinkEvent event.
        $url = Url::fromRoute('<front>');
        $event_dispatcher->dispatch(GetReviewLinkEvent::GET_REVIEW_LINK_EVENT, new GetReviewLinkEvent($url, $order));
        $host = parse_url($url->getOption('base_url'), PHP_URL_HOST);

        // Changing host in request context.
        $old_context = \Drupal::urlGenerator()->getContext();
        $new_context = new RequestContext();
        $new_context->setHost($host);
        \Drupal::urlGenerator()->setContext($new_context);

        // Changing host in $GLOBALS['base_url'].
        /* @see file_create_url(). */
        $old_base_url = $GLOBALS['base_url'];
        $GLOBALS['base_url'] = $host;

        $customer_properties = CustomerProperties::createFromUser($account);

        // Creating klaviyo_review entity.
        $review_builder->createReview($order);

        $properties = new OrderProperties($config_factory, $order);


        $totals = $orderTotalSummary->buildTotals($order);
        $adjustments_total = 0;

        foreach ($totals['adjustments'] as $adjustment) {
          $amount = $adjustment['amount'];
          $adjustments_total += $amount->getNumber();
        }

        $properties->setProperty('Discount Value', abs($adjustments_total));

        $config = $config_factory->get('commerce_klaviyo_review.config');

        $properties->setProperty($config->get('klaviyo_review_url_property', CommerceKlaviyoReviewInterface::DEFAULT_URL_PROPERTY), $review_builder->getReviewLink($order));

        $klaviyo->identify($customer_properties);
        $klaviyo->track($klaviyo_event, $customer_properties, $properties, $order->getCreatedTime());

        $context['message'] = "Creating review for order #{$id}";
        $context['results'][] = $id;

        // Revering host values.
        $GLOBALS['base_url'] = $old_base_url;
        \Drupal::urlGenerator()->setContext($old_context);
      }

      $context['sandbox']['progress']++;
      $context['sandbox']['current_id'] = $id;
    }
  }
  else {
    $context['sandbox']['progress'] = $context['sandbox']['max'];
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    drush_log('Klaviyo reviews created: ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');
  }
}

/**
 * Provides a batch summary result.
 */
function cpl_commerce_create_klaviyo_reviews_batch_finished($success, $results, $operations) {
  if ($success) {
    $message = \Drupal::translation()->formatPlural(
      count($results),
      'One review created.', '@count reviews created.'
    );
  }
  else {
    $message = t('Finished with an error.');
  }
  drupal_set_message($message);
}
