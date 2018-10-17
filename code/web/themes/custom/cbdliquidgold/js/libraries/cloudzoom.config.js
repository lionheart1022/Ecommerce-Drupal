'use strict';

(function($, Drupal) {
  Drupal.behaviors.cpl_base_commerce_cloudzoom = {
    attach: function(context) {
      var $productImage = $('.product-image', context);

      $productImage.once('image-zoom').each(function() {
        var options = {
          maxMagnification: 4,
          zoomPosition: 'inside',
          disableOnScreenWidth: 640,
          animationTime: 300
        };
        $.extend($.fn.CloudZoom.defaults, options);

        CloudZoom.quickStart();

        var $this = $(this);
        $this
          .find('.cloudzoom-gallery')
          .eq(0)
          .addClass('cloudzoom-gallery-active');
      });
    }
  };
})(jQuery, Drupal);
