(function (Drupal) {
    Drupal.behaviors.myPerPersonToggle = {
      attach: function (context, settings) {

        // Zoek alle .person-option-toggle links die nog niet met once(...) verwerkt zijn
        const toggles = context.querySelectorAll('.person-option-toggle');
        toggles.forEach((toggle) => {
          toggle.addEventListener('click', function (e) {
            e.preventDefault();

            // Haal target-id uit data-target
            const targetSelector = toggle.getAttribute('data-target');
            const desc = document.querySelector(targetSelector);

            if (desc) {
              // toggle .expanded
              desc.classList.toggle('expanded');
              // verander linktekst
              if (desc.classList.contains('expanded')) {
                toggle.textContent = Drupal.t('Lees minder');
              } else {
                toggle.textContent = Drupal.t('Lees meer');
              }
            }
          });
        });
      }
    };
  })(Drupal);
