<?php

/**
 * @file
 * Contains Drupal\room_tariff\Plugin\Validation\Constraint.
 */

namespace Drupal\room_tariff\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the UniqueInteger constraint.
 */
class TariffUniqueConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface{

  /**
   * Validator 2.5 and upwards compatible execution context.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  protected $context;
 
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('typed_data_manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {

    foreach ($items as $item) {
      $test = 0;
    }
  }

}