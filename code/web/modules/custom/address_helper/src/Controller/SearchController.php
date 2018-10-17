<?php

namespace Drupal\address_helper\Controller;

use Drupal\address_helper\Exception\NoSuchAddressException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SearchController.
 */
class SearchController extends ControllerBase {

  /**
   * Address Suggestion Service plugin manager.
   *
   * @var \Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface
   */
  protected $addressServicePluginManager;

  /**
   * SearchController contructor.
   *
   * @param \Drupal\address_helper\Plugin\AutocompleteServiceManagerInterface $service_plugin_manager
   *   Address Suggestion Service plugin manager.
   */
  public function __construct(AutocompleteServiceManagerInterface $service_plugin_manager) {
    $this->addressServicePluginManager = $service_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.address_helper_autocomplete_service')
    );
  }

  /**
   * Address suggestions search callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param string $service_id
   *   Address suggestions service ID.
   * @param string $country_code
   *   Country code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Search suggestions response JSON.
   *
   * @throws \Drupal\address_helper\Exception\ServiceException
   *   Address suggestions service error.
   */
  public function search(Request $request, $service_id, $country_code) {
    $results = [];
    // Cache by path + search query.
    $cache_metadata = (new CacheableMetadata())
      ->addCacheContexts(['url.path', 'url.query_args:q'])
      // Cache for one day.
      ->setCacheMaxAge(86400);

    try {
      /** @var \Drupal\address_helper\Plugin\AutocompleteServiceInterface $suggestion_service */
      $suggestion_service = $this
        ->addressServicePluginManager
        ->createInstance($service_id);
    }
    catch (PluginException $e) {
      // @TODO: Throw CacheableNotFoundHttpException instead.
      throw new NotFoundHttpException();
    }

    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      try {
        foreach ($suggestion_service->queryAddress($input, $country_code)->getSuggestions() as $suggestion) {
          $results[] = $suggestion->toArray();
        }
      }
      catch (NoSuchAddressException $e) {
        // No such address. Just return empty JSON.
      }
    }

    return (new CacheableJsonResponse($results))
      ->addCacheableDependency($cache_metadata);
  }

}
