<?php

/**
 * @file
 * Hooks provided by the CPL Blog module.
 */

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * React on blog page query.
 *
 * @param \Drupal\Core\Entity\Query\QueryInterface $query
 *   Order entity query.
 */
function hook_cpl_blog_page_query(QueryInterface $query) {
  $query->condition('my_order_field', 'some_value');
}
