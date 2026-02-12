/**
 * Please don't edit this file if you don't know what this file is used for.
 */

const timer_id = setInterval(function (){
  var preview_calculation = document.getElementById('preview-calculation');
  var calculation_report_wrapper = document.getElementById('calculation_report');
  if(preview_calculation && calculation_report_wrapper) {
    clearInterval(timer_id);
    preview_calculation.addEventListener('click',(e)=>{
       e.preventDefault();
       const fields = fieldQuery();
       const xhr = new XMLHttpRequest();
       xhr.open('POST', '/web/reservation/booking/amount/preview', true);
       xhr.onload = function () {
         if(this.status === 200) {
           try{
             const data = JSON.parse(this.responseText)
             console.log(data)
             if(!data.hasOwnProperty('error')) {
               // Select the element with ID edit-calculation-report
               var calculationReportFieldset = document.getElementById('edit-calculation-report');

               // Remove existing <div> with class 'review-wrapper', if any
               var existingWrapper = calculationReportFieldset.querySelector('div.review-wrapper');
               if (existingWrapper) {
                 existingWrapper.remove();
               }

               // Create a new <div> to wrap the <p> elements
               var divWrapper = document.createElement('div');
               divWrapper.classList.add('review-wrapper');
               divWrapper.style.marginTop = '20px';
               divWrapper.style.border = '1px solid #eee';
               divWrapper.style.padding = '10px';
               divWrapper.style.borderRadius = '3px';

               // Create a new <p> element for 'Hello'
               var p1 = document.createElement('p');
               p1.innerHTML = '<strong>Total Amount: </strong>&nbsp;<span>'+ data.reports.price +'</span>&nbsp;';
               p1.classList.add('review'); // Add class 'review' to the first <p> element

               // Create another new <p> element for 'World'
               var p2 = document.createElement('p');
               p2.textContent = data.reports.message;
               p2.classList.add('review'); // Add class 'review' to the second <p> element

               // Append the <p> elements to the <div> wrapper
               divWrapper.appendChild(p1);

               const additional_p = document.createElement('p');
               additional_p.className = 'review';
               if(data.additional.services) {
                 let inner = '';
                 data.additional.services.forEach((item)=>{
                   inner += `<strong>${item.name}</strong>&nbsp;<span>${item.count} &nbsp;total ${item.price}</span><br>`;
                 });
                 additional_p.innerHTML = inner;
                 divWrapper.appendChild(additional_p);
                 p2.innerHTML += '&nbsp;<i>(additional services included)</i>';
               }
               divWrapper.appendChild(p2);
               // Append the <div> wrapper to the fieldset
               calculationReportFieldset.appendChild(divWrapper);

             }
           }catch (e) {

           }
         }
       }
       xhr.send(JSON.stringify(fields));
    });
  }
},1000);

function fieldQuery() {

  const values = [];
  // Select the input element using its data-drupal-selector attribute
  var inputElement = document.querySelector('input[data-drupal-selector="edit-field-bezetting-0-value"]');

  // Get the name attribute
  var fieldName = inputElement.name;

  // Get the value attribute
  var fieldValue = inputElement.value;
  values.push({name: fieldName, value: fieldValue});

  // Select the checkbox element using its data-drupal-selector attribute
  var checkboxElement = document.querySelector('input[data-drupal-selector="edit-field-per-person-value"]');

  if(checkboxElement) {
    // Get the name attribute
    var fieldName = checkboxElement.name;

    // Get the value attribute (will be "1" if checked, otherwise an empty string)
    var fieldValue = checkboxElement.checked ? checkboxElement.value : '0';
    values.push({name: fieldName, value: fieldValue});
  }

  // Get the start date input element
  var startDateElement = document.querySelector('input[data-drupal-selector="edit-field-date-booking-0-value-date"]');
  // Get the start time input element
  var startTimeElement = document.querySelector('input[data-drupal-selector="edit-field-date-booking-0-value-time"]');
  // Get the end date input element
  var endDateElement = document.querySelector('input[data-drupal-selector="edit-field-date-booking-0-end-value-date"]');
  // Get the end time input element
  var endTimeElement = document.querySelector('input[data-drupal-selector="edit-field-date-booking-0-end-value-time"]');
  // Get the duration select element
  var durationElement = document.querySelector('select[data-drupal-selector="edit-field-date-booking-0-duration"]');
  // Get the hidden timezone input element
  var timezoneElement = document.querySelector('input[data-drupal-selector="edit-field-date-booking-0-timezone"]');

  values.push({name: startDateElement.name,value: startDateElement.value});
  values.push({name: endDateElement.name,value: endDateElement.value});
  values.push({name: startTimeElement.name,value: startTimeElement.value});
  values.push({name: endTimeElement.name,value: endTimeElement.value});
  values.push({name: durationElement.name,value: durationElement.value});
  values.push({name: timezoneElement.name,value: timezoneElement.value});

  // Select the hidden input element using its data-drupal-selector attribute
  var hiddenInputElement = document.querySelector('input[data-drupal-selector="edit-room-service"]');

  // Get the name attribute
  var fieldName = hiddenInputElement.name;

  // Get the value attribute
  var fieldValue = hiddenInputElement.value;
  values.push({name: fieldName, value: fieldValue});

  // Select the checkbox element using its data-drupal-selector attribute
  var checkboxElement = document.querySelector('input[data-drupal-selector="edit-field-per-hour-calc-value"]');

  // Get the name attribute
  var fieldName = checkboxElement.name;

  // Get the value attribute (will be "1" if checked, otherwise an empty string)
  var fieldValue = checkboxElement.checked ? checkboxElement.value : '0';
  values.push({name: fieldName,value: fieldValue});
  values.push({name: 'additional', value: getCountValues()});
  return values;
}


// Function to get values of _count inputs if corresponding checkboxes are checked
function getCountValues() {
  // Select the fieldset
  var fieldset = document.querySelector('[data-drupal-selector="edit-additional-services-fieldset"]');
  var values = {};

  if (fieldset) {
    // Find all checkboxes within the fieldset
    var checkboxes = fieldset.querySelectorAll('input[type="checkbox"]');

    checkboxes.forEach(function(checkbox) {
      // Check if the checkbox is checked
      if (checkbox.checked) {
        // Construct the corresponding _count input's selector
        var countInputSelector = `input[name="${checkbox.name}_count"]`;
        var countInput = fieldset.querySelector(countInputSelector);

        if (countInput) {
          // Add the checkbox name and _count value to the values object
          values[checkbox.name] = countInput.value;
        }
      }
    });
  }

  return values;
}

const time_id2 = setInterval(()=>{
  var checkboxElement = document.querySelector('input[type="checkbox"].allday');
  var mainCheckbox = document.querySelector('input[data-drupal-selector="edit-field-per-hour-calc-value"]');
  if(checkboxElement && mainCheckbox) {
    clearInterval(time_id2);
    checkboxElement.addEventListener('change',(e)=>{
      if(e.target.checked) {
        mainCheckbox.checked = false;
      }
    });
  }
}, 1000);

