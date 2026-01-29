(function (Drupal) {
    Drupal.behaviors.langDropdownDelay = {
      attach: function (context) {
        // Zoeken we alle dropdowns met class .dropdown?
        // Of heb je er maar één? Dan querySelector ipv querySelectorAll.
        const dropdowns = context.querySelectorAll('.dropdown');
        dropdowns.forEach(dropdown => {
          const content = dropdown.querySelector('.dropdown-content');
          if (!content) return;
  
          let hideTimer;
  
          // Muis binnen: toon direct
          dropdown.addEventListener('mouseenter', () => {
            clearTimeout(hideTimer);
            content.style.display = 'block';
          });
  
          // Muis buiten: stel verbergen uit met b.v. 400ms
          dropdown.addEventListener('mouseleave', () => {
            hideTimer = setTimeout(() => {
              content.style.display = 'none';
            }, 400);
          });
        });
      }
    };
  })(Drupal);
  