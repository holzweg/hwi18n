$(document).ready(function() {
    $('span.hwi18n').hwinlinetranslate();
});

(function($) {
    $.fn.hwinlinetranslate = function(options) {
        var opts = $.extend({}, $.fn.hwinlinetranslate.defaults, options);
        var selector = this;

        // Create indicator
        $("body").append("<div id='hwi18n-indicator'></div>");
        var $indicator = $("#hwi18n-indicator");


        var keyStr = "ABCDEFGHIJKLMNOP" +
                     "QRSTUVWXYZabcdef" +
                     "ghijklmnopqrstuv" +
                     "wxyz0123456789+/" +
                     "=";

        function encode64(input) {
           input = escape(input);
           var output = "";
           var chr1, chr2, chr3 = "";
           var enc1, enc2, enc3, enc4 = "";
           var i = 0;

           do {
              chr1 = input.charCodeAt(i++);
              chr2 = input.charCodeAt(i++);
              chr3 = input.charCodeAt(i++);

              enc1 = chr1 >> 2;
              enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
              enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
              enc4 = chr3 & 63;

              if (isNaN(chr2)) {
                 enc3 = enc4 = 64;
              } else if (isNaN(chr3)) {
                 enc4 = 64;
              }

              output = output +
                 keyStr.charAt(enc1) +
                 keyStr.charAt(enc2) +
                 keyStr.charAt(enc3) +
                 keyStr.charAt(enc4);
              chr1 = chr2 = chr3 = "";
              enc1 = enc2 = enc3 = enc4 = "";
           } while (i < input.length);

           return output;
        }

        function decode64(input) {
           var output = "";
           var chr1, chr2, chr3 = "";
           var enc1, enc2, enc3, enc4 = "";
           var i = 0;

           // remove all characters that are not A-Z, a-z, 0-9, +, /, or =
           var base64test = /[^A-Za-z0-9\+\/\=]/g;
           if (base64test.exec(input)) {
              alert("There were invalid base64 characters in the input text.\n" +
                    "Valid base64 characters are A-Z, a-z, 0-9, '+', '/',and '='\n" +
                    "Expect errors in decoding.");
           }
           input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

           do {
              enc1 = keyStr.indexOf(input.charAt(i++));
              enc2 = keyStr.indexOf(input.charAt(i++));
              enc3 = keyStr.indexOf(input.charAt(i++));
              enc4 = keyStr.indexOf(input.charAt(i++));

              chr1 = (enc1 << 2) | (enc2 >> 4);
              chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
              chr3 = ((enc3 & 3) << 6) | enc4;

              output = output + String.fromCharCode(chr1);

              if (enc3 != 64) {
                 output = output + String.fromCharCode(chr2);
              }
              if (enc4 != 64) {
                 output = output + String.fromCharCode(chr3);
              }

              chr1 = chr2 = chr3 = "";
              enc1 = enc2 = enc3 = enc4 = "";

           } while (i < input.length);

           return unescape(output);
        }

        function saveTranslation($this) {
            $indicator.data("saving", true);
            $this.blur();
            selector.removeAttr("contentEditable");

            // save the contents
            var $context = $this.data("context");
            var $source = decode64($this.data("source"));
            var $locale = $this.data("locale");
            var $translation = $this.html();
            $.ajax({
                type: 'POST',
                url: '/de/ezjscore/call/hwi18n::translate',
                dataType: 'json',
                data: {
                    context: $context,
                    source: $source,
                    locale: $locale,
                    translation: $translation
                },
                success: function(data, statusText, xhr, $form) {
                    if(data.status == 200) {
                        $indicator.css({
                            "box-shadow": "1px 1px 2px #85DE81",
                            "background": "none repeat scroll 0 0 #6ACC44"
                        }).fadeOut(500, function() {
                            $indicator.data("saving", false);
                            selector.attr("contentEditable", "true");
                        });
                        $this.data('dirty', false);
                        $this.html(data.translation);
                    }
                }
            });
        }

        function preventClick() {
            return false;
        }

        this.live("blur", function() {
            var $this = $(this);
            $indicator.data('editing', false);
            if($indicator.data('saving') !== true) {
                $indicator.hide();
            }
        }).live('focus', function() {
            var $this = $(this);
            $this.data('before', $this.html());
            $indicator.data('editing', true);

            // show indicator
            if($this.data("dirty") === true) {
                $indicator.css({
                    "left": $this.offset().left - 14,
                    "top": $this.offset().top + $this.height() / 2 - 5,
                    "box-shadow": "1px 1px 2px #FF8787",
                    "background": "none repeat scroll 0 0 #FF0000"
                });
            }else{
                $indicator.css({
                    "left": $this.offset().left - 14,
                    "top": $this.offset().top + $this.height() / 2 - 5,
                    "box-shadow": "1px 1px 2px #C8C8C8",
                    "background": "#CCCCCC"
                });
            }
            $indicator.show();
            return $this;
        }).live("keypress", function(event) {
            var $this = $(this);
            // save on return press
            if ( event.which == 13 ) {
                event.preventDefault();
                if($this.data("dirty") === true) {
                    saveTranslation($this);
                }
                return false;
            }
        }).live("keyup", function(event) {
            var $this = $(this);
            // mark dirty on change
            if ($this.data('before') !== $this.html()) {
                $this.data('before', $this.html());
                $this.data('dirty', true);
                $indicator.css({
                    "box-shadow": "1px 1px 2px #FF8787",
                    "background": "none repeat scroll 0 0 #FF0000"
                });
            }
        }).hover( function() {
            var $this = $(this);

            // cancel if currently editing
            if($indicator.data("editing") === true || $indicator.data("saving") === true) {
                return false;
            }

            // unset parent link tag
            $this.closest("a").bind("click", preventClick);

            // show indicator
            if($this.data("dirty") === true) {
                $indicator.css({
                    "left": $this.offset().left - 14,
                    "top": $this.offset().top + $this.height() / 2 - 5,
                    "box-shadow": "1px 1px 2px #FF8787",
                    "background": "none repeat scroll 0 0 #FF0000"
                });
            }else{
                $indicator.css({
                    "left": $this.offset().left - 14,
                    "top": $this.offset().top + $this.height() / 2 - 5,
                    "box-shadow": "1px 1px 2px #C8C8C8",
                    "background": "#CCCCCC"
                });
            }

            $indicator.show();

        }, function() {
            var $this = $(this);
            if($indicator.data("editing") !== true && $indicator.data("saving") !== true) {
                $indicator.hide();
            }
        });

        return true;

    };

    $.fn.hwinlinetranslate.defaults = {};
})(jQuery);