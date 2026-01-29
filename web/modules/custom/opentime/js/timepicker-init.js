/* timepicker-init.js */
(function ($, Drupal, once) {
  Drupal.behaviors.opentimeTimepicker = {
    attach: function (context, settings) {
      $(once('opentimeTimepicker', '.timepicker', context)).each(function () {
        $(this).timepicker({
          showAnim: 'slideToggle',
          duration:500,
          defaultTime: 'midnight', 
          showPeriodLabels: false,
          minutes: {
            interval:15,
          },
          hourText: 'Uur',           
          minuteText: 'Minuut', 
        });
      });
    }
  };
})(jQuery, Drupal, once);

