/**
 * @file
 * Attaches reservation behaviors to the entity form.
 */

(function ($, Drupal) {
  /**
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.reservationFieldsetSummaries = {
    attach(context) {
      const $context = $(context);
      $context
        .find('fieldset.reservation-entity-settings-form')
        .drupalSetSummary((context) =>
          Drupal.checkPlain(
            $(context)
              .find('.js-form-item-reservation input:checked')
              .next('label')
              .text(),
          ),
        );
    },
  };
})(jQuery, Drupal);
