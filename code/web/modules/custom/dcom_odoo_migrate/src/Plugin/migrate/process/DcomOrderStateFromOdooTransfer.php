<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Marks the order as completed if all Odoo transfers are fulfilled.
 *
 * @code
 * process:
 *   state:
 *     plugin: dcom_order_state_from_odoo_transfer
 *     source: picking_ids
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_order_state_from_odoo_transfer",
 *   handle_multiples = TRUE
 * )
 */
class DcomOrderStateFromOdooTransfer extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!($result = $this->validateExtractValue($value))) {
      throw new MigrateException('Can not lookup without a valid order states.');
    }

    list($transfer_states, $order_state) = $result;

    if ($order_state == 'cancel') {
      return 'canceled';
    }

    $fulfilled = count(array_unique($transfer_states)) === 1 && end($transfer_states) === 'done';
    return $fulfilled ? 'completed' : NULL;
  }

  /**
   * Validates and tries to extract the input value.
   *
   * Applies a bunch of validations to make sure that order transfers states
   * are passed together with the order state.
   *
   * @param mixed $value
   *   The input value.
   *
   * @return array|bool
   *   FALSE if validation failed or a transfer states array and order state.
   */
  protected function validateExtractValue($value) {
    if (!empty($value) && is_array($value) && count($value) == 2) {
      $transfers = reset($value);
      $state = end($value);

      if (!empty($transfers) && !empty($state) && is_array($transfers)) {
        foreach ($transfers as $transfer) {
          if (empty($transfer['state'])) {
            return FALSE;
          }
          $transfer_states[] = $transfer['state'];
        }

        return [$transfer_states, $state];
      }
    }
    return FALSE;
  }

}
