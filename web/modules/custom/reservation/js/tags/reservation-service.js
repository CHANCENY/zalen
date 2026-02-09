(function($){

  document.addEventListener('DOMContentLoaded', function(){
    var reuseService = document.querySelector("#edit-field-resuse-menu-and-services--wrapper");

    if (reuseService) {

      var selectedParagraphIds = [];
      var lastLength = null;
      var isLoading = false;
      var eventsAttached = [];

      /**
       * Collects paragraph ids from the input fields.
       * @param {NodeList<Element>} inputFields
       */
      function collectParagraphIds(inputFields){
        const selectedParagraphIds = [];
        inputFields.forEach(function(inputField){
          const value = inputField.value;
          if (value.length > 0 && inputField.checked === true) {
            let id = value;

            if (parseInt(id) > 0 && !isAlreadySelected(id, selectedParagraphIds)) {
              selectedParagraphIds.push({
                id: inputField.getAttribute('data-drupal-selector'),
                value: id,
                text: inputField.value
              });
            }
          }
        });
        return selectedParagraphIds;
      }

      /**
       *
       * @param {number} id
       * @param {Array} paragraphs
       */
      function isAlreadySelected(id, paragraphs) {
        return paragraphs.find(function (item) {
          return item.value === id;
        });
      }

      /**
       * Generates a preview of the selected paragraphs based on the provided paragraph IDs.
       *
       * @param {Array<Object>} selectedParagraphIds
       */
      async function buildSelectedParagraphsPreview(selectedParagraphIds) {

        const currentHash = hashSelectedParagraphIds(selectedParagraphIds);

        if (lastLength !== currentHash) {
          lastLength = currentHash;

          const preview = document.querySelector('#zaal-form-resuse-menus-services');
          if (!preview) return;

          // 1️⃣ Show spinner
          preview.innerHTML = `
      <div class="preview-loading">
        <span class="spinner"></span>
        <small>Loading preview…</small>
      </div>
    `;

          // If nothing selected, clear preview
          if (!selectedParagraphIds.length) {
            preview.innerHTML = '';
            return;
          }

          isLoading = true;

          try {
            let host = window.location.origin;

            if (window.location.pathname.includes('web')) {
              host += "/web"
            }

            const response = await fetch(host+'/reservation/room/menus-services/paragraph', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(selectedParagraphIds)
            });

            if (!response.ok) {
              throw new Error('Request failed');
            }

            const data = await response.json();

            if (data && data.html !== undefined) {
              preview.innerHTML = data.html;
              setTimeout(function () {
               // buildEditEventListeners(preview);
              },2000)
            }

            if (typeof Drupal !== 'undefined' && Drupal.behaviors) {
              // Reattach behaviors to newly added content
              Drupal.attachBehaviors(preview);
            }

          } catch (e) {
            preview.innerHTML = `
        <div class="preview-error">
          Failed to load preview
        </div>
      `;
          } finally {
            isLoading = false;
          }
        }
      }

      /**
       * Attaches event listeners to enable editing functionality for a given preview element.
       *
       * @param {Element} preview - The target element to which the edit event listeners will be added.
       */
      function buildEditEventListeners(preview) {
        const editButtons = preview.querySelectorAll('.service-edit');

        Array.from(editButtons).forEach(function (button) {
          const id = button.getAttribute('data-id');
          if (id){
            button.addEventListener('click',  (e) =>{
              e.preventDefault();
              loadParagraphContent(button.getAttribute('data-paragraph-fields'));
            });
          }
        });
      }

      function loadParagraphContent(data) {

        data = JSON.parse(atob(data));
        let paragraphFormTable = document.querySelector("#edit-field-extra-room-services-wrapper");
        paragraphFormTable = paragraphFormTable.querySelector("tbody");
        const btnMore = document.querySelector("input[name='field_extra_room_services_add_more']");

        if (paragraphFormTable) {

          const paragraphRows = paragraphFormTable.querySelectorAll("tr");
          var populatedFlag = false;
          // check if we can re suse any of the field collection
          Array.from(paragraphRows).forEach(function (row) {

            const details = row.querySelector("details");
            const reusable = areExtraRoomServiceFieldsEmpty(details);
            if (reusable) {
              populatedFlag = true;
              details.open = true;
              hydrateFieldValues(data, details, btnMore);
              return;
            }
          });

          if (!populatedFlag) {
            if (btnMore) {
              btnMore.removeAttribute('disabled');
             triggerParagraphAddMore(btnMore);
              setTimeout(function () {
                const lastParagraphRow = paragraphFormTable.querySelector("tr:last-child");
                const detailsEl = lastParagraphRow.querySelector("details");
                hydrateFieldValues(data, detailsEl, btnMore)
              },1000)
            }
          }

        }
      }

      function areExtraRoomServiceFieldsEmpty(detailsEl) {
        if (!detailsEl) return true; // treat missing details as empty

        const fieldWrappers = [
          'field--name-field-service-short-description',
          // 'field--name-field-service-currency',
          'field--name-field-service-amount'
        ];

        // if any field has a value, return false
        for (const name of fieldWrappers) {
          const wrapper = detailsEl.querySelector(`.${name}`);
          if (!wrapper) continue;

          const input = wrapper.querySelector('input');
          if (input && input.value && input.value.trim() !== '') {
            return false;
          }
        }

        return true;
      }

      /**
       * Populates form fields within a given details element using data from a paragraphFields object.
       *
       * @param {Object} paragraphFields - An object containing key-value pairs that correspond to fields being updated.
       * @param {HTMLElement} detailsEl - The DOM element containing the form fields to be updated.
       * @param {HTMLElement} moreBtn - A button element, if provided, that will be triggered after field hydration.
       * @return {void} - This method does not return a value.
       */
      function hydrateFieldValues(paragraphFields, detailsEl, moreBtn) {
        if (!detailsEl || !paragraphFields) return;

        // Short Description
        const shortDescInput = detailsEl.querySelector(
          '.field--name-field-service-short-description input'
        );
        if (shortDescInput && paragraphFields.field_service_short_description !== undefined) {
          shortDescInput.value = paragraphFields.field_service_short_description;
        }

        // Currency
        const currencySelect = detailsEl.querySelector(
          '.field--name-field-service-currency select'
        );
        if (currencySelect && paragraphFields.field_service_currency !== undefined) {
          currencySelect.value = paragraphFields.field_service_currency;
          // Trigger change if your select library (SumoSelect) needs it
          currencySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Amount
        const amountInput = detailsEl.querySelector(
          '.field--name-field-service-amount input'
        );
        if (amountInput && paragraphFields.field_service_amount !== undefined) {
          amountInput.value = paragraphFields.field_service_amount;
        }

        // Minimum Order
        const minOrderInput = detailsEl.querySelector(
          '.field--name-field-service-minimum-order input'
        );
        if (minOrderInput && paragraphFields.field_service_minimum_order !== undefined) {
          minOrderInput.value = paragraphFields.field_service_minimum_order;
        }

        // Service or Menu (radio buttons)
        if (paragraphFields.field_is_service_or_menu) {
          const radioInput = detailsEl.querySelector(
            `.field--name-field-is-service-or-menu input[value="${paragraphFields.field_is_service_or_menu}"]`
          );
          if (radioInput) {
            radioInput.checked = true;
          }
        }

        // Image FIDs (hidden inputs)
        if (paragraphFields.field_extra_service_image?.length) {
          const fidsInput = detailsEl.querySelector(
            '.field--name-field-extra-service-image input[name*="[fids]"]'
          );
          if (fidsInput) {
            fidsInput.value = paragraphFields.field_extra_service_image
              .map(img => img.target_id)
              .join(',');
          }
        }
      }

      function triggerParagraphAddMore(button) {
        if (!Drupal?.ajax?.instances) {
          return false;
        }
        for (const key in Drupal.ajax.instances) {
          const instance = Drupal.ajax.instances[key];

          // Skip null instances or instances without elements
          if (!instance || !instance.element) {
            continue;
          }

          if (instance.element === button) {
            try {
              instance.execute();
              return true;
            } catch (error) {
              return false;
            }
          }
        }
        return false;
      }

      /**
       *
       * @param {string} element
       * @param {Element} form
       * @returns {Promise<void>}
       */
      async function rebuildForm(element, form) {
        const response = await fetch(`/reservation/room/menus-services/paragraph/rebuild`,{
          method: 'POST',
          body: JSON.stringify(element)
        });
        const data = await response.json();
        if (data?.status) {
          form.querySelector("input[name='form_build_id']").value = data.form_build_id;
       //   form.querySelector("input[name='form_token']").value = data.form_token;
          lastLength = null;
        }
      }

      /**
       * Handles the completion of the editing process by performing necessary updates
       * and operations on the provided input fields. This method may interact with DOM
       * elements or perform additional logic after editing is finished.
       *
       * @param {NodeList<Element>} inputFields - An array containing input field elements that are being edited.
       * @param {Array<Object>} selectedParagraphIds - An array containing objects containing paragraph IDs and values.
       * @return {void} This method does not return a value.
       */
      function editFinished(inputFields,selectedParagraphIds) {
        const listenerNoScript = document.querySelector("#clone-paragraph-new-id");
        if (listenerNoScript?.textContent.length > 2) {
          const json = JSON.parse(listenerNoScript.textContent);
          listenerNoScript.textContent = "";
          const pids = json.pids;
          selectedParagraphIds.forEach(function (inputField) {
             const inputFieldId = inputField.id;
             const oldParagraphId = inputField.value;

             if (parseInt(pids.old) === parseInt(oldParagraphId)) {
               const inputFieldElement = Array.from(inputFields).find(function (el) {
                 return el.getAttribute('data-drupal-selector') === inputFieldId;
               })

               if (inputFieldElement) {

                 if (pids.old === pids.new) {
                   inputField.value = pids.new;
                   const label = document.querySelector(`label[for="${inputField.id}"]`);

                   if (label) {
                     const span = label.querySelector('span');

                     if (span) {
                       span.innerHTML = pids.new + '&nbsp;';
                     }

                     // Replace the remaining label text
                     label.childNodes.forEach(node => {
                       if (node.nodeType === Node.TEXT_NODE) {
                         node.textContent = pids.label;
                       }
                     });
                   }
                 }

                 else {
                   createNewCheckBox(pids);
                   inputFieldElement.checked = false;
                 }

                 const form_build_id = inputFieldElement.form.querySelector("input[name='form_build_id']").value;
                 rebuildForm({form_build_id}, inputFieldElement.form)
               }
             }
          })
        }
      }

      function simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
          hash = ((hash << 5) - hash) + str.charCodeAt(i);
          hash |= 0;
        }
        return hash;
      }

      function hashSelectedParagraphIds(list) {
        const normalized = list
          .map(item => ({
            id: item.id,
            value: String(item.value),
            text: item.text
          }))
          .sort((a, b) => a.id.localeCompare(b.id));

        return simpleHash(JSON.stringify(normalized));
      }

      function createNewCheckBox(pids) {
        const html = `<input data-drupal-selector="edit-field-resuse-menu-and-services-${pids.new}" type="checkbox" id="edit-field-resuse-menu-and-services-${pids.new}"
                              value="${pids.new}" checked="checked"
                              class="form-checkbox">
                              <label for="edit-field-resuse-menu-and-services-${pids.new}" class="option">
                              <span class="views-field views-field-id">${pids.new} &nbsp;</span>${pids.label}</label>
                              <input type="hidden" name="field_resuse_menu_and_services_hidden[]" value="${pids.new}" >`
        const div = document.createElement('div');
        div.className = `js-form-item form-item js-form-type-checkbox form-type-checkbox js-form-item-field-resuse-menu-and-services-${pids.new} form-item-field-resuse-menu-and-services-${pids.new}`
        div.innerHTML = html;
        reuseService.querySelector('#edit-field-resuse-menu-and-services').appendChild(div);
      }

      setInterval(async ()=>{
        const inputFields = reuseService.querySelectorAll("input[type='checkbox']");
        selectedParagraphIds = collectParagraphIds(inputFields);
        await buildSelectedParagraphsPreview(selectedParagraphIds);
        editFinished(inputFields, selectedParagraphIds);
      },1000)

    }
  })

})(jQuery);
