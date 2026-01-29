<?php

namespace Drupal\Tests\reservation\Unit;

use Drupal\reservation\ReservationLinkBuilder;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Tests\Traits\Core\GeneratePermutationsTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\reservation\ReservationLinkBuilder
 * @group reservation
 */
class ReservationLinkBuilderTest extends UnitTestCase {

  use GeneratePermutationsTrait;

  /**
   * Reservation manager mock.
   *
   * @var \Drupal\reservation\ReservationManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $reservationManager;

  /**
   * String translation mock.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * Current user proxy mock.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * Timestamp used in test.
   *
   * @var int
   */
  protected $timestamp;

  /**
   * @var \Drupal\reservation\ReservationLinkBuilderInterface
   */
  protected $reservationLinkBuilder;

  /**
   * Prepares mocks for the test.
   */
  protected function setUp(): void {
    $this->reservationManager = $this->createMock('\Drupal\reservation\ReservationManagerInterface');
    $this->stringTranslation = $this->getStringTranslationStub();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->moduleHandler = $this->createMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    $this->currentUser = $this->createMock('\Drupal\Core\Session\AccountProxyInterface');
    $this->reservationLinkBuilder = new ReservationLinkBuilder($this->currentUser, $this->reservationManager, $this->moduleHandler, $this->stringTranslation, $this->entityTypeManager);
    $this->reservationManager->expects($this->any())
      ->method('getFields')
      ->with('node')
      ->willReturn([
        'reservation' => [],
      ]);
    $this->reservationManager->expects($this->any())
      ->method('forbiddenMessage')
      ->willReturn("Can't let you do that Dave.");
    $this->stringTranslation->expects($this->any())
      ->method('formatPlural')
      ->willReturnArgument(1);
  }

  /**
   * Test the buildReservationedEntityLinks method.
   *
   * @param \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject $node
   *   Mock node.
   * @param array $context
   *   Context for the links.
   * @param bool $has_access_reservations
   *   TRUE if the user has 'access reservations' permission.
   * @param bool $history_exists
   *   TRUE if the history module exists.
   * @param bool $has_post_reservations
   *   TRUE if the use has 'post reservations' permission.
   * @param bool $is_anonymous
   *   TRUE if the user is anonymous.
   * @param array $expected
   *   Array of expected links keyed by link ID. Can be either string (link
   *   title) or array of link properties.
   *
   * @dataProvider getLinkCombinations
   *
   * @covers ::buildReservationedEntityLinks
   */
  public function testReservationLinkBuilder(NodeInterface $node, $context, $has_access_reservations, $history_exists, $has_post_reservations, $is_anonymous, $expected) {
    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('history')
      ->willReturn($history_exists);
    $this->currentUser->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['access reservations', $has_access_reservations],
        ['post reservations', $has_post_reservations],
      ]);
    $this->currentUser->expects($this->any())
      ->method('isAuthenticated')
      ->willReturn(!$is_anonymous);
    $this->currentUser->expects($this->any())
      ->method('isAnonymous')
      ->willReturn($is_anonymous);
    $links = $this->reservationLinkBuilder->buildReservationedEntityLinks($node, $context);
    if (!empty($expected)) {
      if (!empty($links)) {
        foreach ($expected as $link => $detail) {
          if (is_array($detail)) {
            // Array of link attributes.
            foreach ($detail as $key => $value) {
              $this->assertEquals($value, $links['reservation__reservation']['#links'][$link][$key]);
            }
          }
          else {
            // Just the title.
            $this->assertEquals($detail, $links['reservation__reservation']['#links'][$link]['title']);
          }
        }
      }
      else {
        $this->fail('Expected links but found none.');
      }
    }
    else {
      $this->assertSame($links, $expected);
    }
  }

  /**
   * Data provider for ::testReservationLinkBuilder.
   */
  public function getLinkCombinations() {
    $cases = [];
    // No links should be created if the entity doesn't have the field.
    $cases[] = [
      $this->getMockNode(FALSE, ReservationItemInterface::OPEN, ReservationItemInterface::FORM_BELOW, 1),
      ['view_mode' => 'teaser'],
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      [],
    ];
    foreach (['search_result', 'search_index', 'print'] as $view_mode) {
      // Nothing should be output in these view modes.
      $cases[] = [
        $this->getMockNode(TRUE, ReservationItemInterface::OPEN, ReservationItemInterface::FORM_BELOW, 1),
        ['view_mode' => $view_mode],
        TRUE,
        TRUE,
        TRUE,
        TRUE,
        [],
      ];
    }
    // All other combinations.
    $combinations = [
      'is_anonymous' => [FALSE, TRUE],
      'reservation_count' => [0, 1],
      'has_access_reservations' => [0, 1],
      'history_exists' => [FALSE, TRUE],
      'has_post_reservations'   => [0, 1],
      'form_location'            => [ReservationItemInterface::FORM_BELOW, ReservationItemInterface::FORM_SEPARATE_PAGE],
      'reservations'        => [
        ReservationItemInterface::OPEN,
        ReservationItemInterface::CLOSED,
        ReservationItemInterface::HIDDEN,
      ],
      'view_mode' => [
        'teaser', 'rss', 'full',
      ],
    ];
    $permutations = $this->generatePermutations($combinations);
    foreach ($permutations as $combination) {
      $case = [
        $this->getMockNode(TRUE, $combination['reservations'], $combination['form_location'], $combination['reservation_count']),
        ['view_mode' => $combination['view_mode']],
        $combination['has_access_reservations'],
        $combination['history_exists'],
        $combination['has_post_reservations'],
        $combination['is_anonymous'],
      ];
      $expected = [];
      // When reservations are enabled in teaser mode, and reservations exist, and the
      // user has access - we can output the reservation count.
      if ($combination['reservations'] && $combination['view_mode'] == 'teaser' && $combination['reservation_count'] && $combination['has_access_reservations']) {
        $expected['reservation-reservations'] = '1 reservation';
        // And if history module exists, we can show a 'new reservations' link.
        if ($combination['history_exists']) {
          $expected['reservation-new-reservations'] = '';
        }
      }
      // All view modes other than RSS.
      if ($combination['view_mode'] != 'rss') {
        // Where reservationing is open.
        if ($combination['reservations'] == ReservationItemInterface::OPEN) {
          // And the user has post-reservations permission.
          if ($combination['has_post_reservations']) {
            // If the view mode is teaser, or the user can access reservations and
            // reservations exist or the form is on a separate page.
            if ($combination['view_mode'] == 'teaser' || ($combination['has_access_reservations'] && $combination['reservation_count']) || $combination['form_location'] == ReservationItemInterface::FORM_SEPARATE_PAGE) {
              // There should be an add reservation link.
              $expected['reservation-add'] = ['title' => 'Add new reservation'];
              if ($combination['form_location'] == ReservationItemInterface::FORM_BELOW) {
                // On the same page.
                $expected['reservation-add']['url'] = Url::fromRoute('node.view');
              }
              else {
                // On a separate page.
                $expected['reservation-add']['url'] = Url::fromRoute('reservation.reply', ['entity_type' => 'node', 'entity' => 1, 'field_name' => 'reservation']);
              }
            }
          }
          elseif ($combination['is_anonymous']) {
            // Anonymous users get the forbidden message if the can't post
            // reservations.
            $expected['reservation-forbidden'] = "Can't let you do that Dave.";
          }
        }
      }

      $case[] = $expected;
      $cases[] = $case;
    }
    return $cases;
  }

  /**
   * Builds a mock node based on given scenario.
   *
   * @param bool $has_field
   *   TRUE if the node has the 'reservation' field.
   * @param int $reservation_status
   *   One of ReservationItemInterface::OPEN|HIDDEN|CLOSED
   * @param int $form_location
   *   One of ReservationItemInterface::FORM_BELOW|FORM_SEPARATE_PAGE
   * @param int $reservation_count
   *   Number of reservations against the field.
   *
   * @return \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject
   *   Mock node for testing.
   */
  protected function getMockNode($has_field, $reservation_status, $form_location, $reservation_count) {
    $node = $this->createMock('\Drupal\node\NodeInterface');
    $node->expects($this->any())
      ->method('hasField')
      ->willReturn($has_field);

    if (empty($this->timestamp)) {
      $this->timestamp = time();
    }
    $field_item = (object) [
      'status' => $reservation_status,
      'reservation_count' => $reservation_count,
      'last_reservation_timestamp' => $this->timestamp,
    ];
    $node->expects($this->any())
      ->method('get')
      ->with('reservation')
      ->willReturn($field_item);

    $field_definition = $this->createMock('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->expects($this->any())
      ->method('getSetting')
      ->with('form_location')
      ->willReturn($form_location);
    $node->expects($this->any())
      ->method('getFieldDefinition')
      ->with('reservation')
      ->willReturn($field_definition);

    $node->expects($this->any())
      ->method('language')
      ->willReturn('und');

    $node->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('node');

    $node->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $url = Url::fromRoute('node.view');
    $node->expects($this->any())
      ->method('toUrl')
      ->willReturn($url);

    return $node;
  }

}

namespace Drupal\reservation;

if (!function_exists('history_read')) {

  function history_read() {
    return 0;
  }

}
