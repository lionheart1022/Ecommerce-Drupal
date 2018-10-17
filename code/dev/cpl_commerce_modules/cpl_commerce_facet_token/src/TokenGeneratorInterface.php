<?php

namespace Drupal\cpl_commerce_facet_token;

/**
 * Interface TokenGeneratorInterface.
 */
interface TokenGeneratorInterface {

  const TOKEN_TITLE = 'active_title';
  const TOKEN_TERM_TITLE = 'active_term_title';
  const TOKEN_DESCRIPTION = 'active_description';
  const TOKEN_TERM_DESCRIPTION = 'active_term_description';
  const TOKEN_IMAGE = 'active_image';

  /**
   * Get active facet token value.
   *
   * @param string $name
   *   Token name. Currently, supported values are 'active_title' and
   *   'active_description'.
   *
   * @return string
   *   Token value.
   *
   * @throws \InvalidArgumentException
   *   Unknown token.
   */
  public function getTokenValue($name);

  /**
   * Get active term image token.
   *
   * @param string $name
   *   Token name.
   * @param string $original
   *   Original token value.
   *
   * @return string
   *   Token value.
   */
  public function getImageToken($name, $original);

}
