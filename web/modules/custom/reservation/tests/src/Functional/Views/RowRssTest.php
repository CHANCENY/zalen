<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\Tests\BrowserTestBase;


/**
 * Tests the reservation rss row plugin.
 *
 * @group reservation
 * @see \Drupal\reservation\Plugin\views\row\Rss
 */
class RowRssTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation_rss'];

  /**
   * Test reservation rss output.
   */
  public function testRssRow() {
    $this->drupalGet('test-reservation-rss');

    // Because the response is XML we can't use the page which depends on an
    // HTML tag being present.
    $result = $this->getSession()->getDriver()->find('//item');
    $this->assertCount(1, $result, 'Just one reservation was found in the rss output.');

    $this->assertEquals(gmdate('r', $this->reservation->getCreatedTime()), $result[0]->find('xpath', '//pubDate')->getHtml(), 'The right pubDate appears in the rss output.');
  }

}
