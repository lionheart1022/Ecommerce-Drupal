(function ($, window, Drupal) {

  /**
   * Trigger cart AJAX update on quantity change.
   */
  Drupal.behaviors.cplCartAjaxQuantityUpdate = {
    attach: function (context) {
      $('.cpl-commerce-cart-edit-quantity', context)
        .once('cplCartAjaxQuantityUpdate')
        .each(function() {
          var $input = $(this);
          $input
            .change(Drupal.debounce(function() {
              $input
                .closest('form')
                .find('.form-actions .cpl-commerce-cart-edit-submit')
                .trigger('mousedown');
            }, 250));
        });
    }
  };

})(jQuery, window, Drupal);
