<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\reservation\Entity\Reservation;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drupal\Tests\BrowserTestBase;


/**
 * Generates text using placeholders for dummy content to check reservation token
 * replacement.
 *
 * @group reservation
 */
class ReservationTokenReplaceTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a reservation, then tests the tokens generated from it.
   */
  public function testReservationTokenReplacement() {
    $token_service = \Drupal::token();
    $language_interface = \Drupal::languageManager()->getCurrentLanguage();
    $url_options = [
      'absolute' => TRUE,
      'language' => $language_interface,
    ];

    // Setup vocabulary.
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    // Change the title of the admin user.
    $this->adminUser->name->value = 'This is a title with some special & > " stuff.';
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);

    // Set reservation variables.
    $this->setReservationSubject(TRUE);

    // To test hostname token field should be populated.
    \Drupal::configFactory()
      ->getEditable('reservation.settings')
      ->set('log_ip_addresses', TRUE)
      ->save(TRUE);

    // Create a node and a reservation.
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => '<script>alert("123")</script>']);
    $parent_reservation = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $parent_reservation->id());
    $child_reservation = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName());
    $reservation = Reservation::load($child_reservation->id());
    $reservation->setHomepage('http://example.org/');

    // Add HTML to ensure that sanitation of some fields tested directly.
    $reservation->setSubject('<blink>Blinking Reservation</blink>');

    // Generate and test tokens.
    $tests = [];
    $tests['[reservation:cid]'] = $reservation->id();
    $tests['[reservation:hostname]'] = $reservation->getHostname();
    $tests['[reservation:author]'] = Html::escape($reservation->getAuthorName());
    $tests['[reservation:mail]'] = $this->adminUser->getEmail();
    $tests['[reservation:homepage]'] = UrlHelper::filterBadProtocol($reservation->getHomepage());
    $tests['[reservation:title]'] = Html::escape($reservation->getSubject());
    $tests['[reservation:body]'] = $reservation->reservation_body->processed;
    $tests['[reservation:langcode]'] = $reservation->language()->getId();
    $tests['[reservation:url]'] = $reservation->toUrl('canonical', $url_options + ['fragment' => 'reservation-' . $reservation->id()])->toString();
    $tests['[reservation:edit-url]'] = $reservation->toUrl('edit-form', $url_options)->toString();
    $tests['[reservation:created]'] = \Drupal::service('date.formatter')->format($reservation->getCreatedTime(), 'medium', ['langcode' => $language_interface->getId()]);
    $tests['[reservation:created:since]'] = \Drupal::service('date.formatter')->formatTimeDiffSince($reservation->getCreatedTime(), ['langcode' => $language_interface->getId()]);
    $tests['[reservation:changed:since]'] = \Drupal::service('date.formatter')->formatTimeDiffSince($reservation->getChangedTimeAcrossTranslations(), ['langcode' => $language_interface->getId()]);
    $tests['[reservation:parent:cid]'] = $reservation->hasParentReservation() ? $reservation->getParentReservation()->id() : NULL;
    $tests['[reservation:parent:title]'] = $parent_reservation->getSubject();
    $tests['[reservation:entity]'] = Html::escape($node->getTitle());
    // Test node specific tokens.
    $tests['[reservation:entity:nid]'] = $reservation->getReservationedEntityId();
    $tests['[reservation:entity:title]'] = Html::escape($node->getTitle());
    $tests['[reservation:author:uid]'] = $reservation->getOwnerId();
    $tests['[reservation:author:name]'] = Html::escape($this->adminUser->getDisplayName());

    $base_bubbleable_metadata = BubbleableMetadata::createFromObject($reservation);
    $metadata_tests = [];
    $metadata_tests['[reservation:cid]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:hostname]'] = $base_bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $bubbleable_metadata->addCacheableDependency($this->adminUser);
    $metadata_tests['[reservation:author]'] = $bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $bubbleable_metadata->addCacheableDependency($this->adminUser);
    $metadata_tests['[reservation:mail]'] = $bubbleable_metadata;
    $metadata_tests['[reservation:homepage]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:title]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:body]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:langcode]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:url]'] = $base_bubbleable_metadata;
    $metadata_tests['[reservation:edit-url]'] = $base_bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:created]'] = $bubbleable_metadata->addCacheTags(['rendered']);
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:created:since]'] = $bubbleable_metadata->setCacheMaxAge(0);
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:changed:since]'] = $bubbleable_metadata->setCacheMaxAge(0);
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:parent:cid]'] = $bubbleable_metadata->addCacheTags(['reservation:1']);
    $metadata_tests['[reservation:parent:title]'] = $bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:entity]'] = $bubbleable_metadata->addCacheTags(['node:2']);
    // Test node specific tokens.
    $metadata_tests['[reservation:entity:nid]'] = $bubbleable_metadata;
    $metadata_tests['[reservation:entity:title]'] = $bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[reservation:author:uid]'] = $bubbleable_metadata->addCacheTags(['user:2']);
    $metadata_tests['[reservation:author:name]'] = $bubbleable_metadata;

    // Test to make sure that we generated something for each token.
    $this->assertNotContains(0, array_map('strlen', $tests), 'No empty tokens generated.');

    foreach ($tests as $input => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = $token_service->replace($input, ['reservation' => $reservation], ['langcode' => $language_interface->getId()], $bubbleable_metadata);
      $this->assertEquals($expected, $output, new FormattableMarkup('Reservation token %token replaced.', ['%token' => $input]));
      $this->assertEquals($metadata_tests[$input], $bubbleable_metadata);
    }

    // Test anonymous reservation author.
    $author_name = 'This is a random & " > string';
    $reservation->setOwnerId(0)->setAuthorName($author_name);
    $input = '[reservation:author]';
    $output = $token_service->replace($input, ['reservation' => $reservation], ['langcode' => $language_interface->getId()]);
    $this->assertEquals(Html::escape($author_name), $output, new FormattableMarkup('Reservation author token %token replaced.', ['%token' => $input]));
    // Add reservation field to user and term entities.
    $this->addDefaultReservationField('user', 'user', 'reservation', ReservationItemInterface::OPEN, 'reservation_user');
    $this->addDefaultReservationField('taxonomy_term', 'tags', 'reservation', ReservationItemInterface::OPEN, 'reservation_term');

    // Create a user and a reservation.
    $user = User::create(['name' => 'alice']);
    $user->activate();
    $user->save();
    $this->postReservation($user, 'user body', 'user subject', TRUE);

    // Create a term and a reservation.
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'term',
    ]);
    $term->save();
    $this->postReservation($term, 'term body', 'term subject', TRUE);

    // Load node, user and term again so reservation_count gets computed.
    $node = Node::load($node->id());
    $user = User::load($user->id());
    $term = Term::load($term->id());

    // Generate reservation tokens for node (it has 2 reservations, both new),
    // user and term.
    $tests = [];
    $tests['[entity:reservation-count]'] = 2;
    $tests['[entity:reservation-count-new]'] = 2;
    $tests['[node:reservation-count]'] = 2;
    $tests['[node:reservation-count-new]'] = 2;
    $tests['[user:reservation-count]'] = 1;
    $tests['[user:reservation-count-new]'] = 1;
    $tests['[term:reservation-count]'] = 1;
    $tests['[term:reservation-count-new]'] = 1;

    foreach ($tests as $input => $expected) {
      $output = $token_service->replace($input, ['entity' => $node, 'node' => $node, 'user' => $user, 'term' => $term], ['langcode' => $language_interface->getId()]);
      $this->assertEquals($expected, $output, new FormattableMarkup('Reservation token %token replaced.', ['%token' => $input]));
    }
  }

}
