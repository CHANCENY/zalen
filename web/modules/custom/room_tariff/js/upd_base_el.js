; console.log('Hi auto updating of basic tariff elements from user input');

(function ($, Drupal) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    for (let item_fields of new Map([
      ['inan_day', 'dagprijs'],
      ['per_hour', 'uurprijs'],
      ['i_person', 'prijs-per-persoon'],
    ]).entries()) {

      const search_fields = item_fields[0];
      const tracked_fields = item_fields[1];
      let field_name = drupalSettings.room_tariff.name_definition.replace(/ |_/g, '-');
      // looking for price input fields and adding a handler.
      document.querySelector('input#edit-field-' + tracked_fields + '-0-value').addEventListener("input", function () {

        let cost = this.value;
        // look for the tariff fields and check the values of the item type.
        Array.prototype.some.call(
          document.querySelector('div#edit-' + field_name + '-wrapper').querySelectorAll('.tariff-wrapper select'),
          function (el, key) {

            // if we find a type match, we look for the price input and copy the value.
            if (el.value === search_fields) {
              let index = el.id;
              index = index.slice(index.indexOf('edit-' + field_name + '-') + field_name.length + 6, index.indexOf('-pattern'));
              let price = el;
              for (let i = 0; (i > 10 || price.className.indexOf('wrapper') === -1); i++) {
                price = price.parentElement;
              };
              price = price.parentElement.querySelector('.form-item-' + field_name + '-' + index + '-enumerate-price input');
              if (price.id.indexOf(index + '-enumerate-price') !== -1) {
                price.value = cost;
                return true;
              };
            };

          }
        );

      }, false);

    };

  }, false);

})(jQuery, Drupal);