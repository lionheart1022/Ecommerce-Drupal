/**
 * @file
 * Defines Javascript behaviors for the commerce cart module.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.addressSuggestionsAutocomplete = {
    attach: function (context) {
      $(context)
        .find('.address-suggestion-autocomplete')
        .once('address-suggestion-autocomplete')
        .each(function() {
          var $this = $(this);
          var matchName = new RegExp("(.*)\\[(\\w+)\\]$");

          // A hack to force disable browser's autocomplete, like Chrome's
          // Autofill.
          // @see https://developer.mozilla.org/en-US/docs/Web/Security/Securing_your_site/Turning_off_form_autocompletion
          $this.prop('autocomplete', 'nope');

          $this.autocomplete('option', 'select', function (event, ui) {
            event.preventDefault();

            // Fill in all fields.
            $.each(ui.item.address, function (field, value) {
              var fieldName = $this.prop('name').replace(matchName, '$1[' + field + ']');
              var $field = $('[name="' + fieldName + '"]');
              $field.val(value);

              // Trigger input event.
              var $target_input = $field.not('.address-suggestion-autocomplete');
              if ($target_input[0]) {
                $target_input[0].dispatchEvent(new Event('input'));
              }
            });
          });

          $this.data('ui-autocomplete')._renderItem = function(ul, item) {
            var html = $('<span class="address-suggestion-label"></span>')
              .html(item.label)[0].outerHTML;

            if (item.secondary_label) {
              html += ' ';
              html += $('<span class="address-suggestion-secondary-label"></span>')
                .html(item.secondary_label)[0].outerHTML;
            }

            return $('<li>')
              .append($('<a>').html(html))
              .appendTo(ul);
          };
        });
    }
  };
})(jQuery, Drupal, drupalSettings);
