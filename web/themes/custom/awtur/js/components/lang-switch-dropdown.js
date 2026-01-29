  (function (Drupal) {
    Drupal.behaviors.langSwitchDropdown = {
      attach: function (context, settings) {
        
        once('langSwitchDropdown', '.dropdown', context).forEach(() => {

        const dropdown = document.querySelector(".dropdown");
        if (!dropdown) return;
  
        const activeLangCode = dropdown.querySelector(".active-lang-code");
        const activeLangLink = dropdown.querySelector(".language-link.is-active");
        const flagIcon = dropdown.querySelector(".flag-icon"); 
  
        if (activeLangLink && activeLangCode && flagIcon) {
          const hreflangValue = activeLangLink.getAttribute("hreflang").toLowerCase();
          if (hreflangValue) {
            // Tekst: "NL", "FR", etc.
            activeLangCode.textContent = hreflangValue.toUpperCase();
  
            // Vlag: kies op basis van hreflang
            // Als we geen match vinden, eventueel fallback:
            const flags = {
              nl: 'flag-nl.svg',
              fr: 'flag-fr.svg',
              en: 'flag-gb.svg',
              de: 'flag-de.svg',
            };
            const flagSrc = flags[hreflangValue] || 'flag-globe-lang.svg';
            const baseUrl = drupalSettings.path.baseUrl;
            flagIcon.src = baseUrl + '/themes/custom/awtur/images/flags/' + flagSrc;
            flagIcon.alt = hreflangValue.toUpperCase();  
          }
        }
       });
      }
    };
  })(Drupal);
  