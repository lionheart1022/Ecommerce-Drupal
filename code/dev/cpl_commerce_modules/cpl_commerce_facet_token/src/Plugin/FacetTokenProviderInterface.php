<?php

namespace Drupal\cpl_commerce_facet_token\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Facet-based tokens provider plugins.
 */
interface FacetTokenProviderInterface extends PluginInspectionInterface {

  /**
   * Get facet based metatag title value.
   *
   * @param bool $use_meta_title
   *   Whether field_meta_title should be used instead of term label.
   *
   * @return string
   *   Facet values based metatag title.
   */
  public function getTitleToken($use_meta_title);

  /**
   * Get facet based metatag description value.
   *
   * @param bool $use_meta_description
   *   Whether field_meta_description should be used instead of term
   *   description.
   *
   * @return string
   *   Facet values based metatag description.
   */
  public function getDescriptionToken($use_meta_description);

  /**
   * Get active term image token.
   *
   * @param string $original
   *   Original token value.
   *
   * @return string
   *   Token value.
   */
  public function getImageToken($original);

}
