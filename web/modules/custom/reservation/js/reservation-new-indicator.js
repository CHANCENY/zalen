(function (Drupal, window) {
  function processReservationNewIndicators(placeholders) {
    let isFirstNewReservation = true;
    const newReservationString = Drupal.t('new');

    placeholders.forEach(function (placeholder) {
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
            window.scrollTo(0, reservationNode.getBoundingClientRect().top + window.pageYOffset - Drupal.displace.offsets.top);
          }
        }
      }
    });
  }

  Drupal.behaviors.reservationNewIndicator = {
    attach: function attach(context) {
      const nodeIDs = [];
      const placeholders = Array.from(context.querySelectorAll('[data-reservation-timestamp]')).filter(function (placeholder) {
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

      Drupal.history.fetchTimestamps(nodeIDs, function () {
        processReservationNewIndicators(placeholders);
      });
    }
  };
})(Drupal, window);

