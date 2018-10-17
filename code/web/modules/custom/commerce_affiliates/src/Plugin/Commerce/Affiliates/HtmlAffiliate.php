<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HtmlAffiliate.
 *
 * @CommerceAffiliate(
 *   id = "custom_html_affiliate",
 *   label = @Translation("Custom HTML"),
 *   trackingType = "html",
 * )
 */
class HtmlAffiliate extends AffiliateBase implements ContainerFactoryPluginInterface {

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TokenEntityMapperInterface $token_entity_mapper,
    Token $token
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->tokenEntityMapper = $token_entity_mapper;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token.entity_mapper'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'html_tag' => '',
      'uri' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['html_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('HTML tag'),
      '#required' => TRUE,
      '#options' => [
        'img' => $this->t('Image'),
        'iframe' => $this->t('Iframe'),
      ],
      '#default_value' => $this->configuration['html_tag'],
    ];
    $form['uri'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $this->configuration['uri'],
      '#prefix' => $this->t("<p><b>Configure uri for embed html element.</b></p>"),
    ];
    $form['tokens'] = [
      ['#markup' => $this->t('Commerce Order entity tokens are replaced with their values.')],
      [
        '#theme' => 'token_tree_link',
        '#token_types' => [$this->tokenEntityMapper->getTokenTypeForEntityType('commerce_order')],
        '#show_restricted' => TRUE,
        '#global_types' => TRUE,
        '#show_nested' => FALSE,
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $this->configuration = [];
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['html_tag'] = $values['html_tag'];
      $this->configuration['uri'] = $values['uri'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function track(OrderInterface $order) {
    $attributes = [
      'src' => $this->token->replace($this->configuration['uri'], ['commerce_order' => $order]),
      'class' => [Html::cleanCssIdentifier($this->getPluginId())],
    ];
    $this->setAdditionalAttributes($attributes);
    return [
      '#type' => 'html_tag',
      '#tag' => $this->configuration['html_tag'],
      '#attributes' => $attributes,
    ];
  }

  /**
   * Add attributes that depend on the selected html tag.
   *
   * @param array $attributes
   *   HTML element attributes.
   */
  protected function setAdditionalAttributes(array &$attributes) {
    switch ($this->configuration['html_tag']) {
      case 'img':
        $attributes['style'][] = 'width:0;';
        $attributes['style'][] = 'height:0;';
        break;

      case 'iframe':
        $attributes['height'] = '1';
        $attributes['width'] = '1';
        $attributes['frameborder'] = '0';
        $attributes['scrolling'] = 'no';
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cancelTransaction(OrderInterface $order, $event_type) {
    // TODO: Implement cancelTransaction() method.
  }

}
