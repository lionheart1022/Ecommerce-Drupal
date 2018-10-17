(function ($, window, Drupal, drupalSettings) {

  /**
   * Updates hidden NMI fields with the form data.
   *
   * @param {object} $paymentOffSiteForm
   *   The payment off site form.
   */
  function updateNmiFields($paymentOffSiteForm) {
    var month, year;

    $paymentOffSiteForm.find('input.cc-number, input.security-code')
      .each(function () {
        var val = $(this).val();
        if ($(this).hasClass('cc-number')) {
          $paymentOffSiteForm.find('input[name="ccnumber"]').val(val);
        }
        if ($(this).hasClass('security-code')) {
          $paymentOffSiteForm.find('input[name="billing-cvv"]').val(val);
        }
      });

    $paymentOffSiteForm.find('.credit-card-form__expiration select')
      .each(function () {
        if ($(this).hasClass('month')) {
          month = $(this).val();
        }
        if ($(this).hasClass('year')) {
          year = $(this).val();
        }
      });

    // If the customer reuse the payment method, the fields card number,
    // expiration might be not available.
    if (month && year) {
      var ccexp  = month + year.substr(-2);
      $paymentOffSiteForm.find('input[name="ccexp"]').val(ccexp);
    }
  }

  /**
   * Updates NMI fields.
   *
   * NMI requires to receive card expiration month & year in one field. But we
   * have two fields. So merge it and send to NMI as one field. Update ccnumber
   * and billing-cvv with the form data, as well.
   *
   * @type {{attach: Drupal.behaviors.paymentOffSiteForm.attach}}
   */
  Drupal.behaviors.paymentOffSiteForm = {
    attach: function (context) {
      var $checkoutForm = $('.commerce-checkout-flow, .commerce-nmi-payment-add-form', context);

      $checkoutForm.once('paymentOffSiteForm').each(function() {
        updateNmiFields($checkoutForm);

        $checkoutForm.find('input.cc-number, input.security-code')
          .on('keyup', function () {
            updateNmiFields($checkoutForm);
          })
          .on('change', function () {
            updateNmiFields($checkoutForm);
          });
        $checkoutForm.find('.credit-card-form__expiration select')
          .on('keyup', function () {
            updateNmiFields($checkoutForm);
          })
          .on('change', function () {
            updateNmiFields($checkoutForm);
          });
      });
    }
  };

})(jQuery, window, Drupal, drupalSettings);
