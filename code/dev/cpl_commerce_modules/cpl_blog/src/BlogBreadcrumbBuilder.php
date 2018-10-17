<?php

namespace Drupal\cpl_blog;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a custom blog breadcrumb builder.
 */
class BlogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a BlogBreadcrumbBuilder object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $parameter = $route_match->getParameter('node');
    if (!empty($parameter)) {

      return $parameter->getType() == 'article';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $query = \Drupal::entityQuery('node');
    $query
      ->condition('status', 1)
      ->condition('type', 'paragraphs_page')
      ->condition('field_main_blogs_page', 1)
      ->range(0, 1);
    $this->moduleHandler->invokeAll('cpl_blog_page_query', [$query]);
    $result = $query->execute();

    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute(t('Home'), '<front>'));
    if (!empty($result)) {
      $node_url = Url::fromRoute('entity.node.canonical', array('node' => array_shift($result)));
      $breadcrumb->addLink(Link::fromTextAndUrl(t('Blog'), $node_url));
    }

    return $breadcrumb;
  }

}
