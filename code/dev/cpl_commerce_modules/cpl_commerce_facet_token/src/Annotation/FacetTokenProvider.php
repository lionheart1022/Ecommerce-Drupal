<?php

namespace Drupal\cpl_commerce_facet_token\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Facet-based tokens provider item annotation object.
 *
 * @see \Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class FacetTokenProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
