<?php

namespace Drupal\cpl_commerce_shop;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a commerce product breadcrumb builder.
 */
class ProductBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * Product breadcrumbs configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new ProductBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('cpl_commerce_shop.breadcrumbs');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if (!$this->config->get('taxonomy_reference_field')) {
      return FALSE;
    }

    $product = $route_match->getParameter('commerce_product');
    return !empty($product);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $product = $route_match->getParameter('commerce_product');

    $links = [Link::createFromRoute($this->t('Home'), '<front>')];
    $field_name = $this->config->get('taxonomy_reference_field');
    if (!empty($product->{$field_name}->entity)) {
      $category_term = $product->{$field_name}->entity;
      if ($category_term instanceof TermInterface) {
        $links[] = $category_term->toLink();
        $breadcrumb->addCacheableDependency($category_term);
      }
    }

    $breadcrumb->addCacheContexts(['url.path']);
    $breadcrumb->addCacheableDependency($product);
    $breadcrumb->addCacheableDependency($this->config);
    return $breadcrumb->setLinks($links);
  }

}
