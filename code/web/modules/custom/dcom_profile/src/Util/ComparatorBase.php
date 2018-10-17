<?php

namespace Drupal\dcom_profile\Util;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class ComparatorBase.
 *
 * @package Drupal\dcom_profile\Util
 */
abstract class ComparatorBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ComparatorBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
