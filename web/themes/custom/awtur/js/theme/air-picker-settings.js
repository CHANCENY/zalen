/*(function ($, Drupal) {
    Drupal.behaviors.updstyle = {
      attach: function (context, settings) {
        var create = {
          sumoselect: function(element) {
            var $element = $(element);
            $element.SumoSelect({
              floatWidth: 300,
              forceCustomRendering: true,
              csvDispCount: 2,
              selectAll: true
            });
          },
          dateDatepicker: function(element) {
            var $element = $(element);
            $element.datepicker({
              weekends: [6,0],
              minDate: new Date(),
              dateFormat: 'yyyy-mm-dd'
            });
          },
          monthDatepicker: function(element) {
            var $element = $(element);
            $element.datepicker({
              minView: "months",
              view: 'months',
              minDate: new Date(),
              dateFormat: 'yyyy-mm'
            });
          },
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
              onShow: function(date) {
                let date_timeWrap = date.el.closest('div[data-drupal-field-elements="date-time"]');
                if (date_timeWrap) {
                  let day = date.el.parentNode.previousElementSibling;
                  if (day) {
                    day = day.getElementsByTagName('input')[0].value;
                    if (day) {
                      let nowTime = new Date();
                      let rawTime = new Date(day);
                      let inputTime = new Date(rawTime.getFullYear(), rawTime.getMonth(), rawTime.getDate(),
                      nowTime.getHours(), nowTime.getMinutes(), nowTime.getSeconds(), nowTime.getMilliseconds());
                      rawTime = (inputTime > nowTime) ? inputTime : nowTime;
                      if (rawTime.getMinutes() % 5) {
                        rawTime.setMinutes(Math.ceil(rawTime.getMinutes()/5)*5);
                      };
                      date.update({
                        minDate: rawTime
                      });
                    };
                  };
                };
              }
            });
          }
        };
          
        $('select', context).once('updstyle').each(function () {
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
  })(jQuery, Drupal);*/

  /*(function ($, Drupal) {
    Drupal.behaviors.updstyle = {
      attach: function (context, settings) {
        var create = {
          dateDatepicker: function(element) {
            var $element = $(element);
            $element.datepicker({
              weekends: [6,0],
              minDate: new Date(),
              dateFormat: 'yyyy-mm-dd'
            });
          },
          monthDatepicker: function(element) {
            var $element = $(element);
            $element.datepicker({
              minView: "months",
              view: 'months',
              minDate: new Date(),
              dateFormat: 'yyyy-mm'
            });
          },
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
              onShow: function(date) {
                let date_timeWrap = date.el.closest('div[data-drupal-field-elements="date-time"]');
                if (date_timeWrap) {
                  let day = date.el.parentNode.previousElementSibling;
                  if (day) {
                    day = day.getElementsByTagName('input')[0].value;
                    if (day) {
                      let nowTime = new Date();
                      let rawTime = new Date(day);
                      let inputTime = new Date(rawTime.getFullYear(), rawTime.getMonth(), rawTime.getDate(),
                      nowTime.getHours(), nowTime.getMinutes(), nowTime.getSeconds(), nowTime.getMilliseconds());
                      rawTime = (inputTime > nowTime) ? inputTime : nowTime;
                      if (rawTime.getMinutes() % 5) {
                        rawTime.setMinutes(Math.ceil(rawTime.getMinutes()/5)*5);
                      };
                      date.update({
                        minDate: rawTime
                      });
                    };
                  };
                };
              }
            });
          }
        };
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
  })(jQuery, Drupal);*/

 