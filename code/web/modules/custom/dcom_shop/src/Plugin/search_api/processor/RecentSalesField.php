<?php

namespace Drupal\dcom_shop\Plugin\search_api\processor;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds access checks for profiles.
 *
 * @SearchApiProcessor(
 *   id = "dcom_recent_sales",
 *   label = @Translation("Recent Sales Field"),
 *   description = @Translation("Adds Recent Sales field."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class RecentSalesField extends ProcessorPluginBase {
  protected $intervals = [
    1,
    3,
    7,
    14,
    30,
    45,
    60,
    180,
  ];

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return in_array('commerce_product', $index->getEntityTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      return $properties;
    }

    foreach ($this->intervals as $interval) {
      $properties['recent_sales_' . $interval] = (new ProcessorProperty([
        'label' => $this->formatPlural($interval, 'Product sales in last 1 day', 'Product sales in last @count days'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ]))
        ->setComputed(TRUE);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    if (!$item->getDatasourceId()) {
      return;
    }

    $product = $item->getOriginalObject()->getValue();
    if ($product instanceof ProductInterface) {
      foreach ($item->getFields() as $field_name => $field) {
        if (preg_match('/^recent_sales_(\d+)$/', $field->getPropertyPath(), $matches)) {
          $field->addValue($this->getRecentSales($product, $matches[1]));
        }
      }
    }
  }

  /**
   * Get number of recent sales for given product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   Product entity.
   * @param int|null $days
   *   Number of days.
   *
   * @return int|null
   *   Number of recent sales during given amount of days or NULL.
   */
  protected function getRecentSales(ProductInterface $product, $days = NULL) {
    $variants_ids = $product->getVariationIds();
    if (!$variants_ids) {
      return NULL;
    }
    $query = \Drupal::entityQueryAggregate('commerce_order_item')
      ->aggregate('quantity', 'SUM', NULL, $alias);
    $query
      ->condition('purchased_entity.target_id', $variants_ids, 'IN')
      ->condition('order_id.entity.cart', FALSE)
      ->condition('order_id.entity.state', 'draft', '!=');

    if (isset($days)) {
      $sales_end_time = dcom_shop_get_sales_last_index();
      $date = DrupalDateTime::createFromTimestamp($sales_end_time)
        ->sub(\DateInterval::createFromDateString($days . ' days'))
        ->setTime(0, 0, 0);
      $query->condition('order_id.entity.placed', $date->getTimestamp(), '>=');
      // We always add condition on sales end time (which is request time for
      // most cases) since otherwise products indexed at different time would
      // have data for different intervals.
      $query->condition('order_id.entity.placed', $sales_end_time, '<=');
    }

    $result = $query->execute();
    $result_row = reset($result);
    return isset($result_row[$alias]) ? $result_row[$alias] : 0;
  }

}
