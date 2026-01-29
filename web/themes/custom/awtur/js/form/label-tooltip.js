/*
(function ($, Drupal, once) {
    Drupal.behaviors.labelTooltips = {
      attach: function (context) {
        once('labelTooltips', '.bedrijf-form .form-checkboxes label', context).forEach(function (labelEl) {
          var labelText = labelEl.textContent.trim();
          labelEl.setAttribute('title', labelText);
        });
      }
    };
  })(jQuery, Drupal, once);
  */

 /* (function ($, Drupal, once) {
    Drupal.behaviors.labelEllipsisTooltips = {
      attach: function (context) {
        once('labelEllipsis', '.bedrijf-form .form-checkboxes .form-item', context).forEach(function (itemEl) {
          let labelEl = itemEl.querySelector('label');
          if (labelEl) {
            let fullText = labelEl.textContent.trim();
            itemEl.setAttribute('data-full-text', fullText);
          }
        });
      }
    };
  })(jQuery, Drupal, once);*/

  /*(function ($, Drupal, once) {
    Drupal.behaviors.labelEllipsisTooltips = {
      attach: function (context) {
  
        // 1) Zet de volledige labeltekst in data-full-text
        once('labelEllipsis', '.bedrijf-form .form-checkboxes .form-item', context).forEach(function (itemEl) {
          let labelEl = itemEl.querySelector('label');
          if (labelEl) {
            let fullText = labelEl.textContent.trim();
            itemEl.setAttribute('data-full-text', fullText);
          }
        });
  
        // 2) Toevoegen van click-handler om de tooltip te tonen
        //    en na 0,5s weer te verbergen.
        once('tooltipClickInit', '.bedrijf-form .form-checkboxes', context).forEach(function (containerEl) {
          containerEl.addEventListener('click', function (e) {
            // Zoek de dichtstbijzijnde .form-item
            let itemEl = e.target.closest('.form-item[data-full-text]');
            if (!itemEl) {
              return; // Klikte niet op een checkbox/label-combinatie
            }
            // Tooltip tonen
            itemEl.classList.add('show-tooltip');
  
            // Na 500ms tooltip weer verbergen
            setTimeout(function () {
              itemEl.classList.remove('show-tooltip');
            }, 1000);
          });
        });
      }
    };
  })(jQuery, Drupal, once);*/

 /* (function ($, Drupal, once) {
    Drupal.behaviors.labelEllipsisTooltips = {
      attach: function (context) {
        
        // 1) Voer 1x code uit om data-full-text te zetten
        once('labelEllipsis', '.bedrijf-form .form-checkboxes .form-item', context).forEach(function (itemEl) {
          let labelEl = itemEl.querySelector('label');
          if (labelEl) {
            let fullText = labelEl.textContent.trim();
            itemEl.setAttribute('data-full-text', fullText);
          }
        });
  
        // 2) Voeg 1x een click listener toe aan de container
        once('tooltipClickInit', '.bedrijf-form .form-checkboxes', context).forEach(function (containerEl) {
          containerEl.addEventListener('click', function (e) {
            // Zoek het dichtstbijzijnde .form-item met data-full-text
            let itemEl = e.target.closest('.form-item[data-full-text]');
            if (!itemEl) {
              return; // Gebruiker klikte niet op een label/checkbox
            }
  
            // Voeg de class .show-tooltip toe
            itemEl.classList.add('show-tooltip');
  
            // Verwijder de class na 0,5s => tooltip verdwijnt
            setTimeout(function () {
              // Check of de muis nog hovered? 
              // => Als user op desktop is en hovert, tooltip blijft via :hover
              // => Je kunt gewoon class verwijderen:
              itemEl.classList.remove('show-tooltip');
            }, 1000);
          });
        });
      }
    };
  })(jQuery, Drupal, once);*/

  (function ($, Drupal, once) {
    Drupal.behaviors.labelEllipsisTooltips = {
      attach: function (context) {
        // 1) Data-full-text instellen voor zowel bedrijf- als zaalformulieren
        once('labelEllipsis', '.node-bedrijf-form .form-checkboxes .form-item, .node-zaal-form .form-checkboxes .form-item', context).forEach(function (itemEl) {
          let labelEl = itemEl.querySelector('label');
          if (labelEl) {
            let fullText = labelEl.textContent.trim();
            itemEl.setAttribute('data-full-text', fullText);
          }
        });
  
        // 2) Click listener op beide checkbox-containers
        once('tooltipClickInit', '.node-bedrijf-form .form-checkboxes, .node-zaal-form .form-checkboxes', context).forEach(function (containerEl) {
          containerEl.addEventListener('click', function (e) {
            let itemEl = e.target.closest('.form-item[data-full-text]');
            if (!itemEl) {
              return;
            }
            itemEl.classList.add('show-tooltip');
            setTimeout(function () {
              itemEl.classList.remove('show-tooltip');
            }, 1000);
          });
        });
      }
    };
  })(jQuery, Drupal, once);
  
  