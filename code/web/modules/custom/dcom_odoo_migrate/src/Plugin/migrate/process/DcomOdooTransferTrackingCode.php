<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\odoo_api\OdooApi\ClientInterface;

/**
 * Transforms odoo transfer tracking code.
 *
 * @code
 * process:
 *   tracking_code:
 *     plugin: dcom_odoo_transfer_tracking_code
 *     source: carrier_tracking_ref
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_odoo_transfer_tracking_code",
 *   handle_multiples = TRUE
 * )
 */
class DcomOdooTransferTrackingCode extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $source = $row->getSource();

    if (empty($source['create_date'])) {
      throw new MigrateException('The field create_date is required to do not import old wrong transfer numbers.');
    }
    $import_from = DrupalDateTime::createFromFormat(ClientInterface::ODOO_DATETIME_FORMAT, '2018-06-07 00:00:00', 'UTC')
      ->getTimestamp();

    // Transfers with the create_date<2018-06-07 00:00:00 has wrong tracking
    // codes. Do not import it.
    if ($source['create_date'] < $import_from) {
      return NULL;
    }

    // For some reason carrier_tracking_ref is not reliable. Sometimes it is
    // a string, sometimes it is an array.
    return is_array($value) ? reset($value) : $value;
  }

}
