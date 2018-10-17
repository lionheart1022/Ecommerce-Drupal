/**
 * @file
 * Zendesk JS code.
 */

(function ($, window, Drupal) {

  /**
   * Attaches Zendesk JS.
   */
  Drupal.behaviors.ZZZDiamondCommerceZendesk = {
    attach: function () {
      $('body').once('dcom-zendesk-init').each(function () {
        // Delay loading Zendesk.
        setTimeout(function() {
          window.zEmbed || function (e, t) {
            var n, o, d, i, s, a = [], r = document.createElement("iframe");
            window.zEmbed = function () {
              a.push(arguments)
            }, window.zE = window.zE || window.zEmbed, r.src = "javascript:false", r.title = "", r.role = "presentation", (r.frameElement || r).style.cssText = "display: none", d = document.getElementsByTagName("script"), d = d[d.length - 1], d.parentNode.insertBefore(r, d), i = r.contentWindow, s = i.document;
            try {
              o = s
            } catch (e) {
              n = document.domain, r.src = 'javascript:var d=document.open();d.domain="' + n + '";void(0);', o = s
            }
            o.open()._l = function () {
              var e = this.createElement("script");
              n && (this.domain = n), e.id = "js-iframe-async", e.src = "https://assets.zendesk.com/embeddable_framework/main.js", this.t = +new Date, this.zendeskHost = "diamondcbd.zendesk.com", this.zEQueue = a, this.body.appendChild(e)
            }, o.write('<body onload="document._l();">'), o.close();
          }();
        }, 2000);


        // Autopopup Zendesk chat.
        /*
        function autoPopup(element, callback) {
          var check = window.setInterval(function() {
            if ($(element).length) {
              clearInterval(check);
              callback();
            }
          }, 200);
        }

        autoPopup('.zEWidget-launcher--active', function () {
          if ($zopim !== undefined && !window.localStorage.getItem('zendeskChat')) {
            $zopim.livechat.window.show();
            window.localStorage.setItem('zendeskChat', 'displayed');
          }
        });
        */
      });
    }
  };

})(jQuery, window, Drupal);
