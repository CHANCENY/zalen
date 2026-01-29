<?php

namespace Drupal\Tests\reservation\Functional\Rest;

use Drupal\reservation\Entity\ReservationType;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;

/**
 * ResourceTestBase for ReservationType entity.
 */
abstract class ReservationTypeResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'reservation'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'reservation_type';

  /**
   * The ReservationType entity.
   *
   * @var \Drupal\reservation\ReservationTypeInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer reservation types']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "Camelids" reservation type.
    $camelids = ReservationType::create([
      'id' => 'camelids',
      'label' => 'Camelids',
      'description' => 'Camelids are large, strictly herbivorous animals with slender necks and long legs.',
      'target_entity_type_id' => 'node',
    ]);

    $camelids->save();

    return $camelids;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'dependencies' => [],
      'description' => 'Camelids are large, strictly herbivorous animals with slender necks and long legs.',
      'id' => 'camelids',
      'label' => 'Camelids',
      'langcode' => 'en',
      'status' => TRUE,
      'target_entity_type_id' => 'node',
      'uuid' => $this->entity->uuid(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

}
