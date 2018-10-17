<?php

namespace Drupal\address_helper\Data;

use Drupal\address_helper\Exception\NoSuchAddressException;

/**
 * Suggestions collection class.
 *
 * This is the very simple collection class which simply returns suggestions
 * provided to constructor.
 */
class StaticSuggestionsCollection implements SuggestionsCollectionInterface {

  /**
   * Static suggestions.
   *
   * @var \Drupal\address_helper\Data\SuggestionItemInterface[]
   */
  protected $suggestions;

  /**
   * StaticSuggestionsCollection constructor.
   *
   * @param \Drupal\address_helper\Data\SuggestionItemInterface[] $suggestions
   *   Suggestions list.
   */
  public function __construct(array $suggestions) {
    $this->assertSuggestions($suggestions);
    $this->suggestions = $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions() {
    if (empty($this->suggestions)) {
      throw new NoSuchAddressException();
    }
    return $this->suggestions;
  }

  /**
   * Assert suggestions.
   *
   * @param \Drupal\address_helper\Data\SuggestionItemInterface[] $suggestions
   *   Array of suggestions items.
   */
  protected function assertSuggestions(array $suggestions) {
    foreach ($suggestions as $row) {
      if (!($row instanceof SuggestionItemInterface)) {
        throw new \InvalidArgumentException('Invalid suggestion object.');
      }
    }
  }

}
