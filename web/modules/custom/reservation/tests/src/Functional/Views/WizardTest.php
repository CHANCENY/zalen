<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\views\Views;
use Drupal\Tests\views\Functional\Wizard\WizardTestBase;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests the reservation module integration into the wizard.
 *
 * @group reservation
 * @see \Drupal\reservation\Plugin\views\wizard\Reservation
 */
class WizardTest extends WizardTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node', 'reservation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->drupalCreateContentType(['type' => 'page', 'name' => t('Basic page')]);
    // Add reservation field to page node type.
    $this->addDefaultReservationField('node', 'page');
  }

  /**
   * Tests adding a view of reservations.
   */
  public function testReservationWizard() {
    $view = [];
    $view['label'] = $this->randomMachineName(16);
    $view['id'] = strtolower($this->randomMachineName(16));
    $view['show[wizard_key]'] = 'reservation';
    $view['page[create]'] = TRUE;
    $view['page[path]'] = $this->randomMachineName(16);

    // Just triggering the saving should automatically choose a proper row
    // plugin.
    $this->submitForm('admin/structure/views/add', $view, 'Save and edit');
    // Verify that the view saving was successful and the browser got redirected
    // to the edit page.
    $this->assertSession()->addressEquals('admin/structure/views/view/' . $view['id']);

    // If we update the type first we should get a selection of reservation valid
    // row plugins as the select field.

    $this->drupalGet('admin/structure/views/add');
    $this->submitForm('admin/structure/views/add', $view, 'Update "of type" choice');

    // Check for available options of the row plugin.
    $expected_options = ['entity:reservation', 'fields'];
    $items = $this->getSession()->getPage()->findField('page[style][row_plugin]')->findAll('xpath', 'option');
    $actual_options = [];
    foreach ($items as $item) {
      $actual_options[] = $item->getValue();
    }
    $this->assertEquals($expected_options, $actual_options);

    $view['id'] = strtolower($this->randomMachineName(16));
    $this->submitForm($view, 'Save and edit');
    // Verify that the view saving was successful and the browser got redirected
    // to the edit page.
    $this->assertSession()->addressEquals('admin/structure/views/view/' . $view['id']);

    $user = $this->drupalCreateUser(['access reservations']);
    $this->drupalLogin($user);

    $view = Views::getView($view['id']);
    $view->initHandlers();
    $row = $view->display_handler->getOption('row');
    $this->assertEquals('entity:reservation', $row['type']);

    // Check for the default filters.
    $this->assertEquals('reservation_field_data', $view->filter['status']->table);
    $this->assertEquals('status', $view->filter['status']->field);
    $this->assertEquals('1', $view->filter['status']->value);
    $this->assertEquals('node_field_data', $view->filter['status_node']->table);
    $this->assertEquals('status', $view->filter['status_node']->field);
    $this->assertEquals('1', $view->filter['status_node']->value);

    // Check for the default fields.
    $this->assertEquals('reservation_field_data', $view->field['subject']->table);
    $this->assertEquals('subject', $view->field['subject']->field);
  }

}
