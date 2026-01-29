<?php

namespace Drupal\Tests\reservation\Unit\Entity;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests reservation acquires and releases the right lock.
 *
 * @group reservation
 */
class ReservationLockTest extends UnitTestCase {

  /**
   * Test the lock behavior.
   */
  public function testLocks() {
    $container = new ContainerBuilder();
    $container->set('module_handler', $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface'));
    $container->set('current_user', $this->createMock('Drupal\Core\Session\AccountInterface'));
    $container->set('cache.test', $this->createMock('Drupal\Core\Cache\CacheBackendInterface'));
    $container->set('reservation.statistics', $this->createMock('Drupal\reservation\ReservationStatisticsInterface'));
    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/'));
    $container->set('request_stack', $request_stack);
    $container->setParameter('cache_bins', ['cache.test' => 'test']);
    $lock = $this->createMock('Drupal\Core\Lock\LockBackendInterface');
    $cid = 2;
    $lock_name = "reservation:$cid:.00/";
    $lock->expects($this->at(0))
      ->method('acquire')
      ->with($lock_name, 30)
      ->will($this->returnValue(TRUE));
    $lock->expects($this->at(1))
      ->method('release')
      ->with($lock_name);
    $lock->expects($this->exactly(2))
      ->method($this->anything());
    $container->set('lock', $lock);

    $cache_tag_invalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidator');
    $container->set('cache_tags.invalidator', $cache_tag_invalidator);

    \Drupal::setContainer($container);
    $methods = get_class_methods('Drupal\reservation\Entity\Reservation');
    unset($methods[array_search('preSave', $methods)]);
    unset($methods[array_search('postSave', $methods)]);
    $methods[] = 'invalidateTagsOnSave';
    $reservation = $this->getMockBuilder('Drupal\reservation\Entity\Reservation')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $reservation->expects($this->once())
      ->method('isNew')
      ->will($this->returnValue(TRUE));
    $reservation->expects($this->once())
      ->method('hasParentReservation')
      ->will($this->returnValue(TRUE));
    $reservation->expects($this->once())
      ->method('getParentReservation')
      ->will($this->returnValue($reservation));
    $reservation->expects($this->once())
      ->method('getReservationedEntityId')
      ->will($this->returnValue($cid));
    $reservation->expects($this->any())
      ->method('getThread')
      ->will($this->returnValue(''));

    $anon_user = $this->createMock('Drupal\Core\Session\AccountInterface');
    $anon_user->expects($this->any())
      ->method('isAnonymous')
      ->will($this->returnValue(TRUE));
    $reservation->expects($this->any())
      ->method('getOwner')
      ->will($this->returnValue($anon_user));

    $parent_entity = $this->createMock('\Drupal\Core\Entity\ContentEntityInterface');
    $parent_entity->expects($this->atLeastOnce())
      ->method('getCacheTagsToInvalidate')
      ->willReturn(['node:1']);
    $reservation->expects($this->once())
      ->method('getReservationedEntity')
      ->willReturn($parent_entity);

    $entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $reservation->expects($this->any())
      ->method('getEntityType')
      ->will($this->returnValue($entity_type));
    $storage = $this->createMock('Drupal\reservation\ReservationStorageInterface');

    // preSave() should acquire the lock. (This is what's really being tested.)
    $reservation->preSave($storage);
    // Release the acquired lock before exiting the test.
    $reservation->postSave($storage);
  }

}
