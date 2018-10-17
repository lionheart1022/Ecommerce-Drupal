<?php

namespace Drupal\dcom_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\domain_simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator as DomainSimpleSitemapEntityUrlGenerator;

/**
 * Class EntityUrlGenerator.
 *
 * @package Drupal\dcom_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "dcom_domain_entity",
 *   weight = 10,
 *   instantiateForEachDataSet = true
 * )
 */
class EntityUrlGenerator extends DomainSimpleSitemapEntityUrlGenerator {

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationElements(array $entity_info) {
    $query = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])
      ->getQuery();

    if (!empty($entity_info['keys']['id'])) {
      $query->sort($entity_info['keys']['id'], 'ASC');
    }
    if (!empty($entity_info['keys']['bundle'])) {
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    }
    if (!empty($entity_info['keys']['status'])) {
      $query->condition($entity_info['keys']['status'], 1);
    }
    if ($entity_info['entity_type_name'] == 'node') {
      $orGroupDomain = $query->orConditionGroup()
        ->condition(DOMAIN_ACCESS_FIELD . '.target_id', $this->domainNegotiator->getActiveId())
        ->condition(DOMAIN_ACCESS_ALL_FIELD, 1);
      $query->condition($orGroupDomain);
    }
    elseif (in_array($entity_info['entity_type_name'], [
      'commerce_product',
      'taxonomy_term',
    ])) {
      $query->condition(DCOM_SITEMAP_DOMAIN_ACCESS_FIELD . '.target_id', $this->domainNegotiator->getActiveId());
    }

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->count()->execute());
    }
    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit']);
    }

    return $this->entityTypeManager
      ->getStorage($entity_info['entity_type_name'])
      ->loadMultiple($query->execute());
  }

}
