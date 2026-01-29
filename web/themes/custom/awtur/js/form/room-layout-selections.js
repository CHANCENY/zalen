/*"..\web\themes\custom\awtur\js\form\room-layout-selection.js" */

(function (Drupal, once) {
  'use strict';

  /**
   * Room layout selection behavior.
   *
   * - Works with AJAX (Entity Browser) because it uses Drupal.behaviors and once().
   * - Prevents double-toggle by ignoring clicks inside the label that already toggle the checkbox.
   */
  Drupal.behaviors.roomLayoutSelection = {
    attach: function (context, settings) {
      // Use once to initialize each card exactly once per page/context.
      once('room-layout-selection', '.room-layout-card', context).forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        // Optional: find the label that wraps the checkbox (if present).
        const label = card.querySelector('label.room-layout-select');

        // Set initial visual state.
        card.classList.toggle('selected', checkbox.checked);

        // Avoid binding multiple listeners if somehow re-run (defensive).
        if (card.dataset.rlsInitialized) {
          return;
        }
        card.dataset.rlsInitialized = '1';

        // Clicking the card toggles the checkbox, EXCEPT when clicking the label (label toggles by browser).
        card.addEventListener('click', function (e) {
          // If click directly on the checkbox, let the browser handle it.
          if (e.target === checkbox) {
            return;
          }
          // If click is inside the label that wraps the checkbox, ignore because the label already toggles it.
          if (label && label.contains(e.target)) {
            return;
          }
          // Also ignore clicks on links or buttons inside the card.
          if (e.target.closest && (e.target.closest('a') || e.target.closest('button'))) {
            return;
          }

          // Toggle programmatically and dispatch change.
          checkbox.checked = !checkbox.checked;
          checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Update visual state when checkbox changes (this also covers label clicks).
        checkbox.addEventListener('change', function () {
          card.classList.toggle('selected', checkbox.checked);
        });
      });
    }
  };

})(Drupal, once);


/*document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.room-layout-card').forEach(card => {
      const checkbox = card.querySelector('input[type="checkbox"]');
      if (checkbox.checked) {
        card.classList.add('selected');
      }
      card.addEventListener('click', e => {
        if (e.target === checkbox) return; 
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change'));
      });
      checkbox.addEventListener('change', () => {
        card.classList.toggle('selected', checkbox.checked);
      });
    });
  });*/
