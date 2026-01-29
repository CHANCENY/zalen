(function (Drupal, once) {
  Drupal.behaviors.stylesumo = {
    attach: function (context, settings) {
      setTimeout(() => {
         const placeholders = {
          'edit-commerciele-gelegenheden-keuze': 'Commerciële gelegenheid',
          'edit-concert-gelegenheden-keuze': 'Kies entertainment',
          'edit-feest-gelegenheid-keuze': 'Feestgelegenheid',
          'edit-vergader-gelegenheden-keuze': 'Vergader gelegenheid'
        };

        once('stylesumo', '.form-select', context).forEach((el) => {
          const drupalSelector = el.getAttribute('data-drupal-selector');
          
          let placeholder = 'Selecteer';
          if (drupalSelector && placeholders[drupalSelector]) {
            placeholder = placeholders[drupalSelector];
          }

          jQuery(el).SumoSelect({
            floatWidth: 300,
            forceCustomRendering: true,
            csvDispCount: 2,
            placeholder: placeholder,
            clearAll: true,
            captionFormat: '{0} gekozen',
            captionFormatAllSelected: 'alles gekozen ({0})',
          });
        });
      }, 100);
    }
  };
})(Drupal, once);
