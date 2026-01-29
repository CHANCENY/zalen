<?php

namespace Drupal\Tests\payment\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Payment category in the administration UI.
 *
 * @group Payment
 */
class PaymentAdministrationCategoryWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['payment'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests administrative overview.
   */
  public function testOverview() {
    $this->drupalGet('admin/config/services');
    $this->assertSession()->linkNotExists('Payment');
    $this->drupalGet('admin/config/services/payment');
    $this->assertSession()->statusCodeEquals('403');
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('admin/config');
    $this->drupalGet('admin/config/services');
    $this->assertSession()->linkExists('Payment');
    $this->drupalGet('admin/config/services/payment');
    $this->assertSession()->statusCodeEquals('200');
  }

}
