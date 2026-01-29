/*hover-delay-menu.js*/

(function (Drupal) {
    Drupal.behaviors.hoverDelayMenu = {
      attach: function (context) {
        const menuBlock = context.querySelector('#block-awtur-account-menu');
        if (!menuBlock) return;
  
        const subMenu = menuBlock.querySelector('ul.clearfix, ul.menu');
        if (!subMenu) return;
  
        let hideTimer;
  
        menuBlock.addEventListener('mouseenter', () => {
          clearTimeout(hideTimer);
          subMenu.style.display = 'block';
        });
  
        menuBlock.addEventListener('mouseleave', () => {
          hideTimer = setTimeout(() => {
            subMenu.style.display = 'none';
          }, 400);
        });
      }
    };
  })(Drupal);