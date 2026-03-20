(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.awturNumberInputsHorizontal = {
    attach: function (context) {

      const elements = once(
        'awtur-horizontal',
        context.querySelectorAll('.overnight-room-parent input[type="number"], .reservation-service-count input[type="number"], .person-option-count input[type="number"]')
      );

      elements.forEach(function (input) {

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


        if (input.value === '') {
          if (input.defaultValue !== '') {
            input.value = input.defaultValue;
          } else if (input.min !== '') {
            input.value = input.min;
          }
        }

        // step logic
        function step(delta) {
          let value;

          // Priority: current → default → min → 0
          if (input.value !== '') {
            value = parseInt(input.value);
          } else if (input.defaultValue !== '') {
            value = parseInt(input.defaultValue);
          } else if (input.min !== '') {
            value = parseInt(input.min);
          } else {
            value = 0;
          }

          value += delta;

          const min = input.min !== '' ? parseInt(input.min) : 0;
          const max = input.max !== '' ? parseInt(input.max) : null;

          if (value < min) value = min;
          if (max !== null && value > max) value = max;

          input.value = value;

          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // HOLD LOGIC
        let hold;
        let isHolding = false;

        function start(delta) {
          isHolding = true;
          step(delta);
          hold = setInterval(() => step(delta), 150);
        }

        function stop() {
          clearInterval(hold);
          setTimeout(() => {
            isHolding = false;
          }, 50);
        }

        // CLICK (only if not holding)
        btnUp.addEventListener('click', () => {
          if (!isHolding) step(1);
        });

        btnDown.addEventListener('click', () => {
          if (!isHolding) step(-1);
        });

        // HOLD EVENTS
        btnUp.addEventListener('mousedown', () => start(1));
        btnDown.addEventListener('mousedown', () => start(-1));

        document.addEventListener('mouseup', stop);

        btnUp.addEventListener('mouseleave', stop);
        btnDown.addEventListener('mouseleave', stop);

        // MOBILE SUPPORT
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