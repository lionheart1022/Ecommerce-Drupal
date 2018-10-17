<?php

namespace Drupal\dcom_facets;

/**
 * Interface PublicFacetUrlInterface.
 */
interface PublicFacetUrlInterface {

  /**
   * Public method to get pretty facet URL.
   *
   * @param string $base_path
   *   Base page path.
   * @param array $filters_current_result
   *   Array of arrays of facet values, keyed by facet ID.
   *
   * @return \Drupal\Core\Url
   *   Pretty facet URL.
   */
  public function getPrettyFacetUrl($base_path, array $filters_current_result);

}
