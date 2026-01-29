<?php

/**
 * @file
 * Contains Drupal\room_tariff\Plugin\Validation\Constraint.
 */

namespace Drupal\room_tariff\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value tariff.
 * 
 * @Constraint(
 *   id = "TariffUnique",
 *   label = @Translation("Unique Tariff", context = "Validation"),
 *   type = "entity:room_tariff",
 *   description = @Translation("Validate tariff price."),
 * )
 */
class TariffUniqueConstraint extends Constraint {
  
  // The message that will be shown if the value is not an filled in correctly.
  public $messageNotCorrectly = '%value not filled in correctly';

  // The message that will be shown if the value is not unique.
  public $messageNotUnique = '%value is not unique';

}