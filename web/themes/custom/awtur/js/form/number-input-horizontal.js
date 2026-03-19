(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.awturNumberInputsHorizontal = {
    attach: function (context) {

      const elements = once('awtur-horizontal', context.querySelectorAll('.overnight-room-parent input[type="number"], .reservation-service-count input[type="number"], .person-option-count input[type="number"]'));

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

        // logic
        function step(delta) {

          const stepAttr = input.getAttribute('step');
          const step = stepAttr && stepAttr !== 'any' ? parseFloat(stepAttr) : 1;

          const min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : 0;
          const max = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : Infinity;

          // current value (default ya existing)
          const raw = input.value === '' ? min : parseFloat(input.value);
          const current = isNaN(raw) ? min : raw;

          let next = current + (delta * step);

          // decimal precision handle
          const precision = (step.toString().split('.')[1] || '').length;
          if (precision > 0) {
            const factor = Math.pow(10, precision);
            next = Math.round(next * factor) / factor;
          }

          // clamp min/max
          if (next < min) next = min;
          if (next > max) next = max;

          input.value = next;

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