<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation status field access.
 *
 * @group reservation
 */
class ReservationStatusFieldAccessTest extends BrowserTestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  public $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Reservation admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $reservationAdmin;

  /**
   * Node author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $nodeAuthor;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'reservation',
    'user',
    'system',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => t('Article'),
    ]);
    $node_type->save();
    $this->nodeAuthor = $this->drupalCreateUser([
      'create article content',
      'skip reservation approval',
      'post reservations',
      'edit own reservations',
      'access reservations',
      'administer nodes',
    ]);
    $this->reservationAdmin = $this->drupalCreateUser([
      'administer reservations',
      'create article content',
      'edit own reservations',
      'skip reservation approval',
      'post reservations',
      'access reservations',
      'administer nodes',
    ]);
    $this->addDefaultReservationField('node', 'article');
  }

  /**
   * Tests reservation status field access.
   */
  public function testReservationStatusFieldAccessStatus() {
    $this->drupalLogin($this->nodeAuthor);
    $this->drupalGet('node/add/article');
    $assert = $this->assertSession();
    $assert->fieldNotExists('reservation[0][status]');
    $this->submitForm([
      'title[0][value]' => 'Node 1',
    ], t('Save'));
    $assert->fieldExists('subject[0][value]');
    $this->drupalLogin($this->reservationAdmin);
    $this->drupalGet('node/add/article');
    $assert->fieldExists('reservation[0][status]');
    $this->submitForm([
      'title[0][value]' => 'Node 2',
    ], t('Save'));
    $assert->fieldExists('subject[0][value]');
  }

}
