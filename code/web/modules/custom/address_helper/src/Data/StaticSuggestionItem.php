<?php

namespace Drupal\address_helper\Data;

use CommerceGuys\Addressing\Address;

/**
 * Class representing single static address suggestion item.
 */
class StaticSuggestionItem extends SuggestionItemBase {

  /**
   * Static address object.
   *
   * @var \CommerceGuys\Addressing\Address
   */
  protected $address;

  /**
   * Address suggestion label.
   *
   * @var string
   */
  private $text;

  /**
   * Secondary label.
   *
   * @var string
   */
  protected $secondaryLabel;

  /**
   * Static address suggestion item constructor.
   *
   * @param \CommerceGuys\Addressing\Address $address
   *   Address object.
   * @param string $text
   *   Address suggestion label.
   * @param string $secondary_label
   *   Secondary address suggestion label.
   */
  public function __construct(Address $address, $text = NULL, $secondary_label = NULL) {
    $this->address = $address;
    $this->text = $text;
    $this->secondaryLabel = $secondary_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddress() {
    return $this->address;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionLabel() {
    return isset($this->text) ? $this->text : $this->getAddress()->getAddressLine1();
  }

  /**
   * {@inheritdoc}
   */
  public function getSecondaryLabel() {
    return $this->secondaryLabel;
  }

}
