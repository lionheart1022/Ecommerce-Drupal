<?php

namespace Drupal\address_helper\Exception;

/**
 * No such address exception.
 *
 * Thrown when a third-party service returns no suggestions.
 */
class NoSuchAddressException extends AddressSuggestionException {}
