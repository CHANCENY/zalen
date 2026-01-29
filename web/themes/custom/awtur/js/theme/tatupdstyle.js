;(function ($, Drupal) {

  Drupal.behaviors.updstyle = {
    attach: function (context, settings) {
        var create = {
      sumoselect: function(element) {
        var $element = $(element);
        $element.SumoSelect({floatWidth: 300, forceCustomRendering: true, csvDispCount: 2, selectAll: true,});
      },
      dateDatepicker: function(element) {
        var $element = $(element);
        $element.datepicker({weekends: [6,0], minDate: new Date(), dateFormat:'yyyy-mm-dd',});
      },
      monthDatepicker: function(element) {
        var $element = $(element);
        $element.datepicker({minView: "months", view: 'months', minDate: new Date(), dateFormat:'yyyy-mm',});
      },
     /* timeDatepicker: function(element) {
        var $element = $(element);
        let currentTime = new Date();
        if (currentTime.getMinutes() % 5) {
          currentTime.setMinutes(Math.ceil(currentTime.getMinutes()/5)*5);
        };
        //$element.datepicker({timepicker: true, onlyTimepicker: true, minDate: currentTime, minutesStep: 5, dateFormat:'H:i',});
        let parentWrap = $element[0].parentNode.parentNode;
        if (parentWrap.hasAttribute('data-drupal-field-elements') && parentWrap.getAttribute('data-drupal-field-elements') === 'date-time') {
          let day = parentWrap.querySelector('input[type="date"]');
          if (day === null) {
            $element.datepicker({timepicker: true, onlyTimepicker: true, minutesStep: 5, dateFormat:'H:i',});
            return;
          };
        };*/
        timeDatepicker: function(element) {
          var $element = $(element);
          let currentTime = new Date();
          if (currentTime.getMinutes() % 5) {
            currentTime.setMinutes(Math.ceil(currentTime.getMinutes()/5)*5);
          };
          $element.datepicker({
            timepicker: true,
            onlyTimepicker: true,
            minDate: currentTime,
            minutesStep: 30,
            dateFormat:'H:i',
            timeFormat: 'H:i',
            use24hours: true,
          });
      },
      
        $element.datepicker({
          timepicker: true, onlyTimepicker: true, minDate: currentTime, minutesStep: 5, dateFormat:'H:i',
            onShow(date, isFinished) {//When revealing the timepicker
            if (isFinished === false) {
              let date_timeWrap = date.el.closest('div[data-drupal-field-elements="date-time"]');
              if (date_timeWrap) {
                let day = date.el.parentNode.previousElementSibling;
                if (day) {
                  day = day.getElementsByTagName('input')[0].value;
                  if (day) {
                    let nowTime = new Date();
                    //let todayTime = new Date(nowTime.getFullYear(), nowTime.getMonth(), nowTime.getDate());// -nowTime.getTimezoneOffset() / 60
                    let rawTime = new Date(day);
                    let inputTime = new Date(rawTime.getFullYear(), rawTime.getMonth(), rawTime.getDate(),
                    nowTime.getHours(), nowTime.getMinutes(), nowTime.getSeconds(), nowTime.getMilliseconds());
                    rawTime = (inputTime > nowTime) ? inputTime : nowTime;
                    if (rawTime.getMinutes() % 5) {
                      rawTime.setMinutes(Math.ceil(rawTime.getMinutes()/5)*5);
                    };
                    date.update({minDate: rawTime,});
                  };
                };
              };
            };
          },
        });
        },
      };

      $('select', context).once('updstyle').each(function () {
        // Apply the myCustomBehaviour effect to the elements only once.
        create.sumoselect(this);
      });
      $('input[type="date"]', context).once('updstyle').each(function () {
        create.dateDatepicker(this);
      });
      $('input[type="month"]', context).once('updstyle').each(function () {
        create.monthDatepicker(this);
      });
      $('input[type="time"]', context).once('updstyle').each(function () {
        create.timeDatepicker(this);
      });

    }
  };

})(jQuery, Drupal);
