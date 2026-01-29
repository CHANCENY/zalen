<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\node\NodeInterface;

/**
 * Tests the migration of reservations from Drupal 7.
 *
 * @group reservation
 * @group migrate_drupal_7
 */
class MigrateReservationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'reservation',
    'content_translation',
    'datetime',
    'filter',
    'image',
    'language',
    'link',
    'menu_ui',
    'node',
    'taxonomy',
    'telephone',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('reservation');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installSchema('node', ['node_access']);
    $this->migrateContent();
    $this->executeMigrations([
      'language',
      'd7_node_type',
      'd7_language_content_settings',
      'd7_node_translation',
      'd7_reservation_field',
      'd7_reservation_field_instance',
      'd7_reservation_entity_display',
      'd7_reservation_entity_form_display',
      'd7_taxonomy_vocabulary',
      'd7_field',
      'd7_field_instance',
      'd7_reservation',
      'd7_entity_translation_settings',
      'd7_reservation_entity_translation',
    ]);
  }

  /**
   * Tests the migrated reservations.
   */
  public function testMigration() {
    $reservation = Reservation::load(1);
    $this->assertInstanceOf(Reservation::class, $reservation);
    $this->assertSame('Subject field in English', $reservation->getSubject());
    $this->assertSame('1421727536', $reservation->getCreatedTime());
    $this->assertSame('1421727536', $reservation->getChangedTime());
    $this->assertTrue($reservation->isPublished());
    $this->assertSame('admin', $reservation->getAuthorName());
    $this->assertSame('admin@local.host', $reservation->getAuthorEmail());
    $this->assertSame('This is a reservation', $reservation->reservation_body->value);
    $this->assertSame('filtered_html', $reservation->reservation_body->format);
    $this->assertSame('2001:db8:ffff:ffff:ffff:ffff:ffff:ffff', $reservation->getHostname());
    $this->assertSame('en', $reservation->language()->getId());
    $this->assertSame('1000000', $reservation->field_integer->value);

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->id());

    // Tests that reservations that used the Drupal 7 Title module and that have
    // their subject replaced by a real field are correctly migrated.
    $reservation = Reservation::load(2);
    $this->assertInstanceOf(Reservation::class, $reservation);
    $this->assertSame('TNG for the win!', $reservation->getSubject());
    $this->assertSame('TNG is better than DS9.', $reservation->reservation_body->value);
    $this->assertSame('en', $reservation->language()->getId());

    // Tests that the reservationed entity is correctly migrated when the reservation
    // was posted to a node translation.
    $reservation = Reservation::load(3);
    $this->assertInstanceOf(Reservation::class, $reservation);
    $this->assertSame('Reservation to IS translation', $reservation->getSubject());
    $this->assertSame('This is a reservation to an Icelandic translation.', $reservation->reservation_body->value);
    $this->assertSame('2', $reservation->getReservationedEntityId());
    $this->assertSame('node', $reservation->getReservationedEntityTypeId());
    $this->assertSame('is', $reservation->language()->getId());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('2', $node->id());

    // Tests a reservation migrated from Drupal 6 to Drupal 7 that did not have a
    // language.
    $reservation = Reservation::load(4);
    $this->assertInstanceOf(Reservation::class, $reservation);
    $this->assertSame('Reservation without language', $reservation->getSubject());
    $this->assertSame('1426781880', $reservation->getCreatedTime());
    $this->assertSame('1426781880', $reservation->getChangedTime());
    $this->assertTrue($reservation->isPublished());
    $this->assertSame('Bob', $reservation->getAuthorName());
    $this->assertSame('bob@local.host', $reservation->getAuthorEmail());
    $this->assertSame('A reservation without language (migrated from Drupal 6)', $reservation->reservation_body->value);
    $this->assertSame('filtered_html', $reservation->reservation_body->format);
    $this->assertSame('drupal7.local', $reservation->getHostname());
    $this->assertSame('und', $reservation->language()->getId());
    $this->assertSame('10', $reservation->field_integer->value);

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->id());
  }

  /**
   * Tests the migration of reservation entity translations.
   */
  public function testReservationEntityTranslations() {
    $manager = $this->container->get('content_translation.manager');

    // Get the reservation and its translations.
    $reservation = Reservation::load(1);
    $reservation_fr = $reservation->getTranslation('fr');
    $reservation_is = $reservation->getTranslation('is');

    // Test that fields translated with Entity Translation are migrated.
    $this->assertSame('Subject field in English', $reservation->getSubject());
    $this->assertSame('Subject field in French', $reservation_fr->getSubject());
    $this->assertSame('Subject field in Icelandic', $reservation_is->getSubject());
    $this->assertSame('1000000', $reservation->field_integer->value);
    $this->assertSame('2000000', $reservation_fr->field_integer->value);
    $this->assertSame('3000000', $reservation_is->field_integer->value);

    // Test that the French translation metadata is correctly migrated.
    $metadata_fr = $manager->getTranslationMetadata($reservation_fr);
    $this->assertFalse($metadata_fr->isPublished());
    $this->assertSame('en', $metadata_fr->getSource());
    $this->assertSame('1', $metadata_fr->getAuthor()->uid->value);
    $this->assertSame('1531837764', $metadata_fr->getCreatedTime());
    $this->assertSame('1531837764', $metadata_fr->getChangedTime());
    $this->assertFalse($metadata_fr->isOutdated());

    // Test that the Icelandic translation metadata is correctly migrated.
    $metadata_is = $manager->getTranslationMetadata($reservation_is);
    $this->assertTrue($metadata_is->isPublished());
    $this->assertSame('en', $metadata_is->getSource());
    $this->assertSame('2', $metadata_is->getAuthor()->uid->value);
    $this->assertSame('1531838064', $metadata_is->getCreatedTime());
    $this->assertSame('1531838064', $metadata_is->getChangedTime());
    $this->assertTrue($metadata_is->isOutdated());
  }

}
