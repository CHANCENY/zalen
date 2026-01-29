(function ($, Drupal) {
  'use strict';

  // Define the function
  function removeDuplicateOptions() {

    // let selectField = jQuery('[id^="edit-field-prijs-eenheid-"][id$="-pattern"]').filter(function () {
    let selectField = jQuery('[name*=field_prijs_eenheid][name*=pattern]').filter(function () {
      // Get the ID of the element
      let id = jQuery(this).attr('id');
      // Extract the value of `{num}` from the ID
      let num = id.match(/edit-field-prijs-eenheid-(\d+)-pattern/);
      // Return true if `{num}` is found and matches your criteria
      return num !== null && parseInt(num[1]) >= 0; // Modify the condition as needed
    });
    let valArr = [];
    selectField.each(function () {
      // Get the value of the selected option in selectField
      let optionsList = jQuery(this).find('option');
      let selectedOptionValue = jQuery(this).val();
      valArr.push(selectedOptionValue);

      // Call hide and show fields function
      fieldsHideShowHandler(valArr);
      jQuery.each(valArr, function (index, value) {
        if (index < valArr.length - 1) {
          optionsList.filter(function () {
            // Add conditions for 'i_person' and 'services' here
            return jQuery(this).val() === value && jQuery(this).val() !== 'i_person' && jQuery(this).val() !== 'services';
          }).remove();
        }
      });
    });
  }

  function fieldsHideShowHandler(list) {
    Object.keys(list).forEach(key => {
      const value = list[key];
      let hideElements = jQuery('.form-item-field-prijs-eenheid-' + key + '-enumerate-special-offer,.interval-date-' + key + '-begin,.interval-date-' + key + '-end,.date-range-' + key + '-type,.date-range-' + key + '-start,.date-range-' + key + '-end,.form-item-field-prijs-eenheid-' + key + '-enumerate-services-images,.form-item-field-prijs-eenheid-' + key + '-enumerate-service-description,.form-item-field-prijs-eenheid-' + key + '-enumerate-services,.form-item-field-prijs-eenheid-' + key + '-enumerate-require,.form-item-field-prijs-eenheid-' + key + '-enumerate-services-minimum-order, .form-item-field-prijs-eenheid-'+ key +'-enumerate-person-images, .form-item-field-prijs-eenheid-'+ key +'-enumerate-person-description, .form-item-field-prijs-eenheid-'+ key +'-enumerate-person-label, .form-item-field-prijs-eenheid-'+ key +'-enumerate-is-optional, .form-item-field-prijs-eenheid-'+ key +'-enumerate-child-friendly');
      hideElements.hide();

      if (value === 'interval') {
        jQuery('.form-item-field-prijs-eenheid-' + key + '-enumerate-special-offer,.interval-date-' + key + '-begin,.interval-date-' + key + '-end').show();
      }
      else if (value === 'rang_dat') {
        jQuery('.date-range-' + key + '-type,.date-range-' + key + '-start,.date-range-' + key + '-end').show();
      }
      else if (value === 'i_person') {
        jQuery('.form-item-field-prijs-eenheid-'+ key +'-enumerate-person-images, .form-item-field-prijs-eenheid-'+ key +'-enumerate-person-description, .form-item-field-prijs-eenheid-'+ key +'-enumerate-person-label, .form-item-field-prijs-eenheid-'+ key +'-enumerate-is-optional, .form-item-field-prijs-eenheid-'+ key +'-enumerate-child-friendly').show();
      }
      else if (value === 'services') {
        jQuery('.form-item-field-prijs-eenheid-' + key + '-enumerate-services-images,.form-item-field-prijs-eenheid-' + key + '-enumerate-service-description,.form-item-field-prijs-eenheid-' + key + '-enumerate-services,.form-item-field-prijs-eenheid-' + key + '-enumerate-require,.form-item-field-prijs-eenheid-' + key + '-enumerate-services-minimum-order').show();
      }
    });

    // prevent page scroll on ajax complete
    var scrollPosition;
    function storeScrollPosition() {
      scrollPosition = jQuery(window).scrollTop();
    }
    function restoreScrollPosition() {
      $(window).scrollTop(scrollPosition);
    }
    jQuery(document).ajaxStart(function () {
      storeScrollPosition();
    });
    jQuery(document).ajaxComplete(function () {
      restoreScrollPosition();
    });
  }

  function removeMinimumOrderPriceRule() {

    let ruleElement = jQuery('[name*=field_regels][name*=rule_type]').filter(function () {
      let id = jQuery(this).attr('id');
      // Extract the value of `{num}` from the ID
      let num = id.match(/edit-field-regels-(\d+)-rule-type/);
      // Return true if `{num}` is found and matches your criteria
      return num !== null && parseInt(num[1]) >= 0; // Modify the condition as needed
    });
    let priceRuleArr = [];
    ruleElement.each(function () {
      // Get the value of the selected option in ruleElement
      let optionsList = jQuery(this).find('option');
      let selectedOptionValue = jQuery(this).val();

      // priceRuleArr.push(selectedOptionValue);
      priceRuleArr.push(priceRuleArr.includes('minprice') ? 'if_large' : selectedOptionValue);

      priceHideShowHandler(priceRuleArr);
      // Call hide and show fields function
      jQuery.each(priceRuleArr, function (index, value) {
        if (index < priceRuleArr.length - 1) {
          optionsList.filter(function () {
            return jQuery(this).val() === value && jQuery(this).val() !== 'if_large';
          }).remove();
        }
      });
    });
  }
  function priceHideShowHandler(list) {
    Object.keys(list).forEach(key => {
      const value = list[key];
      if (value === 'minprice') {
        jQuery('.form-item-field-regels-' + key + '-subrule-datarule-price').hide();
        // jQuery('.form-item-field-regels-'+ key +'-subrule-datarule-span-time').hide();
        jQuery("[id*='edit-field-regels-" + key + "-subrule-pattern-tariff'] option").each(function () {
          if (this.value === 'days' || this.value === 'hours') {
            jQuery(this).remove();
          }
        });
      } else {
        jQuery('.form-item-field-regels-' + key + '-subrule-datarule-price, .form-item-field-regels-' + key + '-subrule-pattern-tariff').show();
        jQuery("[id*='edit-field-regels-" + key + "-subrule-pattern-tariff'] option").each(function () {
          if (this.value === 'per_hour' || this.value === 'inan_day') {
            jQuery(this).remove();
          }
        });
      }
    });

    // prevent page scroll on ajax complete
    var scrollPosition;
    function storeScrollPosition() {
      scrollPosition = jQuery(window).scrollTop();
    }
    function restoreScrollPosition() {
      $(window).scrollTop(scrollPosition);
    }
    jQuery(document).ajaxStart(function () {
      storeScrollPosition();
    });
    jQuery(document).ajaxComplete(function () {
      restoreScrollPosition();
    });
  }


  function initTariffChangeListeners() {
    // const selector = '[id^="edit-field-regels-"][id$="-subrule-pattern-tariff"]';
    const selector = '[name^="field_regels["][name$="[subrule][pattern_tariff]"]';
    const elements = document.querySelectorAll(selector);
    const lastElement = elements[elements.length - 1];
    const lastMatch = lastElement.name.match(/field_regels\[(\d+)\]\[subrule\]\[pattern_tariff\]/);
    const elementId = lastMatch[1];
    const input = document.querySelector('.form-item-field-regels-'+ elementId +'-subrule-pattern-tariff select');
    if(input.value == 'i_person') {
      jQuery(`.form-item-field-regels-${elementId}-subrule-datarule-span-time label`).text('Minimum Person');
    }

    elements.forEach((element) => {
      element.addEventListener('change', () => {
        const selectedValue = element.value;
        // Extract the rule number using regex
        const match = element.id.match(/edit-field-regels-(\d+)-subrule-pattern-tariff/);
        if (match && selectedValue == 'i_person') {
          const ruleNumber = match[1];
          jQuery(`.form-item-field-regels-${ruleNumber}-subrule-datarule-span-time label`).text('Minimum Person');
        } else {
          const ruleNumber = match[1];
          jQuery(`.form-item-field-regels-${ruleNumber}-subrule-datarule-span-time label`).text('Minimum Time');
        }
      });
    });
  }

  // Run the function on DOMContentLoaded event
  document.addEventListener('DOMContentLoaded', function () {
    removeDuplicateOptions();
    removeMinimumOrderPriceRule();
    initTariffChangeListeners();
  }, false);

  // Run the function on AJAX complete
  jQuery(document).ajaxComplete(function () {
    removeDuplicateOptions();
    removeMinimumOrderPriceRule();
    initTariffChangeListeners();
  });

})(jQuery, Drupal);

