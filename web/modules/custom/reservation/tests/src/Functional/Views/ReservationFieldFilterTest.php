<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation field filters with translations.
 *
 * @group reservation
 */
class ReservationFieldFilterTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_field_filters'];

  /**
   * List of reservation titles by language.
   *
   * @var array
   */
  public $reservationTitles = [];

  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->drupalLogin($this->drupalCreateUser(['access reservations']));

    // Add two new languages.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Set up reservation titles.
    $this->reservationTitles = [
      'en' => 'Food in Paris',
      'es' => 'Comida en Paris',
      'fr' => 'Nourriture en Paris',
    ];

    // Create a new reservation. Using the one created earlier will not work,
    // as it predates the language set-up.
    $reservation = [
      'uid' => $this->loggedInUser->id(),
      'entity_id' => $this->nodeUserReservationed->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'cid' => '',
      'pid' => '',
      'node_type' => '',
    ];
    $this->reservation = Reservation::create($reservation);

    // Add field values and translate the reservation.
    $this->reservation->subject->value = $this->reservationTitles['en'];
    $this->reservation->reservation_body->value = $this->reservationTitles['en'];
    $this->reservation->langcode = 'en';
    $this->reservation->save();
    foreach (['es', 'fr'] as $langcode) {
      $translation = $this->reservation->addTranslation($langcode, []);
      $translation->reservation_body->value = $this->reservationTitles[$langcode];
      $translation->subject->value = $this->reservationTitles[$langcode];
    }
    $this->reservation->save();
  }

  /**
   * Tests body and title filters.
   */
  public function testFilters() {
    // Test the title filter page, which filters for title contains 'Comida'.
    // Should show just the Spanish translation, once.
    $this->assertPageCounts('test-title-filter', ['es' => 1, 'fr' => 0, 'en' => 0], 'Comida title filter');

    // Test the body filter page, which filters for body contains 'Comida'.
    // Should show just the Spanish translation, once.
    $this->assertPageCounts('test-body-filter', ['es' => 1, 'fr' => 0, 'en' => 0], 'Comida body filter');

    // Test the title Paris filter page, which filters for title contains
    // 'Paris'. Should show each translation once.
    $this->assertPageCounts('test-title-paris', ['es' => 1, 'fr' => 1, 'en' => 1], 'Paris title filter');

    // Test the body Paris filter page, which filters for body contains
    // 'Paris'. Should show each translation once.
    $this->assertPageCounts('test-body-paris', ['es' => 1, 'fr' => 1, 'en' => 1], 'Paris body filter');
  }

  /**
   * Asserts that the given reservation translation counts are correct.
   *
   * @param string $path
   *   Path of the page to test.
   * @param array $counts
   *   Array whose keys are languages, and values are the number of times
   *   that translation should be shown on the given page.
   * @param string $message
   *   Message suffix to display.
   */
  protected function assertPageCounts($path, $counts, $message) {
    // Get the text of the page.
    $this->drupalGet($path);
    $text = $this->getTextContent();

    // Check the counts. Note that the title and body are both shown on the
    // page, and they are the same. So the title/body string should appear on
    // the page twice as many times as the input count.
    foreach ($counts as $langcode => $count) {
      $this->assertEquals(2 * $count, substr_count($text, $this->reservationTitles[$langcode]), 'Translation ' . $langcode . ' has count ' . $count . ' with ' . $message);
    }
  }

}
