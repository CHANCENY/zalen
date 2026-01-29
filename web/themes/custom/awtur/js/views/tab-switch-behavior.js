(function (Drupal, once) {
    Drupal.behaviors.exposedTabsBehavior = {
      attach: function (context, settings) {
        once('exposedTabsBehavior', 'body', context).forEach(() => {
  
          document.querySelectorAll('.gelegenheidsTab').forEach(tab => {
            tab.addEventListener('click', function(evt) {
              var i, tabContent, tabButtons;
              
              const gelegenheidTonen = tab.getAttribute('data-target'); 
                
              // Hide all tab-subject
              tabContent = document.getElementsByClassName('tabgelegenheid');
              for (i = 0; i < tabContent.length; i++) {
                tabContent[i].style.display = 'none';
              }
  
              // Delete .active from all tabs
              tabButtons = document.getElementsByClassName('gelegenheidsTab');
              for (i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
              }
  
              // Show selected tab + .active
              document.getElementById(gelegenheidTonen).style.display = 'block';
              evt.currentTarget.classList.add('active');
            });
          });
  
          // Simulate DOMContentLoaded
          const feestCheck = document.getElementById('feestCheck');
          if (feestCheck) {
            feestCheck.click();
          }
        });
      }
    };
  })(Drupal, once);
  