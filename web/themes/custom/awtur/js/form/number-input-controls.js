/**
 * number-input-controls.js
 * Drupal behavior to replace native number spinners with styled custom controls.
 *
 * Targets:
 * - .zaal-form input[type="number"]
 * - .bedrijf-form input[type="number"]
 *
 * File name suggestion: number-input-controls.js
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.awturNumberInputs = {
    attach: function (context) {
      const selector = '.zaal-form input[type="number"], .bedrijf-form input[type="number"]';
      const elements = once('awtur-number-input', selector, context);

      elements.forEach(function (input) {
        // avoid double-wrapping
        if (input.dataset.awturWrapped) {
          return;
        }

        input.dataset.awturWrapped = '1';

        // Create wrapper
        const wrapper = document.createElement('span');
        wrapper.className = 'awtur-number-wrap';
        // Move input into wrapper
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // Add custom controls container
        const controls = document.createElement('span');
        controls.className = 'awtur-number-controls';
        controls.innerHTML = '<button type="button" class="awtur-ni-btn awtur-ni-up" aria-label="Increase" title="Increase" tabindex="-1">▲</button>' +
                             '<button type="button" class="awtur-ni-btn awtur-ni-down" aria-label="Decrease" title="Decrease" tabindex="-1">▼</button>';
        wrapper.appendChild(controls);

        const btnUp = controls.querySelector('.awtur-ni-up');
        const btnDown = controls.querySelector('.awtur-ni-down');

        // Function to step value respecting min/max and step
        function stepInput(delta) {
          const stepAttr = input.getAttribute('step');
          const step = stepAttr && stepAttr !== 'any' ? parseFloat(stepAttr) : 1;
          const min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : -Infinity;
          const max = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : Infinity;

          // parse current value or fall back to 0
          const raw = input.value === '' ? 0 : parseFloat(input.value);
          const current = isNaN(raw) ? 0 : raw;

          let next = current + (delta * step);

          // Clamp respecting precision of step
          const precision = (step.toString().split('.')[1] || '').length;
          if (isFinite(precision) && precision > 0) {
            const factor = Math.pow(10, precision);
            next = Math.round(next * factor) / factor;
          }

          if (next > max) next = max;
          if (next < min) next = min;

          input.value = String(next);
          // trigger events so other scripts react
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Click handlers (single click)
        btnUp.addEventListener('click', function (e) {
          e.preventDefault();
          stepInput(+1);
          input.focus();
        });
        btnDown.addEventListener('click', function (e) {
          e.preventDefault();
          stepInput(-1);
          input.focus();
        });

        // Support press-and-hold
        let holdTimer = null;
        function startHold(delta) {
          // step immediately
          stepInput(delta);
          // start a repeating interval after short delay
          holdTimer = setTimeout(function () {
            holdTimer = setInterval(function () {
              stepInput(delta);
            }, 80);
          }, 250);
        }
        function clearHold() {
          if (holdTimer) {
            clearTimeout(holdTimer);
            clearInterval(holdTimer);
            holdTimer = null;
          }
        }

        btnUp.addEventListener('mousedown', function (e) { e.preventDefault(); startHold(+1); });
        btnDown.addEventListener('mousedown', function (e) { e.preventDefault(); startHold(-1); });
        document.addEventListener('mouseup', clearHold);
        btnUp.addEventListener('mouseleave', clearHold);
        btnDown.addEventListener('mouseleave', clearHold);
        // touch support
        btnUp.addEventListener('touchstart', function (e) { e.preventDefault(); startHold(+1); }, { passive: false });
        btnDown.addEventListener('touchstart', function (e) { e.preventDefault(); startHold(-1); }, { passive: false });
        document.addEventListener('touchend', clearHold);

        // Keep layout if the input already has width or size, else set a default
        if (!input.style.width && !input.getAttribute('size')) {
          // let the CSS set width; do not override
        }

        // Remove native spin buttons (redundant CSS but keep as fallback)
        input.classList.add('awtur-hide-native-spinner');
      });
    }
  };

})(Drupal, once);
