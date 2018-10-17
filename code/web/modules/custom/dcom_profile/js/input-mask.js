(function ($, window, Drupal) {

  /**
   * Add mask to phone number input.
   */
  Drupal.behaviors['dcom_profile_telephoneInputMask'] = {
    attach: function (context) {
      function applyPhoneMask(country_code_input) {
        country_code_input.parents('.field--type-address.field--name-address').siblings('.field--type-telephone.field--name-field-phone-number')
          .each(function () {
            var phone_field = $(this);
            var maskWasFound = false;

            if (country_code_input.length > 0) {
              country_code = country_code_input.val();

              // Changes country code for United Kingdom (Great Britain).
              if (country_code == 'GB') country_code = 'UK';

              phoneInputmaskObject = new Inputmask({alias: 'phone'});
              $.each(phoneInputmaskObject.opts.phoneCodes, function (ndx, lmnt) {
                if (lmnt.cc.length > 0) {
                  if ($.isArray(lmnt.cc)) {
                    $.each(lmnt.cc, function (k, v) {
                      if (v == country_code) {
                        maskWasFound = true;
                        phone_field.find('input').inputmask(lmnt.mask);
                        return false;
                      }
                    });
                  }
                  else if (lmnt.cc == country_code) {
                    maskWasFound = true;
                    phone_field.find('input').inputmask(lmnt.mask);
                    return false;
                  }
                }
              });

              if (!maskWasFound) {
                phone_field.find('input').inputmask('remove');
              }
            }
          });
      };

      $('[name*="[address][country_code]"]', context)
        .once('dcom-profile-telephone-input-mask')
        .each(function () {
          country_code_input = $(this);

          applyPhoneMask(country_code_input);
          country_code_input.on('change', function () {
            applyPhoneMask(country_code_input);
          });
        });
    }
  };

})(jQuery, window, Drupal);
