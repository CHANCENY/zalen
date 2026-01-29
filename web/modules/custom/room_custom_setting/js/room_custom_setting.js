(function (Drupal, $, once) {
    'use strict';

    Drupal.behaviors.room_custom_setting = {
      attach: function(context, settings) {

        // $('#custom_calendar td').once('myJsCalendarCustomBehavior').each(
        $(once('myJsCalendarCustomBehavior', '#custom_calendar td')).each(
          function(){
            if (this.hasAttribute('data-availability')) {this.classList.add('day-claim');};
          }
          ).hover(
          function(){
            if (!elPressed && this.hasAttribute('data-availability')) {
              elPointed = document.createElement('div');
              elPointed.innerHTML = createAvailabilityForDay(this);
              elPointed = elPointed.firstChild;
              elPointed = this.parentNode.parentNode.parentNode.parentNode.appendChild(elPointed);
            } else{return;};
          },
          function(){
            if (!elPressed && this.hasAttribute('data-availability')) {
              elPointed.parentNode.removeChild(elPointed);
            } else{return;};
            }
        ).on('click', function(){
          if (this.hasAttribute('data-availability')) {
          elPressed = elPointed;
          elPointed = document.createElement('div');
          elPointed.innerHTML = '<input class="my-message-ok" type="button" value="OK"/>';
          elPointed = elPointed.firstChild;
          elPointed.onclick = function() {this.parentNode.parentNode.removeChild(this.parentNode);elPressed=null;};
          elPressed.appendChild(elPointed);
          } else{return;};
        });

        var elPressed = null, elPointed = null;

        function createAvailabilityForDay (element){
          if (element.classList == '') {
            return false;
          };
          if (!element.getAttribute('data-availability')) {
            return false;
          };

          var day = getrDayData(element);

          var elData = '<div id="descrip-availability-day">\
          <div><table>\
          <tbody>\
            <tr>\
            <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>\
          </tr>\
          <tr>\
            <td>'+day['Mon']+'</td><td>'+day['Tue']+'</td><td>'+day['Wed']+'</td><td>'+day['Thu']+'</td><td>'+day['Fri']+'</td><td class="calendar-day last">'+day['Sat']+'</td><td class="calendar-day last">'+day['Sun']+'</td>\
          </tr>\
          </tbody>\
          </table></div>\
          <div><p>'+day['now']+'</p>\
          <div>'+day['nighttime']+day['daytime']+'</div></div>\
          </div>';

          return elData;
        };

        function getrDayData (el) {
          var day = {
            now: el.textContent + ' ' + el.parentNode.parentNode.parentNode.parentNode.querySelector('div.calendar-head').textContent || 'XX:XX',
            Mon: 'XX:XX',
            Tue: 'XX:XX',
            Wed: 'XX:XX',
            Thu: 'XX:XX',
            Fri: 'XX:XX',
            Sat: 'XX:XX',
            Sun: 'XX:XX',
            nighttime: getrSvgData(el, '00:00-12:00'),
            daytime: getrSvgData(el, '12:00-24:00'),
          };

          return day;
        };

        function getrSvgData (el, timeSpan) {

          var availability = el.dataset.availability.split(';');

          for (var i = 0; i < availability.length; i++) {
            if (availability[i].slice(0, 5) < timeSpan.slice(0, 5)) {availability[i] = timeSpan.slice(0, 6) + availability[i].slice(6, 11);};
            if (availability[i].slice(6, 11) > timeSpan.slice(6, 11)) {availability[i] = availability[i].slice(0, 6) + timeSpan.slice(6, 11);};
            if (availability[i].slice(0, 5) >= availability[i].slice(6, 11)) {availability.splice(i, 1);i--;};
          };

          var out = {
            tagSvg: ['<svg class="chart" width="100" height="100" viewBox="0 0 50 50">','</svg>'],
            tagCircle: ['<circle class="unit" cx="50%" cy="50%"','></circle>'],
            tagDiv: ['<div>','</div>'],
            getTimeInterval: function (strDate) {
              var Interval = strDate.split('-');
              var different = (this.getDateTime(Interval[1]) - this.getDateTime(Interval[0]));
              different = different / 1000 / 60 /10;
              return different;
            },
            getTimeVacuumInterval: function (allInterval,interval) {
              var vacuum = this.getTimeInterval(allInterval) - this.getTimeInterval(interval);
              return vacuum;
            },
            getDateTime: function (string) {
              return new Date(0, 0,0, string.split(':')[0], string.split(':')[1]);
            },
            getRadius: function (circle) {
              var radius = circle / (2 * Math.PI);
              radius = Math.round(radius * 100)/100;
              return radius;
            },
            getTimeOffset: function (all,part) {
              var timeOffset = all.slice(0, 5) + '-' + part.slice(0, 5);
              timeOffset = this.getTimeInterval(timeOffset);
              timeOffset = this.getTimeInterval('00:00-03:00') - timeOffset;
              return timeOffset;
            },
          };

          //not dynamic values
          var properties = {
            fill: 'none',
            strokeAvailability: '#f18080',
            strokeAvailable: '#66c10c',
            strokeClock: '#000',
            dasharrayAllInterval: out.getTimeInterval(timeSpan),
            width: '6',
          };
          properties.radius = out.getRadius(properties.dasharrayAllInterval);
          properties.dasharrayMinute = properties.dasharrayAllInterval/60/50*10;
          properties.dasharrayHourOut = properties.dasharrayAllInterval/12-properties.dasharrayAllInterval/60/50*10;
          properties.defaultTimeOffset = properties.dasharrayAllInterval/4;

          var elDiagram = '';
          elDiagram += out.tagDiv[0] + out.tagSvg[0];
          elDiagram += out.tagCircle[0] + 'r="'+properties.radius+'"' + 'fill="'+properties.fill+'"' +
          'stroke="'+properties.strokeAvailable+'"' + 'stroke-width="'+(Math.round(properties.width*0.8*10)/10)+'"' +
          'stroke-dasharray="'+properties.dasharrayAllInterval+' '+'0'+'"' +
          'stroke-dashoffset="'+'0'+'"' + out.tagCircle[1];
          for (var k = 0; k < availability.length; k++) {
            elDiagram += out.tagCircle[0] + 'r="'+properties.radius+'"' + 'fill="'+properties.fill+'"' +
            'stroke="'+properties.strokeAvailability+'"' + 'stroke-width="'+properties.width+'"' +
            'stroke-dasharray="'+out.getTimeInterval(availability[k])+' '+out.getTimeVacuumInterval(timeSpan,availability[k])+'"' +
            'stroke-dashoffset="'+out.getTimeOffset(timeSpan,availability[k])+'"' + out.tagCircle[1];
          };
          elDiagram += out.tagCircle[0] + 'r="'+properties.radius+'"' + 'fill="'+properties.fill+'"' +
          'stroke="'+properties.strokeClock+'"' + 'stroke-width="'+(Math.round(properties.width*1.2*10)/10)+'"' +
          'stroke-dasharray="'+properties.dasharrayMinute+' '+properties.dasharrayHourOut+'"' +
          'stroke-dashoffset="'+properties.defaultTimeOffset+'"' + out.tagCircle[1];

          elDiagram += out.tagCircle[0] + 'r="'+(properties.radius +1)+'"' + 'fill="'+properties.fill+'"' +
          'stroke="'+'#fff'+'"' + 'stroke-width="'+(Math.round(properties.width*0.8*10)/20)+'"' +
          'stroke-dasharray="'+properties.dasharrayAllInterval+' '+'0'+'"' +
          'stroke-dashoffset="'+'0'+'"' + out.tagCircle[1];

          elDiagram += '<text id="heading" x="22" y="10" font-size="6" font-weight="bold" fill="#000" font-family="Arial, Helvetica, sans-serif">' + timeSpan.slice(0, 2) + '</text>' +
          '<text  id="caption" x="22" y="45" font-size="6" font-weight="bold" fill="#000" font-family="Arial, Helvetica, sans-serif">' + ('0' + (+timeSpan.slice(0, 2)+6)).slice(-2) + '</text>';

          elDiagram += out.tagSvg[1] + out.tagDiv[1];

          return elDiagram;
        };

      },
    };

})(Drupal, jQuery, once);

//Below function to adjust the minutes interval in the reservation form
(function (Drupal, drupalSettings) {
  Drupal.behaviors.customSmartDateStep = {
    attach: function (context, settings) {
      once('customSmartDateStep', '.smartdate--widget input[type="time"]', context).forEach(function (element) {
        element.step = 900; // Set step to 15 minutes
      });
    }
  };
})(Drupal, drupalSettings);

/*<input class="time-start form-time required" 
 type="time" step="900" placeholder="hh:mm:ss"
  data-help="Vul de tijd als volgt in: hh:mm:ss (b.v., 07:56:48)." 
  id="edit-field-date-booking-0-value-time" name="field_date_booking[0][value][time]"
   value="02:00" size="24" required="required" aria-required="true"
    data-once="smartDateHideSeconds smartDateStartChange customSmartDateStep"></input>*/