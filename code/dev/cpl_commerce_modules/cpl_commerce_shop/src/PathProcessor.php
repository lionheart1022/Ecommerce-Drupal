<?php

namespace Drupal\cpl_commerce_shop;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\Request;

/**
 * Collections terms path processor.
 */
class PathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (isset($options['entity_type'])
      && isset($options['entity'])
      && $options['entity_type'] == 'taxonomy_term') {
      $path_parts = explode('/', ltrim($path, '/'));
      if (count($path_parts) == 3
        && !empty($options['entity']->field_is_collection->value)
        && (!empty($options['entity']->machine_name->value) || !empty($options['entity']->field_url_value->value))) {
        $url = Url::fromUri('internal:/collections/{facets_query}')
          ->setRouteParameter('facets_query', $this->getTermSlugValue($options['entity']));
        return $url->toString();
      }
    }
    return $path;
  }

  /**
   * Get taxonomy term slug URL value.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   Taxonomy term object.
   *
   * @return string
   *   Slug URL value.
   */
  protected function getTermSlugValue(Term $term) {
    if (!empty($term->field_url_value->value)) {
      return $term->field_url_value->value;
    }

    return $term->machine_name->value;
  }

}
