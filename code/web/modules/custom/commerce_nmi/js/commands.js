(function ($, window, Drupal) {

  /**
   * Ajax command to removing all events from the element.
   */
  Drupal.AjaxCommands.prototype.offEvents = function(ajax, response, status){
    var $element = $('input[data-drupal-selector="' + response['drupal_selector'] + '"]');
    $element.off();
  }

})(jQuery, window, Drupal);
