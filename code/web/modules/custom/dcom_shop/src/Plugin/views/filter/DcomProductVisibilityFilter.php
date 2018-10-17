<?php

namespace Drupal\dcom_shop\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler which filters by field_visibility_option depend on user role.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("dcom_product_visibility_filter")
 */
class DcomProductVisibilityFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $account
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
  }

  /**
   * Determine if a filter can be exposed.
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";

    $values = ['everywhere'];

    // Shows Products marked as "Show Only on wholesale" for user with role
    // "Wholesale 1".
    // @TODO: Support user roles other than "Wholesale 1".
    if (in_array('wholesale_1', $this->account->getRoles())) {
      $values[] = 'wholesale';
    }
    else {
      $values[] = 'retail';
    }

    $this->query->addWhere($this->options['group'], $field, $values, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    if (!in_array('user.roles', $contexts)) {
      $contexts[] = 'user.roles';
    }

    return $contexts;
  }

}
