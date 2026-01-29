<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests reservations as part of an RSS feed.
 *
 * @group reservation
 */
class ReservationRssTest extends ReservationTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup the rss view display.
    EntityViewDisplay::create([
      'status' => TRUE,
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'rss',
      'content' => ['links' => ['weight' => 100]],
    ])->save();
  }

  /**
   * Tests reservations as part of an RSS feed.
   */
  public function testReservationRss() {
    // Find reservation in RSS feed.
    $this->drupalLogin($this->webUser);
    $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName());
    $this->drupalGet('rss.xml');

    $cache_contexts = [
      'languages:language_interface',
      'theme',
      'url.site',
      'user.node_grants:view',
      'user.permissions',
      'timezone',
    ];
    $this->assertCacheContexts($cache_contexts);

    $cache_context_tags = \Drupal::service('cache_contexts_manager')->convertTokensToKeys($cache_contexts)->getCacheTags();
    $this->assertCacheTags(Cache::mergeTags($cache_context_tags, [
      'config:views.view.frontpage',
      'node:1', 'node_list',
      'node_view',
      'user:3',
    ]));

    $raw = '<reservations>' . $this->node->toUrl('canonical', ['fragment' => 'reservations', 'absolute' => TRUE])->toString() . '</reservations>';
    $this->assertSession()->responseContains($raw);

    // Hide reservations from RSS feed and check presence.
    $this->node->set('reservation', ReservationItemInterface::HIDDEN);
    $this->node->save();
    $this->drupalGet('rss.xml');
    $this->assertSession()->responseNotContains($raw);
  }

}
