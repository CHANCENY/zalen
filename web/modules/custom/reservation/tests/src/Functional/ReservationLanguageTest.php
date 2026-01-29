<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for reservation language.
 *
 * @group reservation
 */
class ReservationLanguageTest extends BrowserTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * We also use the language_test module here to be able to turn on content
   * language negotiation. Drupal core does not provide a way in itself to do
   * that.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'language',
    'language_test',
    'reservation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create and log in user.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer languages',
      'access administration pages',
      'administer content types',
      'administer reservations',
      'create article content',
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);
    $this->drupalLogin($admin_user);

    // Add language.
    $edit = ['predefined_langcode' => 'fr'];
    $this->submitForm('admin/config/regional/language/add', $edit, 'Add language');

    // Set "Article" content type to use multilingual support.
    $edit = ['language_configuration[language_alterable]' => TRUE];
    $this->submitForm('admin/structure/types/manage/article', $edit, 'Save content type');

    // Enable content language negotiation UI.
    \Drupal::state()->set('language_test.content_language_type', TRUE);

    // Set interface language detection to user and content language detection
    // to URL. Disable inheritance from interface language to ensure content
    // language will fall back to the default language if no URL language can be
    // detected.
    $edit = [
      'language_interface[enabled][language-user]' => TRUE,
      'language_content[enabled][language-url]' => TRUE,
      'language_content[enabled][language-interface]' => FALSE,
    ];
    $this->submitForm('admin/config/regional/language/detection', $edit, 'Save settings');

    // Change user language preference, this way interface language is always
    // French no matter what path prefix the URLs have.
    $edit = ['preferred_langcode' => 'fr'];
    $this->submitForm("user/" . $admin_user->id() . "/edit", $edit, 'Save');

    // Create reservation field on article.
    $this->addDefaultReservationField('node', 'article');

    // Make reservation body translatable.
    $field_storage = FieldStorageConfig::loadByName('reservation', 'reservation_body');
    $field_storage->setTranslatable(TRUE);
    $field_storage->save();
    $this->assertTrue($field_storage->isTranslatable(), 'Reservation body is translatable.');
  }

  /**
   * Test that reservation language is properly set.
   */
  public function testReservationLanguage() {

    // Create two nodes, one for english and one for french, and reservation each
    // node using both english and french as content language by changing URL
    // language prefixes. Meanwhile interface language is always French, which
    // is the user language preference. This way we can ensure that node
    // language and interface language do not influence reservation language, as
    // only content language has to.
    foreach ($this->container->get('language_manager')->getLanguages() as $node_langcode => $node_language) {
      // Create "Article" content.
      $title = $this->randomMachineName();
      $edit = [
        'title[0][value]' => $title,
        'body[0][value]' => $this->randomMachineName(),
        'langcode[0][value]' => $node_langcode,
        'reservation[0][status]' => ReservationItemInterface::OPEN,
      ];
      $this->submitForm("node/add/article", $edit, 'Save');
      $node = $this->drupalGetNodeByTitle($title);

      $prefixes = $this->config('language.negotiation')->get('url.prefixes');
      foreach ($this->container->get('language_manager')->getLanguages() as $langcode => $language) {
        // Post a reservation with content language $langcode.
        $prefix = empty($prefixes[$langcode]) ? '' : $prefixes[$langcode] . '/';
        $reservation_values[$node_langcode][$langcode] = $this->randomMachineName();
        $edit = [
          'subject[0][value]' => $this->randomMachineName(),
          'reservation_body[0][value]' => $reservation_values[$node_langcode][$langcode],
        ];
        $this->submitForm($prefix . 'node/' . $node->id(), $edit, 'Preview');
        $this->submitForm($edit, 'Save');

        // Check that reservation language matches the current content language.
        $cids = \Drupal::entityQuery('reservation')
          ->condition('entity_id', $node->id())
          ->condition('entity_type', 'node')
          ->condition('field_name', 'reservation')
          ->sort('cid', 'DESC')
          ->range(0, 1)
          ->execute();
        $reservation = Reservation::load(reset($cids));
        $args = ['%node_language' => $node_langcode, '%reservation_language' => $reservation->langcode->value, '%langcode' => $langcode];
        $this->assertEquals($langcode, $reservation->langcode->value, new FormattableMarkup('The reservation posted with content language %langcode and belonging to the node with language %node_language has language %reservation_language', $args));
        $this->assertEquals($reservation_values[$node_langcode][$langcode], $reservation->reservation_body->value, 'Reservation body correctly stored.');
      }
    }

    // Check that reservation bodies appear in the administration UI.
    $this->drupalGet('admin/content/reservation');
    foreach ($reservation_values as $node_values) {
      foreach ($node_values as $value) {
        $this->assertSession()->responseContains($value);
      }
    }
  }

}
