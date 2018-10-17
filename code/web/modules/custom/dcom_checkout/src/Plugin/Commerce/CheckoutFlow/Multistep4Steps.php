<?php

namespace Drupal\dcom_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutFlow\Multistep4Steps as CplMultistep4Steps;

/**
 * Provides the default multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "dcom_checkout_4step",
 *   label = "4 step checkout - deprecated, for compatibility only",
 * )
 */
class Multistep4Steps extends CplMultistep4Steps {}
