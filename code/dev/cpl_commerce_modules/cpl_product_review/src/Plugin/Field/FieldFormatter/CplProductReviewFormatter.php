<?php

namespace Drupal\cpl_product_review\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'voting_api_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "cpl_product_review_formatter",
 *   label = @Translation("CPL Commerce product review formatter"),
 *   field_types = {
 *     "voting_api_field"
 *   }
 * )
 */
class CplProductReviewFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $entity = $items->getEntity();
    $results = \Drupal::service('plugin.manager.votingapi.resultfunction')->getResults($entity->getEntityTypeId(), $entity->id());

    if (!isset($results['cpl_average_review'])) {
      return $elements;
    }

    $elements[] = [
      '#theme' => 'cpl_product_review_formatter',
      '#results' => $results['cpl_average_review'],
    ];

    return $elements;
  }

}
