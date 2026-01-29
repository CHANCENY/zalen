(function (Drupal) {
  Drupal.behaviors.awturSearchDatePicker = {
    attach: function (context, settings) {
      var startDateInput = context.querySelector("input[data-drupal-selector='edit-field-date-booking-start-value']");
      var endDateInput = context.querySelector("input[data-drupal-selector='edit-field-date-booking-end-value']");

      if (startDateInput) {
        flatpickr(startDateInput, {
          enableTime: true,
          dateFormat: "Y-m-d\\TH:i",
          altInput: true,
          altFormat: "d-m-Y H:i",
          time_24hr: true,
          minuteIncrement: 15,
          onReady: function(selectedDates, dateStr, instance) {
            instance.altInput.setAttribute('placeholder', 'DD-MM-YYYY HH:MM');
            instance.altInput.classList.add('input-search-filter-start', 'input-search-filter');
          }
        });
      }

      if (endDateInput) {
        flatpickr(endDateInput, {
          enableTime: true,
          dateFormat: "Y-m-d\\TH:i",
          altInput: true,
          altFormat: "d-m-Y H:i",
          time_24hr: true,
          minuteIncrement: 15,
          onReady: function(selectedDates, dateStr, instance) {
            instance.altInput.setAttribute('placeholder', 'DD-MM-YYYY HH:MM');
            instance.altInput.classList.add('input-search-filter-end', 'input-search-filter');
          }
        });
      }
    }
  };

  Drupal.behaviors.awturCloseDatesPicker = {
    attach: function (context, settings) {
      var closeDatesInputs = context.querySelectorAll("#edit-field-sluit-periode-wrapper input[type='date']");
      if (closeDatesInputs.length > 0) {
        closeDatesInputs.forEach(function (element) {
          flatpickr(element, {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d-m-Y",
            onReady: function(selectedDates, dateStr, instance) {
              instance.altInput.setAttribute('placeholder', 'DD-MM-YYYY');
              instance.altInput.classList.add('input-close-period');
            }
          });
        });
      }
    },
  };
})(Drupal);
