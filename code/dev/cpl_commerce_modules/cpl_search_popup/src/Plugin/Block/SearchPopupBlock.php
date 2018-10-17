<?php

namespace Drupal\cpl_search_popup\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Search Popup' Block.
 *
 * @Block(
 *   id = "search_popup_block",
 *   admin_label = @Translation("Search Popup block"),
 *   category = @Translation("CPL"),
 * )
 */
class SearchPopupBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Views storage service.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $viewsStorage;

  /**
   * Views executable factory service.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewsExecutableFactory;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * View object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * Creates a Shop Exposed Search instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorage $views_storage
   *   The Views storage service.
   * @param \Drupal\views\ViewExecutableFactory $views_executable_factory
   *   The Views executable factory service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigEntityStorage $views_storage, ViewExecutableFactory $views_executable_factory, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->viewsStorage = $views_storage;
    $this->viewsExecutableFactory = $views_executable_factory;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['view_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Shop counter view'),
      '#default_value' => $this->configuration['view_id'],
      '#options' => $this->getApplicableViews(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['view_id'] = $form_state->getValue('view_id');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#theme' => 'cpl_search_popup_block',
      '#items_count' => $this->getViewItemsCount(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!($view = $this->getView())) {
      return parent::getCacheTags();
    }
    $parent_cache_tags = parent::getCacheTags();
    $view_cache_tags = CacheableMetadata::createFromRenderArray($view->buildRenderable())
      ->getCacheTags();
    return Cache::mergeTags($parent_cache_tags, $view_cache_tags);
  }

  /**
   * Helper function for getting a Shop view.
   *
   * @return \Drupal\views\ViewExecutable|false
   *   View executable object or FALSE
   */
  protected function getView() {
    if (!isset($this->view)) {
      if (!($view_storage = $this->viewsStorage->load($this->configuration['view_id']))) {
        return $this->view = FALSE;
      }
      $this->view = $this->viewsExecutableFactory->get($view_storage);
      if (!$this->view->setDisplay('results_counter')) {
        return $this->view = FALSE;
      }

      // Init handlers.
      $this->view->preExecute();
    }

    return $this->view;
  }

  /**
   * Get view items count.
   *
   * @return int
   *   Search view items count.
   */
  protected function getViewItemsCount() {
    if (!($view = $this->getView())) {
      return 0;
    }

    $view->get_total_rows = TRUE;
    $view->execute();
    return $view->total_rows;
  }

  /**
   * Get list of views to be used for a counter.
   */
  protected function getApplicableViews() {
    $ids = $this->viewsStorage->getQuery()
      ->condition('status', TRUE)
      ->execute();

    /** @var \Drupal\views\ViewEntityInterface[] $views */
    $views = $this->viewsStorage->loadMultiple($ids);
    $views = array_filter($views, function (ViewEntityInterface $view) {
      return (bool) @$view->getDisplay('results_counter');
    });

    $list = [];
    foreach ($views as $view) {
      $list[$view->id()] = $view->label();
    }

    return $list;
  }

}
