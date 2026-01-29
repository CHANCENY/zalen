(function ($, Drupal) {
  Drupal.behaviors.myModuleBehavior = {
    attach: function (context, settings) {
      // Ensure this only runs once per element
      var vip = $('input[data-drupal-selector="edit-roles-premium-zaal"]', context);
      var standaard = $('input[data-drupal-selector="edit-roles-zaal-eigenaar"]', context);
      var dynamicFields = $('#dynamic_fields_wrapper', context);

      // Function to toggle visibility based on vip checked status
      function toggleDynamicFields() {
        if (vip.is(':checked')) {
          dynamicFields.show();
        } else {
          dynamicFields.hide();
        }
      }

      if (standaard.length) {
        standaard.off('change.standaard').on('change.standaard', function () {
          if (this.checked) {
            vip.prop('checked', false);
          }
          toggleDynamicFields();
        });
      }

      if (vip.length) {
        vip.off('change.vip').on('change.vip', function () {
          if (this.checked) {
            standaard.prop('checked', false);
          }
          toggleDynamicFields();
        });
      }

      // Initial check
      toggleDynamicFields();

      // Remove extra fields
      var elements = document.querySelectorAll('[id^="standard-register-form"] [id^="edit-field-betaling-accepteren-via-mo-wrapper"]');
      if (elements.length >= 2) {
        elements[0].remove();
      }

      let removableField = document.querySelectorAll('[id^="standard-register-form"] [id^="edit-field-betaling-accepteren-via-mo"]');
      let partner = $('input[data-drupal-selector="edit-roles-zaal-eigenaar"]', context);
      if (partner.is(':checked')) {
        removableField.forEach(field => {
          field.remove();
        });
      }

      partner.off('click.partner').on('click.partner', function () {
        removableField.forEach(field => {
          field.remove();
        });
      });

      // Override Drupal's Ajax command
      Drupal.AjaxCommands.prototype.changed = function (ajax, response, status) {
        var elements = document.querySelectorAll('[id^="standard-register-form"] [id^="edit-field-betaling-accepteren-via-mo-wrapper"]');
        if (elements.length >= 2) {
          elements[0].remove();
        }

        // Set checkbox states from AJAX
        $('input[data-drupal-selector="edit-roles-premium-zaal"]').prop('checked', response.vipChecked);
        $('input[data-drupal-selector="edit-roles-zaal-eigenaar"]').prop('checked', response.standaardChecked);

        // Re-toggle visibility based on new state
        toggleDynamicFields();
      };
    }
  };
})(jQuery, Drupal);
