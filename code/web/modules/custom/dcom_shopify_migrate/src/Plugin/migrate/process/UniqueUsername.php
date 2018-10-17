<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\Plugin\migrate\process\MakeUniqueEntityField;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Custom unique username formatter.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_unique_username
 *     entity_type: user
 *     field: name
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_unique_username"
 * )
 */
class UniqueUsername extends MakeUniqueEntityField {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $destination_ids = $this->migration->getIdMap()->lookupDestinationIds($row->getSourceIdValues());
    if (!empty($destination_ids[0][0])) {
      // User existing username if user already exists.
      /** @var \Drupal\user\UserInterface $user */
      $user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($destination_ids[0][0]);
      if ($user) {
        return $user->getAccountName();
      }
      return $value;
    }

    $source = $row->getSource();
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $uid = $user_storage
        ->getQuery()
      ->condition('mail', $source['email'])
      ->range(0, 1)
      ->execute();

    if ($uid) {
      $uid = reset($uid);
      if ($user = $user_storage->load($uid)) {
        /** @var \Drupal\user\UserInterface $user */
        $line = implode(',', [
          $source['id'],
          $source['email'],
        ]) . "\n";
        file_put_contents('private://existing_shopify_retail_users.csv', $line, FILE_APPEND);
        return $user->getAccountName();
      }
    }
    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

}
