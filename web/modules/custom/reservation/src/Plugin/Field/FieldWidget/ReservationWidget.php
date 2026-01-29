<?php

namespace Drupal\reservation\Plugin\Field\FieldWidget;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a default reservation widget.
 *
 * @FieldWidget(
 *   id = "reservation_default",
 *   label = @Translation("Reservation"),
 *   field_types = {
 *     "reservation"
 *   }
 * )
 */
class ReservationWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();

    $element['status'] = [
      '#type' => 'radios',
      '#title' => t('Reservations'),
      '#title_display' => 'invisible',
      '#default_value' => $items->status,
      '#options' => [
        ReservationItemInterface::OPEN => t('Open'),
        ReservationItemInterface::CLOSED => t('Closed'),
        ReservationItemInterface::HIDDEN => t('Hidden'),
      ],
      ReservationItemInterface::OPEN => [
        '#description' => t('Users with the "Post reservations" permission can post reservations.'),
      ],
      ReservationItemInterface::CLOSED => [
        '#description' => t('Users cannot post reservations, but existing reservations will be displayed.'),
      ],
      ReservationItemInterface::HIDDEN => [
        '#description' => t('Reservations are hidden from view.'),
      ],
    ];
    // If the entity doesn't have any reservations, the "hidden" option makes no
    // sense, so don't even bother presenting it to the user unless this is the
    // default value widget on the field settings form.
    if (!$this->isDefaultValueWidget($form_state) && !$items->reservation_count) {
      $element['status'][ReservationItemInterface::HIDDEN]['#access'] = FALSE;
      // Also adjust the description of the "closed" option.
      $element['status'][ReservationItemInterface::CLOSED]['#description'] = t('Users cannot post reservations.');
    }
    // If the advanced settings tabs-set is available (normally rendered in the
    // second column on wide-resolutions), place the field as a details element
    // in this tab-set.
    if (isset($form['advanced'])) {
      // Get default value from the field.
      $field_default_values = $this->fieldDefinition->getDefaultValue($entity);

      // Override widget title to be helpful for end users.
      $element['#title'] = $this->t('Reservation settings');

      $element += [
        '#type' => 'details',
        // Open the details when the selected value is different to the stored
        // default values for the field.
        '#open' => ($items->status != $field_default_values[0]['status']),
        '#group' => 'advanced',
        '#attributes' => [
          'class' => ['reservation-' . Html::getClass($entity->getEntityTypeId()) . '-settings-form'],
        ],
        '#attached' => [
          'library' => ['reservation/drupal.reservation'],
        ],
      ];
    }

    return $element;
  }
  

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Add default values for statistics properties because we don't want to
    // have them in form.
    foreach ($values as &$value) {
      $value += [
        'cid' => 0,
        'last_reservation_timestamp' => 0,
        'last_reservation_name' => '',
        'last_reservation_uid' => 0,
        'reservation_count' => 0,
      ];
    }
    return $values;
  }

}
