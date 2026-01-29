<?php

namespace Drupal\reservation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'reservation_username' formatter.
 *
 * @FieldFormatter(
 *   id = "reservation_username",
 *   label = @Translation("Author name"),
 *   description = @Translation("Display the author name."),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class AuthorNameFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      /** @var $reservation \Drupal\reservation\ReservationInterface */
      $reservation = $item->getEntity();
      $account = $reservation->getOwner();
      $elements[$delta] = [
        '#theme' => 'username',
        '#account' => $account,
        '#cache' => [
          'tags' => $account->getCacheTags() + $reservation->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'name' && $field_definition->getTargetEntityTypeId() === 'reservation';
  }

}
