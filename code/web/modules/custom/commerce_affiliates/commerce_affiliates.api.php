<?php

/**
 * @file
 * Document all supported APIs.
 */

/**
 * Alter affiliate types before tracking.
 *
 * @param array $affiliate_types
 *   The array of $affiliate_types entities keyed by the affiliate_type id.
 * @param array $context
 *   The context array. Contains 'order_checkout' key with the order entity if
 *   the affiliate types are applied on the "affiliates_processing" commerce
 *   checkout pane.
 */
function hook_commerce_affiliate_types_alter(array &$affiliate_types, array $context) {
  if (isset($affiliate_types['xyz'])) {
    unset($affiliate_types['xyz']);
  }
}
