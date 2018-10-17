/**
 * @file
 * Privy JS code.
 */

(function ($, window, Drupal) {

  /**
   * Attaches Privy JS.
   */
  Drupal.behaviors.ZZZDiamondCommercePrivy = {
    attach: function () {
      $('body').once('dcom-privy-init').each(function () {
        window._d_site = window._d_site || '4254E2382F4313936690B827';
        // Delay loading Privy.
        setTimeout(function() {
          (function(p, r, i, v, y) {
            p[i] = p[i] || function() { (p[i].q = p[i].q || []).push(arguments) };
            v = r.createElement('script'); v.async = 1; v.src = '//widget.privy.com/assets/widget.js';
            y = r.getElementsByTagName('script')[0]; y.parentNode.insertBefore(v, y);
          })(window, document, 'Privy');
        }, 2000);
      });
    }
  };

})(jQuery, window, Drupal);
