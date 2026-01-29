/**
 * @file
 * Attaches behaviors for the Reservation module's "new" indicator.
 *
 * May only be loaded for authenticated users, with the History module
 * installed.
 */

(function (Drupal, window) {
  /**
   * Processes the markup for "new reservation" indicators.
   *
   * @param {NodeList} placeholders
   *   The elements that should be processed.
   */
  function processReservationNewIndicators(placeholders) {
    let isFirstNewReservation = true;
    const newReservationString = Drupal.t('new');

    placeholders.forEach((placeholder) => {
      const timestamp = parseInt(placeholder.getAttribute('data-reservation-timestamp'), 10);
      const node = placeholder.closest('[data-history-node-id]');
      const nodeID = node.getAttribute('data-history-node-id');
      const lastViewTimestamp = Drupal.history.getLastRead(nodeID);

      if (timestamp > lastViewTimestamp) {
        placeholder.classList.remove('hidden');
        placeholder.textContent = newReservationString;
        const reservationNode = placeholder.closest('.js-reservation');
        reservationNode.classList.add('new');

        if (isFirstNewReservation) {
          isFirstNewReservation = false;
          reservationNode.insertAdjacentHTML('beforebegin', '<a id="new"></a>');
          if (window.location.hash === '#new') {
            window.scrollTo(
              0,
              reservationNode.getBoundingClientRect().top + window.pageYOffset - Drupal.displace.offsets.top,
            );
          }
        }
      }
    });
  }

  /**
   * Renders "new" reservation indicators wherever necessary.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches "new" reservation indicators behavior.
   */
  Drupal.behaviors.reservationNewIndicator = {
    attach(context) {
      const nodeIDs = [];
      const placeholders = Array.from(context.querySelectorAll('[data-reservation-timestamp]')).filter((placeholder) => {
        const reservationTimestamp = parseInt(placeholder.getAttribute('data-reservation-timestamp'), 10);
        const node = placeholder.closest('[data-history-node-id]');
        const nodeID = node.getAttribute('data-history-node-id');
        if (Drupal.history.needsServerCheck(nodeID, reservationTimestamp)) {
          nodeIDs.push(nodeID);
          return true;
        }
        return false;
      });

      if (placeholders.length === 0) {
        return;
      }

      Drupal.history.fetchTimestamps(nodeIDs, () => {
        processReservationNewIndicators(placeholders);
      });
    },
  };
})(Drupal, window);

