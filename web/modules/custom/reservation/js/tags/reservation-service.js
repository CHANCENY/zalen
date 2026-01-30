(function($){

  document.addEventListener('DOMContentLoaded', function(){
    var reuseService = document.querySelector("#edit-field-resuse-menu-and-services-wrapper");

    if (reuseService) {

      var selectedParagraphIds = [];
      var lastLength = null;

      /**
       * Collects paragraph ids from the input fields.
       * @param {NodeList<Element>} inputFields
       */
      function collectParagraphIds(inputFields){
        const selectedParagraphIds = [];
        inputFields.forEach(function(inputField){
          const value = inputField.value;
          if (value.length > 0) {
            let id = value.split('(');
            id = id[id.length - 1];
            id = id.split(')')[0].trim();

            if (parseInt(id) > 0 && !isAlreadySelected(id, selectedParagraphIds)) {
              selectedParagraphIds.push({
                id: inputField.getAttribute('data-drupal-selector'),
                value: id,
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
      async function buildSelectedParagraphsPreview(selectedParagraphIds){

        if (lastLength !== selectedParagraphIds.length) {
          lastLength = selectedParagraphIds.length;
          const response = await fetch('/reservation/room/menus-services/paragraph', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(selectedParagraphIds)
          });

          const data = await response.json();
          console.log(data);

        }

      }

      setInterval(function (){

        var inputFields = reuseService.querySelectorAll("input[type='text']");
        selectedParagraphIds = collectParagraphIds(inputFields);
        buildSelectedParagraphsPreview(selectedParagraphIds);
      }, 2000);

    }








  })

})(jQuery);
