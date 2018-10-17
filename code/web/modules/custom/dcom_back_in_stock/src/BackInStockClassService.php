<?php

namespace Drupal\dcom_back_in_stock;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Service class for dcom_back_in_stock module.
 */
class BackInStockClassService {

  const AVAIL_GLOBAL = 'GLOBAL';
  const AVAIL_ODOO = 'ODOO';
  const AVAIL_FORCE_AVAIL = 'AVAIL';
  const AVAIL_FORCE_NOTAVAIL = 'NOTAVAIL';

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme initialization.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * BackInStockClassService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config factory for back in stock class service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactory $configFactory, MailManagerInterface $mail_manager, Renderer $renderer, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization) {
    $this->config = $configFactory->get('dcom_back_in_stock.config');
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
  }

  /**
   * Check if user is waiting for specific product.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariation $product_variation
   *   ProductVariation object.
   *
   * @return int|false
   *   Return stock_notification entity id or false.
   */
  public function checkUserWaitingForProduct(ProductVariation $product_variation) {
    if (\Drupal::currentUser()->isAnonymous()) {
      return $this->getSubscribersProduct($product_variation, NULL, \Drupal::service('session_manager')->getId());
    }
    else {
      return $this->getSubscribersProduct($product_variation, \Drupal::currentUser()->id());
    }
  }

  /**
   * Get subscribers of product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariation $product_variation
   *   Product variation.
   * @param int|null $user_id
   *   User ID or null.
   * @param int|null $session_id
   *   Session ID or null.
   *
   * @return int|false
   *   Return stock_notification entity id or false.
   */
  public function getSubscribersProduct(ProductVariation $product_variation, $user_id = NULL, $session_id = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::service('entity.query')->get('stock_notification');

    if (!isset($user_id) && !isset($session_id)) {
      throw new \InvalidArgumentException();
    }

    $group = $query->orConditionGroup();
    if (isset($user_id)) {
      $group->condition('user_id', $user_id);
    }
    if (isset($session_id)) {
      $group->condition('session_id', $session_id);
    }

    $notice_entity_id = $query
      ->condition('product_variation', $product_variation->id())
      ->condition('email_sent', 0)
      ->condition($group)
      ->execute();

    return $notice_entity_id ? $notice_entity_id : FALSE;
  }

  /**
   * Check product variation stock policy.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariation $product_variation
   *   Product variation.
   *
   * @return string
   *   One of AVAIL_ODOO, AVAIL_FORCE_AVAIL or AVAIL_FORCE_NOTAVAIL.
   */
  public function getStockPolicy(ProductVariation $product_variation) {
    if (!empty($product_variation->field_force_availability->value)
      && $product_variation->field_force_availability->value != static::AVAIL_GLOBAL) {
      // Return product variant policy if it doesn't inherit product.
      return $product_variation->field_force_availability->value;
    }

    $product = $product_variation->getProduct();
    if (!empty($product->field_force_availability->value)
      && $product->field_force_availability->value != static::AVAIL_GLOBAL) {
      // Return product policy if it doesn't inherit global.
      return $product->field_force_availability->value;
    }

    $global_setting = $this->config->get('inventory_policy');
    if ($global_setting) {
      return $global_setting;
    }

    return static::AVAIL_ODOO;
  }

  /**
   * Send notification email.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *   Commerce product.
   * @param string $email
   *   Email address.
   * @param string $mail_theme
   *   Theme name for email body.
   */
  public function sendBackInStockEmail(Product $product, $email, $mail_theme) {
    /** @var \Drupal\File\Entity\File $product_image */
    $product_image = $product->get('field_product_image')->entity;
    $product_image_uri = $product_image->getFileUri();

    $params = [
      'headers' => [
        'Content-Type' => 'text/html; charset=UTF-8;',
        'Content-Transfer-Encoding' => '8Bit',
      ],
      'subject' => t('Stock @product notify', ['@product' => $product->getTitle()]),
      'product' => $product,
      'product_image' => $product_image_uri,
    ];

    $build = [
      '#theme' => 'dcom_back_in_stock_email',
      '#product_entity' => $product,
    ];
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    // @see MailsystemManager::mail()
    // Switch the theme to the configured mail theme.
    $current_active_theme = $this->themeManager->getActiveTheme();
    if ($mail_theme && $mail_theme != $current_active_theme->getName()) {
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($mail_theme));
    }

    try {
      $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($build) {
        return $this->renderer->render($build);
      });
      $this->mailManager->mail('dcom_back_in_stock', 'email', $email, $langcode, $params);
    }
    finally {
      // Revert the active theme, this is done inside a finally block so it is
      // executed even if an exception is thrown during sending a mail.
      if ($mail_theme != $current_active_theme->getName()) {
        $this->themeManager->setActiveTheme($current_active_theme);
      }
    }
  }

}
