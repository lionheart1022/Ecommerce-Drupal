<?php

namespace Drupal\commerce_nmi\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for removing all events for the element.
 *
 * @ingroup ajax
 */
class OffEventsCommand implements CommandInterface {

  /**
   * The drupal selector.
   *
   * @var string
   */
  protected $drupalSelector;

  /**
   * OffEventsCommand constructor.
   *
   * @param string $drupal_selector
   *   The drupal selector.
   */
  public function __construct($drupal_selector) {
    $this->drupalSelector = $drupal_selector;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'offEvents',
      'drupal_selector' => $this->drupalSelector,
    ];
  }

}
