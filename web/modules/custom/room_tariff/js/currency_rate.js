;console.log('Hi currency rate');

(function (Drupal, $, once) {
    'use strict';

    Drupal.behaviors.currency_rate = {
      attach: function(context, settings) {

        let rate = settings.room_tariff;
        function exchange (cash, currency) {
          let base = rate.currency_rate['base'];
          let to = rate.field_config['currency'];
          let result;
          cash = cash*100;
          if (currency == to) {
            return Math.round(cash+Number.EPSILON)/100;
          } else if (currency == base) {
            result = cash * rate.currency_rate[to];
          } else if (to == base) {
            result = cash / rate.currency_rate[currency];
          } else if ((currency !== base) && (to !== base)) {
            result = cash * rate.currency_rate[to] / rate.currency_rate[currency];
          }
          return Math.round(result+Number.EPSILON)/100;
        };

        $('.currency-refresh-price').once('myJsCurrencyRateBehavior').each(
          function(){

            let wraper = this.parentElement.parentElement;
            let choose = wraper.querySelector('select.currency-refresh-rate');
            let cost = wraper.querySelector('span.currency-refresh-data');
            let cash = wraper.querySelector('input[type="text"].currency-refresh-price');

            this.addEventListener("input", function(){
              if (isNaN(parseFloat(this.value))) {
                cost.innerHTML = '?!...';
              } else if ((choose.value !== rate.field_config['currency']) && !isNaN(parseFloat(this.value))) {
                cost.innerHTML = parseFloat(this.value).toFixed(2) + ' ' + choose.value + ' ~ ' +
                  exchange(parseFloat(this.value), choose.value).toFixed(2) + ' ' + rate.field_config['currency'] + ': ' +
                  rate.field_config['provider'] + ', ' + rate.currency_rate['date'] + '.';
              } else {cost.innerHTML = parseFloat(this.value).toFixed(2) + ' ' + choose.value;};
            }, false);

            choose.addEventListener("change", function(){
              if (isNaN(parseFloat(cash.value))) {
                cost.innerHTML = '?!...';
              } else if ((choose.value !== rate.field_config['currency']) && !isNaN(parseFloat(cash.value))) {
                cost.innerHTML = parseFloat(cash.value).toFixed(2) + ' ' + choose.value + ' ~ ' +
                  exchange(parseFloat(cash.value), choose.value).toFixed(2) + ' ' + rate.field_config['currency'] + ': ' +
                  rate.field_config['provider'] + ', ' + rate.currency_rate['date'] + '.';
              } else {cost.innerHTML = parseFloat(cash.value).toFixed(2) + ' ' + choose.value;};
            }, false);

          }
        );

      },
    };

})(Drupal, jQuery, once);
