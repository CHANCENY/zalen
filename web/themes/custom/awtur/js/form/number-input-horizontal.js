(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.awturNumberInputsHorizontal = {
    attach: function (context) {

      const elements = once('awtur-horizontal', context.querySelectorAll('.overnight-room-parent input[type="number"]'));

      elements.forEach(function (input) {

        // avoid conflict with old script
        if (input.closest('.awtur-number-wrap')) return;

        // wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'qty-stepper';

        input.parentNode.insertBefore(wrapper, input);

        // buttons
        const btnDown = document.createElement('button');
        btnDown.type = 'button';
        btnDown.className = 'qty-btn qty-minus';
        btnDown.innerHTML = '−';

        const btnUp = document.createElement('button');
        btnUp.type = 'button';
        btnUp.className = 'qty-btn qty-plus';
        btnUp.innerHTML = '+';

        // structure
        wrapper.appendChild(btnDown);
        wrapper.appendChild(input);
        wrapper.appendChild(btnUp);

        // logic
        function step(delta) {
          let value = parseInt(input.value) || 0;
          value += delta;

          if (value < 0) value = 0;

          // format 01, 02
          input.value = String(value).padStart(2, '0');

          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        btnUp.addEventListener('click', () => step(1));
        btnDown.addEventListener('click', () => step(-1));

        // hold support
        let hold;
        function start(delta) {
          step(delta);
          hold = setInterval(() => step(delta), 120);
        }
        function stop() {
          clearInterval(hold);
        }

        btnUp.addEventListener('mousedown', () => start(1));
        btnDown.addEventListener('mousedown', () => start(-1));
        document.addEventListener('mouseup', stop);

        btnUp.addEventListener('mouseleave', stop);
        btnDown.addEventListener('mouseleave', stop);

        // mobile
        btnUp.addEventListener('touchstart', (e) => {
          e.preventDefault();
          start(1);
        }, { passive: false });

        btnDown.addEventListener('touchstart', (e) => {
          e.preventDefault();
          start(-1);
        }, { passive: false });

        document.addEventListener('touchend', stop);

      });
    }
  };

})(Drupal, once);