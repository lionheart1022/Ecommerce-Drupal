<?php

namespace Drupal\commerce_affiliates\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the affiliates plugin annotation object.
 *
 * Plugin namespace: Plugin\Commerce\Affiliates.
 *
 * @Annotation
 */
class CommerceAffiliate extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The affiliate "tracking type" of the plugin.
   *
   * Ex: "html".
   *
   * @var string
   */
  public $trackingType;

}
