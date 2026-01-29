<?php

namespace Drupal\room_tariff\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * These prices are valid.
 *
 * Checks if the prices shown are valid.
 *
 * @Constraint(
 *   id = "PriceCheck",
 *   label = @Translation("Correct of the price field", context = "Validation")
 * )
 */
class PriceCheckConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'You misspelled the price field (%type: %id).';

}
