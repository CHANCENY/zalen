<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Entity\ReservationType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that reservation bundles behave as expected.
 *
 * @group reservation
 */
class ReservationBundlesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'node', 'taxonomy', 'user'];

  /**
   * Entity type ids to use for target_entity_type_id on reservation bundles.
   *
   * @var array
   */
  protected $targetEntityTypes;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityFieldManager = $this->container->get('entity_field.manager');

    $this->installEntitySchema('reservation');

    // Create multiple reservation bundles,
    // each of which has a different target entity type.
    $this->targetEntityTypes = [
      'reservation' => 'Reservation',
      'node' => 'Node',
      'taxonomy_term' => 'Taxonomy Term',
    ];
    foreach ($this->targetEntityTypes as $id => $label) {
      ReservationType::create([
        'id' => 'reservation_on_' . $id,
        'label' => 'Reservation on ' . $label,
        'target_entity_type_id' => $id,
      ])->save();
    }
  }

  /**
   * Test that the entity_id field is set correctly for each reservation bundle.
   */
  public function testEntityIdField() {
    $field_definitions = [];

    foreach (array_keys($this->targetEntityTypes) as $id) {
      $bundle = 'reservation_on_' . $id;
      $field_definitions[$bundle] = $this->entityFieldManager
        ->getFieldDefinitions('reservation', $bundle);
    }
    // Test that the value of the entity_id field for each bundle is correct.
    foreach ($field_definitions as $bundle => $definition) {
      $entity_type_id = str_replace('reservation_on_', '', $bundle);
      $target_type = $definition['entity_id']->getSetting('target_type');
      $this->assertEquals($entity_type_id, $target_type);

      // Verify that the target type remains correct
      // in the deeply-nested object properties.
      $nested_target_type = $definition['entity_id']->getItemDefinition()->getFieldDefinition()->getSetting('target_type');
      $this->assertEquals($entity_type_id, $nested_target_type);
    }

  }

}
