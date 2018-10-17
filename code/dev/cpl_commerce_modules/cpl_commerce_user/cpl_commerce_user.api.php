<?php

/**
 * @file
 * Hooks provided by the CPL Commerce User module.
 */

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * React on order counter query.
 *
 * @param \Drupal\Core\Entity\Query\QueryInterface $query
 *   Order entity query.
 */
function hook_cpl_commerce_order_counter_query(QueryInterface $query) {
  $query->condition('my_order_field', 'some_value');
}
