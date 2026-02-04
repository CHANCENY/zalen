(function (Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.DateTimeFlatPickr = {
    attach: function (context) {

      for (var name in drupalSettings.datetimeFlatPickr) {
        const flatpickr = once("datetime-flatpickr", "input[flatpickr-name='" + name + "']");
        flatpickr.forEach(function (item) {
          const { disable, minDate, maxDate, jumpToDate, disabledWeekDays, disabledDates, ...settings } = drupalSettings.datetimeFlatPickr[name].settings;
          let extraSettings = {};
          // Min/Max date as number will set the offset with days,
          // otherwise, use date.
          if (minDate) {
            extraSettings.minDate = !isNaN(minDate) ? new Date().fp_incr(minDate) : minDate;
          }
          if (maxDate) {
            extraSettings.maxDate = !isNaN(maxDate) ? new Date().fp_incr(maxDate) : maxDate;
          }

          // Disable dates if present.
          extraSettings.disable = disable ?? [];

          // Disable selected weekdays.
          extraSettings.disable.push(function(date) {
            return disabledWeekDays.includes(date.getDay());
          });

          // Disable dates.
          if (disabledDates) {
            extraSettings.disable.push(function(date) {
              const year = date.getFullYear();
              const month = String(date.getMonth() + 1).padStart(2, '0');
              const day = String(date.getDate()).padStart(2, '0');
              return disabledDates.includes(year + '-' + month + '-' + day);
            });
          }

          item.flatpickr({ ...settings, ...extraSettings });

          if (jumpToDate) {
            item._flatpickr.jumpToDate(jumpToDate);
          }
        });
      }
    }
  };

})(Drupal, drupalSettings);
