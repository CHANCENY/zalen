(function ($, Drupal) {
  Drupal.behaviors.reservationBookingAmountPreview = {
    attach: function (context, settings) {
      $(document).ready(function () {

        let DEFAULT_CALCULATION;
        let CALCULATION_BY;

        function calculationCheckBoxesToggler() {
          const $inputs = $('#edit-field-calculate-payment-per-day-value, #edit-field-per-hour-calc-value, #edit-field-per-person-value', context);
          const $personOptions = $('#edit-per-person-options', context);

          if ($inputs.length > 0) {
            // Set default based on initially checked input
            DEFAULT_CALCULATION = $inputs.filter(':checked')[0];
            CALCULATION_BY = DEFAULT_CALCULATION;

            $inputs.off('change.calcToggle').on('change.calcToggle', function () {

              // If the clicked checkbox is already checked, this event will be triggered AFTER it's unchecked
              setTimeout(() => {
                const $checked = $inputs.filter(':checked');

                if ($checked.length === 0 && DEFAULT_CALCULATION) {
                  // Re-check the default if all are unchecked
                  $(DEFAULT_CALCULATION).prop('checked', true).trigger('change');
                } else if (this.checked) {
                  CALCULATION_BY = this;
                  // Uncheck others
                  $inputs.not(this).prop('checked', false);

                  // Toggle options
                  if (this.id === 'edit-field-per-person-value') {
                    $personOptions.show();
                  } else {
                    $personOptions.hide();
                  }
                }
              }, 0);
            });
          }
        }

        function isTodayWithinRange({ value, end_value }) {
          const now = new Date();

          const startDate = new Date(value);
          const endDate = new Date(end_value);

          return now >= startDate && now <= endDate;
        }

        function formatDateRange({ value, end_value }) {
          if (!value || !end_value) return '';

          const options = { year: 'numeric', month: 'long', day: 'numeric' };

          const startDate = new Date(value);
          const endDate = new Date(end_value);

          if (isNaN(startDate) || isNaN(endDate)) return '';

          return `${startDate.toLocaleDateString(undefined, options)} - ${endDate.toLocaleDateString(undefined, options)}`;
        }

        function isBookingDatesInSeasonalRange(dates, seasonalRange) {
          
          if (!dates || !seasonalRange) return false;

           // Parse booking dates (replace space with T for proper parsing)
            const bookingStart = new Date(dates.start.replace(' ', 'T'));
            const bookingEnd = new Date(dates.end.replace(' ', 'T'));

             // Parse seasonal rule dates
             const seasonStart = new Date(seasonalRange.value);
             const seasonEnd = new Date(seasonalRange.end_value);

            // Validate dates
            if (isNaN(bookingStart) || isNaN(bookingEnd) || isNaN(seasonStart) || isNaN(seasonEnd)) {
                return false;
            }

            // Check if booking is fully inside seasonal range
            return bookingStart >= seasonStart && bookingEnd <= seasonEnd;

        }


        function pricingRulesValidation() {
          setTimeout(()=>{
            const $jsonField = $('#json_validation_data', context);
            if ($jsonField.length) {
              const validationData = JSON.parse($jsonField.val());
              if (validationData) {
                personOptionListener(validationData.hasOwnProperty('person_booking') ? validationData.person_booking : {});
                const checkBtn = $("#preview-calculation", context);
                if (checkBtn.length) {

                  checkBtn.off('click.previewCalc').on('click.previewCalc', function (e) {
                    e.preventDefault();
                    if (CALCULATION_BY) {

                      // checking for per-hour validation of rules.
                      if (CALCULATION_BY.id === 'edit-field-per-hour-calc-value') {

                        let $dates = getBookedDateTimes();
                        let hoursBooked = getHourDifference($dates.start, $dates.end);

                        if (hoursBooked <= 0) {
                          showErrorModal('Sorry, the booking start date must be earlier than the end date.');
                          return;
                        }

                        let hourRate = validationData.hour_booking.hourlyRate[0];

                        if (hourRate.miniHours > hoursBooked) {
                          showErrorModal('Sorry, to book this room you are required to book for '+hourRate.miniHours + ' hours or more. Please add more hours on booking date field.');
                          return;
                        }

                        let lowestMiniHour = hoursBooked;
                        let amount = hourRate.amount;
                        let seasonalApplied = false;
                        let advanceRuleApplied = false;

                        Object.values(hourRate.bookingAdvanceRules).forEach(rule => {
                          if (lowestMiniHour >= rule.miniHours) {
                            amount = rule.amount;
                            advanceRuleApplied = true;
                          }
                        });

                        Object.values(hourRate.seasonal_rules).forEach(rule => {
                            if (rule?.date_range) {
                              if (isBookingDatesInSeasonalRange($dates, rule.date_range[0])) {
                                 amount = rule.amount;
                                 seasonalApplied = true;
                                 advanceRuleApplied = false;
                              }
                            }
                        });

                        // bring in services
                        const servicesSelected = getSelectedAdditionalServices(validationData.additionalServices);
                        let servicesTotalAmount = 0;
                        if (servicesSelected.length) {
                          servicesTotalAmount = servicesSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                        }

                        // bring in rooms
                        const overnightRoomsSelected = getSelectedOvernightRooms(validationData.overnightRooms ? validationData.overnightRooms : {});
                        let overnightRoomsTotalAmount = 0;
                        let overnightHeadTotalCount = 0;
                        let overnightSentement = '';

                        if (overnightRoomsSelected.length) {
                          overnightRoomsTotalAmount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                          overnightHeadTotalCount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalCount, 0);
                          const symblonight = getCurrencySymbol(overnightRoomsSelected[0].currency);
                          overnightSentement = `<li><strong>Overnight rooms total:</strong> &nbsp; ${symblonight} ${overnightRoomsTotalAmount}</li>`;
                        }

                        // make the preview html elements
                        const currencySymbol = getCurrencySymbol(hourRate.currency);
                        let summaryElement = `<h3>Hourly Pricing Summary</h3><ul><li><strong>Booked hours:&nbsp;</strong> ${hoursBooked}</li>
                                                    <li><strong>Services total amount: &nbsp;</strong> ${currencySymbol}${servicesTotalAmount} </li>
                                                     ${overnightSentement}
                                                    <li><strong>Total amount:</strong> ${currencySymbol}${hoursBooked * amount + servicesTotalAmount + overnightRoomsTotalAmount}</li>
                                                    </ul>`;
                        summaryElement += `<h4>Hourly Charges Rules Available</h4><ul>`;
                        if (amount === hourRate.amount) {
                          summaryElement += `<li><strong>Normal hour charge:&nbsp;</strong> ${currencySymbol}${hourRate.amount} &nbsp;<em>applied</em></li>`;
                        }
                        else {
                          summaryElement += ` <li><strong>Normal hour charge:&nbsp;</strong> ${currencySymbol}${hourRate.amount}</li>`;
                        }

                        Object.values(hourRate.bookingAdvanceRules).forEach(rule => {
                          if (rule.amount === amount && advanceRuleApplied) {
                            summaryElement += `<li><strong>Booking ${rule.miniHours} hrs or more charge:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr &nbsp;<em>applied</em></li>`;
                          }
                          else {
                            summaryElement += `<li><strong>Booking ${rule.miniHours} hrs or more charge:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr</li>`;
                          }
                        });
                        summaryElement += `</ul>`;

                        if (hourRate.seasonal_rules) {
                          summaryElement += "<h4>Seasonal pricing</h4>";
                          summaryElement += "<ul>";
                          Object.values(hourRate.seasonal_rules).forEach(rule => {

                            const formattedDate = formatDateRange(rule.date_range[0]);
                            if (rule.amount === amount && seasonalApplied) {
                              summaryElement += `<li><strong>Booking between ${formattedDate} ${rule.label} season:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr &nbsp;<em>applied</em></li>`;
                            }
                            else {
                              summaryElement += `<li><strong>Booking between ${formattedDate} ${rule.label} season:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr</li>`;
                            }
                          })
                          summaryElement += "</ul>";
                        }

                        if (servicesSelected.length) {
                          summaryElement += `<h4>Additional Services</h4><ul>`;
                          servicesSelected.forEach(service => {
                            summaryElement += `<li><strong>${service.name} (${service.totalCount}):</strong> ${service.symbol}${service.totalAmount}</li>`;
                          })
                          summaryElement += `</ul>`;
                        }

                        if (overnightRoomsSelected.length) {
                          summaryElement += `<h4>Overnight Rooms Selected</h4><ul>`;
                          overnightRoomsSelected.forEach(room => {
                            summaryElement += `<li><strong>${room.name} (${room.totalCount}):</strong>&nbsp; ${room.symbol}${room.amount} <em>nights: &nbsp; ${room.nights}</em></li>`;
                          })
                        }

                        showErrorModal(`<div class="pricing-summary">${summaryElement}</div>`, 'Summary');

                      }

                      else if (CALCULATION_BY.id === 'edit-field-calculate-payment-per-day-value') {
                        let $dates = getBookedDateTimes();
                        let daysBooked = Math.ceil(getHourDifference($dates.start, $dates.end) / 24);

                        if (daysBooked <= 0) {
                          showErrorModal('Sorry, the booking start date must be earlier than the end date. or please select per hour calculation.','Error');
                          return;
                        }
                        let dayRate = validationData.day_booking.dayRate[0];
                        if (dayRate.miniDays > daysBooked) {
                          showErrorModal('Sorry, to book this room you are required to book for '+dayRate.miniDays + ' days or more. Please add more hours on booking date field.');
                          return;
                        }

                        let lowestMiniHour = daysBooked;
                        let amount = dayRate.amount;
                        let seasonalApplied = false;
                        let advanceRuleApplied = false;

                        Object.values(dayRate.bookingAdvanceRules).forEach(rule => {
                          if (lowestMiniHour >= rule.miniDays) {
                            amount = rule.amount;
                            advanceRuleApplied = true;
                          }
                        });

                        Object.values(dayRate.seasonal_rules).forEach(rule => {
                          if (rule?.date_range) {
                            if (isBookingDatesInSeasonalRange($dates, rule.date_range[0])) {
                              amount = rule.amount;
                              seasonalApplied = true;
                              advanceRuleApplied = false;
                            }
                          }
                        });

                        // bring in services
                        const servicesSelected = getSelectedAdditionalServices(validationData.additionalServices);
                        let servicesTotalAmount = 0;
                        if (servicesSelected.length) {
                          servicesTotalAmount = servicesSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                        }

                        // bring in rooms
                        const overnightRoomsSelected = getSelectedOvernightRooms(validationData.overnightRooms ? validationData.overnightRooms : {});
                        let overnightRoomsTotalAmount = 0;
                        let overnightHeadTotalCount = 0;
                        let overnightSentement = '';
                        if (overnightRoomsSelected.length) {
                          overnightRoomsTotalAmount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                          overnightHeadTotalCount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalCount, 0);
                          const symblonight = getCurrencySymbol(overnightRoomsSelected[0].currency);
                          overnightSentement = `<li><strong>Overnight rooms total:</strong> &nbsp; ${symblonight} ${overnightRoomsTotalAmount}</li>`;
                        }

                        // make the perview html elements
                        const currencySymbol = getCurrencySymbol(dayRate.currency);
                        let summaryElement = `<h3>Day Pricing Summary</h3><ul>
                                                        <li><strong>Booked days:&nbsp;</strong> ${daysBooked}</li>
                                                        <li><strong>Services total amount: &nbsp;</strong> ${currencySymbol}${servicesTotalAmount} </li>
                                                        ${overnightSentement}
                                                        <li><strong>Total amount:&nbsp;</strong>${currencySymbol}${daysBooked * amount + servicesTotalAmount + overnightRoomsTotalAmount}</li>
                                                        </ul>`;
                        summaryElement += `<h4>Day Charges Rules Available</h4><ul>`;
                        if (amount === dayRate.amount) {
                          summaryElement += `<li><strong>Normal day charge:&nbsp;</strong> ${currencySymbol}${dayRate.amount} &nbsp;<em>applied</em></li>`;
                        }
                        else {
                          summaryElement += ` <li><strong>Normal day charge:&nbsp;</strong> ${currencySymbol}${dayRate.amount}</li>`;
                        }

                        Object.values(dayRate.bookingAdvanceRules).forEach(rule => {
                          if (rule.amount === amount) {
                            summaryElement += `<li><strong>Booking ${rule.miniDays} days or more charge:&nbsp;</strong> ${currencySymbol}${rule.amount}/day &nbsp;<em>applied</em></li>`;
                          }
                          else {
                            summaryElement += `<li><strong>Booking ${rule.miniDays} days or more charge:&nbsp;</strong> ${currencySymbol}${rule.amount}/day</li>`;
                          }
                        });
                        summaryElement += `</ul>`;

                        if (dayRate.seasonal_rules) {
                          summaryElement += "<h4>Seasonal pricing</h4>";
                          summaryElement += "<ul>";
                          Object.values(dayRate.seasonal_rules).forEach(rule => {

                            const formattedDate = formatDateRange(rule.date_range[0]);
                            if (rule.amount === amount && seasonalApplied) {
                              summaryElement += `<li><strong>Booking between ${formattedDate} ${rule.label} season:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr &nbsp;<em>applied</em></li>`;
                            }
                            else {
                              summaryElement += `<li><strong>Booking between ${formattedDate} ${rule.label} season:&nbsp;</strong> ${currencySymbol}${rule.amount}/hr</li>`;
                            }

                          })
                          summaryElement += "</ul>";
                        }

                        if (servicesSelected.length) {
                          summaryElement += `<h4>Additional Services</h4><ul>`;
                          servicesSelected.forEach(service => {
                            summaryElement += `<li><strong>${service.name} (${service.totalCount}):</strong> ${service.symbol}${service.totalAmount}</li>`;
                          })
                          summaryElement += `</ul>`;
                        }

                        if (overnightRoomsSelected.length) {
                          summaryElement += `<h4>Overnight Rooms Selected</h4><ul>`;
                          overnightRoomsSelected.forEach(room => {
                            summaryElement += `<li><strong>${room.name} (${room.totalCount}):</strong>&nbsp; ${room.symbol}${room.amount} <em>nights: &nbsp; ${room.nights}</em></li>`;
                          })
                        }

                        showErrorModal(`<div class="pricing-summary">${summaryElement}</div>`, 'Summary');
                      }

                      else if (CALCULATION_BY.id === 'edit-field-per-person-value') {

                        const $personOptions = validationData.person_booking;
                        const keys = Object.keys($personOptions);
                        const totalPersonCount = parseInt($("#edit-field-bezetting-0-value", context).val());
                        const personObjectData = [];
                        let flagMandatoryItemOneSelected = false;
                       
                        keys.forEach(key => {
                          const $personOption = $(`#${key}`,context);
                          const personValidationItem = $personOptions[key][0] ?? {};
                          let $personCount = parseInt($personOption.val());
                          if ($personCount) {
                            const personOptionData = personOptionObject(personValidationItem, $personCount);
                            if (personOptionData) {
                              personObjectData.push(personOptionData);
                            }
                          }
                        });
                        if (personObjectData.length === 0) {
                          showErrorModal('Please select at least one person option.', 'Warning');
                          return;
                        }

                        const personTotalCount = personObjectData.reduce((acc, item) => acc + item.count, 0);
                        const personTotalAmount = personObjectData.reduce((acc, item) => acc + (item.amount * item.count), 0);

                        // bring in services
                        const servicesSelected = getSelectedAdditionalServices(validationData.additionalServices);
                        let servicesTotalAmount = 0;
                        if (servicesSelected.length) {
                          servicesTotalAmount = servicesSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                        }

                        // bring in rooms
                        const overnightRoomsSelected = getSelectedOvernightRooms(validationData.overnightRooms ? validationData.overnightRooms : {});
                        let overnightRoomsTotalAmount = 0;
                        let overnightHeadTotalCount = 0;
                        let overnightSentement = '';
                        if (overnightRoomsSelected.length) {
                          overnightRoomsTotalAmount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalAmount, 0);
                          overnightHeadTotalCount = overnightRoomsSelected.reduce((acc, item) => acc + item.totalCount, 0);
                          const symblonight = getCurrencySymbol(overnightRoomsSelected[0].currency);
                          overnightSentement = `<li><strong>Overnight rooms total:</strong> &nbsp; ${symblonight} ${overnightRoomsTotalAmount}</li>`;
                        }

                        const personTotalAmountFormatted = getCurrencyNumber(personTotalAmount + servicesTotalAmount + overnightRoomsTotalAmount, personObjectData[0].currency);
                        const currencySymbol = getCurrencySymbol(personObjectData[0].currency);

                        // make the perview html elements
                        let summaryElement = `<h3>Day Pricing Summary</h3><ul>
                                                       <li><strong>Booked persons:&nbsp;</strong> ${personTotalCount}</li>
                                                       <li><strong>Services total amount: &nbsp;</strong> ${currencySymbol}${servicesTotalAmount} </li>
                                                       ${overnightSentement}
                                                       <li><strong>Total amount:</strong> ${personTotalAmountFormatted}</li>
                                                       </ul>`;
                        summaryElement += `<h4>Person options selected</h4><ul>`;

                        Object.values(personObjectData).forEach(option => {
                          const currencySymbol = getCurrencySymbol(option.currency);
                          summaryElement += `<li><strong>${option.name} (${option.count}):&nbsp;</strong> ${currencySymbol}${option.amount}/person</li>`;
                        });
                        summaryElement += `</ul>`;


                        if (personObjectData[0]['seasonals'].length > 0) {

                          let seasonalUsed = "<h4>Seasonal pricing used</h4><ul>";
                          let seasonals = "<h4>Available Seasonal Pricings</h4><ul>";
                          let seasonalFlag = false;
                          personObjectData.forEach((personItem)=>{

                            // HERE
                          
                            if (personItem.isSeasonal) {
                               const date = formatDateRange(personItem.seasonal.date_range[0]);
                              seasonalUsed += `<li>${personItem.name}: booked between ${date} for ${personItem.seasonal.label} season: ${currencySymbol}${personItem.amount}/person</li>`;
                              seasonalFlag = personItem.isSeasonal;
                            }

                            seasonalUsed += "</ul>";
                            
                            personItem.seasonals.forEach((seasonal)=>{
                               const dateL = formatDateRange(seasonal.date_range[0]);
                               seasonals += `<li>Book between ${dateL} for ${seasonal.label} season: ${currencySymbol}${seasonal.amount}/person</li>`
                            });
                            seasonals += "</ul>";

                          })

                          if (seasonalFlag) {
                            summaryElement += seasonalUsed;
                          }

                          summaryElement += seasonals;

                        }



                        if (servicesSelected.length) {
                          summaryElement += `<h4>Additional Services</h4><ul>`;
                          servicesSelected.forEach(service => {
                            summaryElement += `<li><strong>${service.name} (${service.totalCount}):</strong> ${service.symbol}${service.totalAmount}</li>`;
                          })
                          summaryElement += `</ul>`;
                        }

                        if (overnightRoomsSelected.length) {
                          summaryElement += `<h4>Overnight Rooms Selected</h4><ul>`;
                          overnightRoomsSelected.forEach(room => {
                            summaryElement += `<li><strong>${room.name} (${room.totalCount}):</strong>&nbsp; ${room.symbol}${room.amount} <em>nights: &nbsp; ${room.nights}</em></li>`;
                          })
                        }

                        if (personTotalCount < totalPersonCount) {
                          const difference = totalPersonCount - personTotalCount;
                          summaryElement += `<h4>Notice</h4><p><em>You have booked options for ${personTotalCount} person(s), but the Occupation field indicates ${totalPersonCount} person(s). Please book options for ${difference} more person(s).</em></p>`;
                        }
                        showErrorModal(`<div class="pricing-summary">${summaryElement}</div>`, 'Summary');

                      }

                    }

                  });

                  if (validationData.additionalServices) {
                    const keys = Object.keys(validationData.additionalServices);

                    if (keys.length) {
                      keys.forEach(key => {
                        const checkbox = $(`input[name="${key}"]`, context);

                        // Bind toggle logic to each checkbox
                        checkbox.off('change.previewCalc').on('change.previewCalc', function () {
                          const toggleId = `#service-count-wrapper-${key}`;
                          if ($(this).is(':checked')) {
                            $(toggleId).show();
                          } else {
                            $(toggleId).hide();
                          }
                        });
                      });
                    }
                  }

                  if (validationData.overnightRooms) {

                    let keys = Object.keys(validationData.overnightRooms);

                    if (keys.length) {
                      keys.forEach(key => {
                        const checkbox = $(`#${key}`, context);

                        // Bind toggle logic to each checkbox
                        checkbox.off('change.previewRoom').on('change.previewRoom', function () {
                          const toggleId = `.overnight-room-count-wrapper-${key}`;
                          if ($(this).is(':checked')) {
                            $(toggleId).show();
                          } else {
                            $(toggleId).hide();
                          }
                        });

                        const $overnightRoomCount = $(`#${key}_count`, context);
                        $overnightRoomCount.off('input.previewRoom').on('input.previewRoom', function () {
                          const value = parseInt($(this).val());
                          if (value > 0) {
                            if (validationData.overnightRooms[key].totalRoom < value) {
                              showErrorModal(`Sorry, only ${validationData.overnightRooms[key].totalRoom} rooms of type ${validationData.overnightRooms[key].name} are available.`, 'Warning');
                              $(this).val(0);
                              return;
                            }
                          }
                        })

                      });
                    }


                  }

                }
              }
            }
            let elements = $('#edit-field-bezetting-0-value', context);
          }, 3000);
        }

        function personOptionListener(personValidationData) {

          const getTotalPersonCount = (keys, type = 'default') => {

            let selectableKeys = keys.map(key => {
              return `#${key}`
            });
            let sum = 0;
            selectableKeys.forEach(key => {
              let $personOption = $(key,context);
              if ($personOption) {
                let $personCount = parseInt($personOption.val());
                if ($personOption.hasClass(type)) {
                  if ($personCount) {
                    sum += $personCount;
                  }
                }

              }
            })
            return sum;
          }

          if (personValidationData) {
            let keys = Object.keys(personValidationData);
            let EXCEED_FLAG = false;
            keys.forEach(key => {
              let namespace =key.replace(/[-_](.)/g, (_, char) => char.toUpperCase());
              let $personOption = $(`#${key}`,context);
              if ($personOption && key !== 'room') {
                $personOption.off(`input.personOption${namespace}`).on(`input.personOption${namespace}`, function (e) {

                  const personTotalCount = parseInt($("#edit-field-bezetting-0-value", context).val());
                  const personValidationItem = personValidationData[key][0] ?? {};
                  const $personCount = parseInt($(this).val());

                  if ($(this).hasClass('default')) {
                    if (!personTotalCount) {
                      $('#edit-field-bezetting-0-value', context).val(personValidationData.room.miniPersons)
                      showErrorModal(
                        'We have set the Bezetting (Occupation) to ' + personValidationData.room.miniPersons +
                        ', which is the minimum number of persons that can be booked. Please adjust it to your desired number of occupants.',
                        'Notice'
                      );
                    }

                    // validate person item person count
                    if ($personCount < personValidationItem.miniPersons) {

                      if (!$(this).data('prompted')) {
                        showErrorModal("This person '"+ personValidationItem.name +"' option requires minimum of "+personValidationItem.miniPersons+" persons.", 'Warning');
                        $(this).val(personValidationItem.miniPersons)
                        $(this).data('prompted', true)
                        return;
                      }
                    }

                    // get the total person set on mandatory options
                    const mandatoryPersonCount = getTotalPersonCount(keys,'default');
                    if (mandatoryPersonCount > personTotalCount) {

                      if (!EXCEED_FLAG) {
                        EXCEED_FLAG = true;
                        showErrorModal(
                          "Please note: It seems you have selected more persons than the set occupants on Bezetting (Occuation) field. However, you may continue if desired.",
                          "Notice"
                        );
                        EXCEED_FLAG = true;
                      }
                    }

                  }

                  // Validate pricing rules
                  const personOptionData = personOptionObject(personValidationItem, $personCount);
                  if (personOptionData) {
                    const parent = $(this).closest('.my-per-person-item');
                    parent.find('.person-option-price').text(personOptionData.amount);
                    let flagText = personOptionData.flag === true ? 'selected' : '';
                    parent.find('.person-option-label-flag').html(`<em>${flagText}</em>`);
                    parent.find('.edit-checkbox').val(personOptionData.flag);
                  }
                });

                $personOption.off(`change.personOption${namespace}`).on(`change.personOption${namespace}`, function (e) {
                  const personTotalCount = parseInt($("#edit-field-bezetting-0-value", context).val());
                  const personValidationItem = personValidationData[key][0] ?? {};
                  const $personCount = parseInt($(this).val());

                  if ($(this).hasClass('default')) {
                    // validate person item person count
                    if ($personCount < personValidationItem.miniPersons) {
                      showErrorModal("This person '"+ personValidationItem.name +"' option requires minimum of "+personValidationItem.miniPersons+" persons.", 'Warning');
                      $(this).val(personValidationItem.miniPersons)
                      return;
                    }
                  }
                });

              }
            });
          }

        }

        function getCurrencyNumber(amount, currency) {
          const formatted = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency.toUpperCase(),
          }).format(amount);
          return formatted;
        }

        function getCurrencySymbol(currency) {
          const formatter = new Intl.NumberFormat('en', {
            style: 'currency',
            currency: 'EUR',
            currencyDisplay: 'narrowSymbol',
          });
          const parts = formatter.formatToParts(1.00);
          const symbol = parts.find(p => p.type === 'currency')?.value;
          return symbol;
        }

        function personOptionObject(personValidationItem, personCount) {
          let object = {
            sameAmount: personValidationItem.amount,
            amount: personValidationItem.amount,
            count: personCount,
            name: personValidationItem.name,
            flag: (!personCount) !== true,
            currency: personValidationItem.currency,
            isSeasonal: false,
            seasonals: [],
            seasonal: {}
          }
          const dates = getBookedDateTimes();

          Object.values(personValidationItem.bookingAdvanceRules).forEach(( rule) => {
             if (personCount >= rule.miniPersons) {
               object = {
                 amount: rule.amount,
                 count: personCount,
                 name: personValidationItem.name,
                 flag: (!personCount) !== true,
                 currency: personValidationItem.currency,
                 isSeasonal: false,
                 seasonals: [],
                 seasonal: {},
                 sameAmount: rule.amount
               }
             }
          })

          Object.values(personValidationItem.seasonal_rules).forEach((rule)=>{

            if (rule?.date_range) {
              if (isBookingDatesInSeasonalRange(dates, rule.date_range[0])) {
                object.amount = rule.amount
                object.isSeasonal = true;
                object.seasonal = rule;
              }
              object.seasonals.push(rule);
            }

          })

          return object;

        }

        function getBookedDateTimes() {
          let $datetime = $('#edit-field-date-booking-0',context);
          let $datetimeStart = $datetime.find('#edit-field-date-booking-0-time-wrapper-value-date, #edit-field-date-booking-0-time-wrapper-value-time',context);
          let $datetimeEnd = $datetime.find('#edit-field-date-booking-0-time-wrapper-end-value-date, #edit-field-date-booking-0-time-wrapper-end-value-time',context);

          let start = '';
          let end = '';
          $datetimeStart.each(function(index, element) {
            start += ' '+ $(element).val();
          });
          $datetimeEnd.each(function(index, element) {
            end += ' '+ $(element).val();
          });
          start = start.trim();
          end = end.trim();
          return {start, end};
        }

        function getSelectedAdditionalServices(addtionalServices) {
          const keys = Object.keys(addtionalServices);
          let selectedAdditionalServices = [];
          keys.forEach(key => {
            const checkbox = $(`input[name="${key}"]`, context);
            if (checkbox.is(':checked')) {
              const $serviceCount = parseInt($(`input[name="${key}_count"]`, context).val());
              if ($serviceCount) {
                if ($serviceCount >= addtionalServices[key].mini) {
                  const symbol = getCurrencySymbol(addtionalServices[key].currency);
                  selectedAdditionalServices.push({
                    ...addtionalServices[key],
                    totalCount: $serviceCount,
                    totalAmount: $serviceCount * addtionalServices[key].amount,
                    symbol,
                  });
                }else {
                  showErrorModal(`Service ${addtionalServices[key].name} was ignored in summary calculation due to not meeting minimum requirment`, 'Warning')
                }

              }

            }
          });
          return selectedAdditionalServices;
        }

        function getSelectedOvernightRooms(overnightRooms) {
          let keys = Object.keys(overnightRooms);
          if (keys.length > 0) {
            const selectedOvernightRooms = [];
            keys.forEach((key)=>{
              let $checkbox = $(`#${key}`,context);
              let $count = parseInt($(`#${key}_count`,context).val());
              let $nights = parseInt($(`#${key}_night`,context).val());
              if ($checkbox && $count) {
                if($checkbox.is(':checked'))
                selectedOvernightRooms.push({
                  ...overnightRooms[key],
                  totalCount: $count,
                  totalAmount: ($nights * overnightRooms[key].amount) * $count,
                  nights: $nights,
                  symbol: getCurrencySymbol(overnightRooms[key].currency),
                })
              }
            });
            return selectedOvernightRooms;
          }
          return [];
        }

        function getHourDifference(start, end) {
          // Ensure the datetime format is compatible with Date constructor
          const startDate = new Date(start.replace(' ', 'T'));
          const endDate = new Date(end.replace(' ', 'T'));

          // Check for invalid dates
          if (isNaN(startDate) || isNaN(endDate)) {
            console.warn('Invalid date format');
            return null;
          }

          // Calculate difference in milliseconds and convert to hours
          const diffMs = endDate - startDate;
          return diffMs / (1000 * 60 * 60);
        }

        function hidPricingTypeOptions() {
          let $pricingType = $('#edit-field-room-pricing-settings-wrapper',context);
          if ($pricingType.length) {
            let entries = $pricingType.find('.field--name-field-pricing-type');
            entries = entries.find('li');
            let values = [];
            entries.each(function(index, element) {
              if ($(element).hasClass('selected')) {
                values.push($(element).text().trim().toLowerCase());
              }
            });
            entries.each(function(index, element) {
              if (values.includes('per hour')) {
                 if ($(element).text().trim().toLowerCase() === 'per hour' && !$(element).hasClass('selected')) {
                   $(element).hide();
                 }
              }
              if (values.includes('per day')) {
                if ($(element).text().trim().toLowerCase() === 'per day' && !$(element).hasClass('selected')) {
                  $(element).hide();
                }
              }
            })
          }
        }

        function modalInit() {
          let css = `<style>
.page-node-type-zaal {
 .pricing-summary {
  padding: 1rem;
  background-color: #f8f9fa;
  border-radius: 8px;
  font-family: sans-serif;
}

.pricing-summary h3, .pricing-summary h4 {
  margin-bottom: 0.5rem;
  color: #333;
}

.pricing-summary ul {
  list-style: none;
  padding-left: 0;
  margin-bottom: 1rem;
}

.pricing-summary li {
  margin: 0.3rem 0;
}
}
</style>`;
          let $modal = `<div id="error-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:20px; border-radius:8px; max-width:500px; width:90%; position:relative; margin: auto; margin-top: 10%;">
    <span id="error-modal-close" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:18px;">&times;</span>
    <h3 id="type" style="margin-top:0; color:#d00;"></h3>
    <div id="error-modal-message"></div>
  </div>
</div>`;
          $('body',context).append($modal);
          $('head',context).append(css);

          $(document).on('click', '#error-modal-close', function () {
            $('#error-modal').fadeOut();
          });

          $(document).on('click', '#error-modal', function (e) {
            if (e.target.id === 'error-modal') {
              $(this).fadeOut();
            }
          });
        }

        function showErrorModal(message, type = 'Error') {
          const $modal = $('#error-modal');
          const $msg = $('#error-modal-message');

          if (!$modal.length) {
            console.error('Error modal container not found in DOM.');
            return;
          }
          $modal.find('#type').text(type);
          $msg.html(message);
          $modal.fadeIn();
        }

        if ($('input[name="field_room_pricing_settings_add_more"]',context).length) {
          setTimeout(() => {
            hidPricingTypeOptions();
          }, 1000);
        }

        calculationCheckBoxesToggler();
        pricingRulesValidation();
        modalInit();

        if($("#section-overnight",context).length) {
          setTimeout(()=>{
            if ($("#section-overnight",context).hasClass('upgrade-vip')) {
              let html = $("#section-overnight",context).find(".upgrade-plan");
              if (html) {
                let cloneHtml = html.clone();
                let htmlThis = "<p>Heads up:</p>"+html.get(0).outerHTML;
                const now = Math.floor(Date.now() / 1000); // Current time in seconds
                const savedTime = window.localStorage.getItem('show_next_popup');

                if (!savedTime || now > parseInt(savedTime)) {
                  showErrorModal(htmlThis, "Alert");

                  // Set next time to show popup: 2 hours from now
                  const twoHoursLater = now + 2 * 60 * 60;
                  window.localStorage.setItem('show_next_popup', twoHoursLater.toString());
                }
              }
            }
          },3000)
        }
      });
    }
  };
})(jQuery, Drupal);

