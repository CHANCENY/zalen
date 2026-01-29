<?php

namespace Drupal\Tests\reservation\Unit;

use Drupal\reservation\ReservationStatistics;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\reservation\ReservationStatistics
 * @group reservation
 */
class ReservationStatisticsUnitTest extends UnitTestCase {

  /**
   * Mock statement.
   *
   * @var \Drupal\Core\Database\Statement
   */
  protected $statement;

  /**
   * Mock select interface.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $select;

  /**
   * Mock database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * ReservationStatistics service under test.
   *
   * @var \Drupal\reservation\ReservationStatisticsInterface
   */
  protected $reservationStatistics;

  /**
   * Counts calls to fetchAssoc().
   *
   * @var int
   */
  protected $calls_to_fetch;

  /**
   * Sets up required mocks and the ReservationStatistics service under test.
   */
  protected function setUp(): void {
    $this->statement = $this->getMockBuilder('Drupal\Core\Database\Driver\sqlite\Statement')
      ->disableOriginalConstructor()
      ->getMock();

    $this->statement->expects($this->any())
      ->method('fetchObject')
      ->will($this->returnCallback([$this, 'fetchObjectCallback']));

    $this->select = $this->getMockBuilder('Drupal\Core\Database\Query\Select')
      ->disableOriginalConstructor()
      ->getMock();

    $this->select->expects($this->any())
      ->method('fields')
      ->will($this->returnSelf());

    $this->select->expects($this->any())
      ->method('condition')
      ->will($this->returnSelf());

    $this->select->expects($this->any())
      ->method('execute')
      ->will($this->returnValue($this->statement));

    $this->database = $this->getMockBuilder('Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();

    $this->database->expects($this->once())
      ->method('select')
      ->will($this->returnValue($this->select));

    $this->reservationStatistics = new ReservationStatistics($this->database, $this->createMock('Drupal\Core\Session\AccountInterface'), $this->createMock(EntityTypeManagerInterface::class), $this->createMock('Drupal\Core\State\StateInterface'), $this->database);
  }

  /**
   * Tests the read method.
   *
   * @see \Drupal\reservation\ReservationStatistics::read()
   *
   * @group Drupal
   * @group Reservation
   */
  public function testRead() {
    $this->calls_to_fetch = 0;
    $results = $this->reservationStatistics->read(['1' => 'boo', '2' => 'foo'], 'snafus');
    $this->assertEquals($results, ['something', 'something-else']);
  }

  /**
   * Return value callback for fetchObject() function on mocked object.
   *
   * @return bool|string
   *   'Something' on first, 'something-else' on second and FALSE for the
   *   other calls to function.
   */
  public function fetchObjectCallback() {
    $this->calls_to_fetch++;
    switch ($this->calls_to_fetch) {
      case 1:
        return 'something';

      case 2:
        return 'something-else';

      default:
        return FALSE;
    }
  }

}
