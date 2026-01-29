/**
 * @file
 * Attaches behaviors for the Reservation module's "by-viewer" class.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Add 'by-viewer' class to reservations written by the current user.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.reservationByViewer = {
    attach(context) {
      const currentUserID = parseInt(drupalSettings.user.uid, 10);
      $('[data-reservation-user-id]')
        .filter(function () {
          return (
            parseInt(this.getAttribute('data-reservation-user-id'), 10) ===
            currentUserID
          );
        })
        .addClass('by-viewer');
    },
  };
})(jQuery, Drupal, drupalSettings);
