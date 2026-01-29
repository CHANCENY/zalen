<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests reservations with other entities.
 *
 * @group reservation
 */
class ReservationEntityTest extends ReservationTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'reservation',
    'node',
    'history',
    'field_ui',
    'datetime',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected $vocab;
  protected $reservationType;

  protected function setUp(): void {
    parent::setUp();

    $this->vocab = $this->createVocabulary();
    $this->reservationType = ReservationType::create([
      'id' => 'taxonomy_reservation',
      'label' => 'Taxonomy reservation',
      'description' => '',
      'target_entity_type_id' => 'taxonomy_term',
    ]);
    $this->reservationType->save();
    $this->addDefaultReservationField(
      'taxonomy_term',
      $this->vocab->id(),
      'field_reservation',
      ReservationItemInterface::OPEN,
      $this->reservationType->id()
    );
  }

  /**
   * Tests CSS classes on reservations.
   */
  public function testEntityChanges() {
    $this->drupalLogin($this->webUser);
    // Create a new node.
    $term = $this->createTerm($this->vocab, ['uid' => $this->webUser->id()]);

    // Add a reservation.
    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = Reservation::create([
      'entity_id' => $term->id(),
      'entity_type' => 'taxonomy_term',
      'field_name' => 'field_reservation',
      'uid' => $this->webUser->id(),
      'status' => ReservationInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'reservation_body' => [LanguageInterface::LANGCODE_NOT_SPECIFIED => [$this->randomMachineName()]],
    ]);
    $reservation->save();

    // Request the node with the reservation.
    $this->drupalGet('taxonomy/term/' . $term->id());
    $settings = $this->getDrupalSettings();
    $this->assertFalse(isset($settings['ajaxPageState']['libraries']) && in_array('reservation/drupal.reservation-new-indicator', explode(',', $settings['ajaxPageState']['libraries'])), 'drupal.reservation-new-indicator library is present.');
    $this->assertFalse(isset($settings['history']['lastReadTimestamps']) && in_array($term->id(), array_keys($settings['history']['lastReadTimestamps'])), 'history.lastReadTimestamps is present.');
  }

}
