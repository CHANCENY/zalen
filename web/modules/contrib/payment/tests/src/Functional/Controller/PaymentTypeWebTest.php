<?php

namespace Drupal\Tests\payment\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Payment type UI.
 *
 * @group Payment
 */
class PaymentTypeWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['field_ui', 'payment', 'payment_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests administrative overview.
   */
  public function testOverview() {
    $admin = $this->drupalCreateUser(['access administration pages']);
    $admin_payment_type = $this->drupalCreateUser(['access administration pages', 'payment.payment_type.administer']);

    // Test the plugin listing.
    $this->drupalGet('admin/config/services/payment');
    $this->assertSession()->linkNotExists('Payment types');
    $this->drupalGet('admin/config/services/payment/type');
    $this->assertSession()->statusCodeEquals('403');
    $this->drupalLogin($admin);
    $this->drupalGet('admin/config/services/payment');
    $this->assertSession()->linkNotExists('Payment types');
    $this->drupalGet('admin/config/services/payment/type');
    $this->assertSession()->statusCodeEquals('403');
    $this->drupalLogin($admin_payment_type);
    $this->drupalGet('admin/config/services/payment');
    $this->assertSession()->linkExists('Payment types');
    $this->drupalGet('admin/config/services/payment/type');
    $this->assertSession()->statusCodeEquals('200');
    $this->assertSession()->pageTextContains(t('Test type'));

    // Test the dummy payment type route.
    $this->drupalGet('admin/config/services/payment/type/payment_test');
    $this->assertSession()->statusCodeEquals('200');

    // Test field operations.
    $this->drupalLogout();
    $links = [
      'administer payment display' => t('Manage display'),
      'administer payment fields' => t('Manage fields'),
      'administer payment form display' => t('Manage form display'),
    ];
    $path = 'admin/config/services/payment/type';
    foreach ($links as $permission => $text) {
      $this->drupalLogin($admin_payment_type);
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals('200');
      $this->assertSession()->linkNotExists($text);
      $this->drupalLogin($this->drupalCreateUser([$permission, 'payment.payment_type.administer']));
      $this->drupalGet($path);
      $this->clickLink($text);
      $this->assertSession()->statusCodeEquals('200');
      $this->assertSession()->titleEquals($text . ' | Drupal');
    }
  }

}
