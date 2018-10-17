<?php

namespace Drupal\dcom_shop\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Shop Exposed Search' block.
 *
 * @Block(
 *   id = "exposed_search",
 *   admin_label = @Translation("Diamond Commerce - Shop Search")
 * )
 */
class ShopExposedSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'sort' => TRUE,
      'search' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!($view = $this->getView())) {
      return [];
    }

    $form_state = (new FormState())
      ->setStorage([
        'view' => $view,
        'display' => &$view->display_handler->display,
      ])
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();

    // TODO: Remove hardcoded path.
    $form = $this->formBuilder->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);
    $form['#action'] = '/collections/all';

    if (empty($this->configuration['sort'])) {
      unset($form['sort_by']);
    }
    if (empty($this->configuration['search'])) {
      unset($form['search']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'url.query_args:search']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!($view = $this->getView())) {
      return parent::getCacheTags();
    }
    return Cache::mergeTags(parent::getCacheTags(), $view->getCacheTags());
  }

  /**
   * Helper function for getting a Shop view.
   *
   * @return \Drupal\views\ViewExecutable|false
   *   View executable object or FALSE
   */
  protected function getView() {
    if (!isset($this->view)) {
      if (!($view_storage = $this->viewsStorage->load('diamond_commerce_shop'))) {
        return $this->view = FALSE;
      }
      $this->view = $this->viewsExecutableFactory->get($view_storage);
      if (!$this->view->setDisplay('diamond_commerce_shop_block')) {
        return $this->view = FALSE;
      }

      // Init handlers.
      $this->view->preExecute();
    }

    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['sort'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display "Sort by" sort form.'),
      '#default_value' => (int) $this->configuration['sort'],
    ];

    $form['search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display "Search" filter.'),
      '#default_value' => (int) $this->configuration['search'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['sort'] = $form_state->getValue('sort');
    $this->configuration['search'] = $form_state->getValue('search');
  }

}
