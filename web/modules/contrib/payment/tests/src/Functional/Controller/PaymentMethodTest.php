<?php

namespace Drupal\Tests\payment\Functional\Controller;

use Drupal\payment\Entity\PaymentMethodConfiguration;
use Drupal\payment\Entity\PaymentMethodConfigurationInterface;
use Drupal\payment\Payment;
use Drupal\Tests\BrowserTestBase;

/**
 * Payment method UI.
 *
 * @group Payment
 */
class PaymentMethodTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['plugin', 'currency', 'payment'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the list.
   */
  public function testList() {
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests enabling/disabling.
   */
  public function testEnableDisable() {
    // Confirm that there are no enable/disable links without the required
    // permissions.
    $this->markTestSkipped('Fails on DrupalCI');

    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->assertSession()->linkNotExists(t('Enable'));
    $this->assertSession()->linkNotExists(t('Disable'));

    $storage = \Drupal::entityTypeManager()->getStorage('payment_method_configuration');

    /** @var \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method */
    $payment_method = $storage->load('collect_on_delivery');
    $this->assertFalse($payment_method->status());

    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any', 'payment.payment_method_configuration.update.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->clickLink(t('Enable'));
    \Drupal::configFactory()->reset();
    $payment_method = $storage->load('collect_on_delivery');
    $this->assertTrue($payment_method->status());

    $this->clickLink(t('Disable'));
    \Drupal::configFactory()->reset();
    $payment_method = $storage->load('collect_on_delivery');
    $this->assertFalse($payment_method->status());
  }

  /**
   * Tests duplication.
   */
  public function testDuplicate() {
    $entity_id = 'collect_on_delivery';
    $plugin_id = 'payment_basic';
    $storage = \Drupal::entityTypeManager()->getStorage('payment_method_configuration');

    // Test that only the original exists.
    $this->assertTrue((bool) $storage->load($entity_id));
    $this->assertFalse((bool) $storage->load($entity_id . '_duplicate'));

    // Test insufficient permissions.
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->assertSession()->linkNotExists('admin/config/services/payment/method/configuration/' . $entity_id . '/duplicate');
    $this->drupalGet('admin/config/services/payment/method/configuration/' . $entity_id . '/duplicate');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration/' . $entity_id . '/duplicate');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.create.' . $plugin_id]));
    $this->drupalGet('admin/config/services/payment/method/configuration/' . $entity_id . '/duplicate');
    $this->assertSession()->statusCodeEquals(403);

    // Test sufficient permissions.
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any', 'payment.payment_method_configuration.create.' . $plugin_id]));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->clickLink(t('Duplicate'));
    $this->assertSession()->statusCodeEquals(200);
    $this->xpath('//form[@id="payment-method-configuration-payment-basic-form"]');
    $this->submitForm([
      'id' => $entity_id . '_duplicate',
    ], t('Save'));
    $this->assertTrue((bool) $storage->load($entity_id));
    $this->assertTrue((bool) $storage->load($entity_id . '_duplicate'));
  }

  /**
   * Tests deletion.
   */
  public function testDelete() {
    $id = 'collect_on_delivery';

    $this->drupalGet('admin/config/services/payment/method/configuration/' . $id . '/delete');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->assertSession()->linkNotExists(t('Delete'));

    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.view.any', 'payment.payment_method_configuration.delete.any']));
    $this->drupalGet('admin/config/services/payment/method/configuration');
    $this->clickLink(t('Delete'));
    $this->submitForm([], t('Delete'));
    $this->assertFalse((bool) PaymentMethodConfiguration::load($id));
  }

  /**
   * Tests selecting.
   */
  public function testAddSelect() {
    $plugin_id = 'payment_basic';
    $this->drupalGet('admin/config/services/payment/method/configuration-add');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_method_configuration.create.' . $plugin_id]));
    $this->drupalGet('admin/config/services/payment/method/configuration-add');
    $this->assertSession()->statusCodeEquals(200);
    $definition = Payment::methodConfigurationManager()->getDefinition($plugin_id);
    $this->assertSession()->pageTextContains($definition['label']);
  }

  /**
   * Tests adding.
   */
  public function testAdd() {
    $plugin_id = 'payment_basic';
    $this->drupalGet('admin/config/services/payment/method/configuration-add/' . $plugin_id);
    $this->assertSession()->statusCodeEquals(403);
    $user = $this->drupalCreateUser(['payment.payment_method_configuration.create.' . $plugin_id]);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/services/payment/method/configuration-add/' . $plugin_id);
    $this->assertSession()->statusCodeEquals(200);
    $this->xpath('//form[@id="payment-method-configuration-payment-basic-form"]');

    // Test form validation.
    $this->submitForm([
      'owner' => '',
    ], t('Save'));
    $this->xpath('//input[@id="edit-label" and contains(@class, "error")]');
    $this->xpath('//input[@id="edit-id" and contains(@class, "error")]');
    $this->xpath('//input[@id="edit-owner" and contains(@class, "error")]');

    // Test form submission and payment method creation.
    $label = $this->randomString();;
    $brand_label = $this->randomString();
    $execute_status_id = 'payment_failed';
    $capture_status_id = 'payment_success';
    $refund_status_id = 'payment_cancelled';
    $id = strtolower($this->randomMachineName());
    $this->submitForm([
      'label' => $label,
      'id' => $id,
      'owner' => $user->label(),
      'plugin_form[plugin_form][brand_label]' => $brand_label,
      'plugin_form[plugin_form][execute][execute_status][container][select][container][plugin_id]' => $execute_status_id,
      'plugin_form[plugin_form][capture][capture]' => TRUE,
      'plugin_form[plugin_form][capture][plugin_form][capture_status][container][select][container][plugin_id]' => $capture_status_id,
      'plugin_form[plugin_form][refund][refund]' => TRUE,
      'plugin_form[plugin_form][refund][plugin_form][refund_status][container][select][container][plugin_id]' => $refund_status_id,
    ], t('Save'));
    /** @var \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method */
    $payment_method = PaymentMethodConfiguration::load($id);
    $this->assertInstanceOf(PaymentMethodConfigurationInterface::class, $payment_method);
    $this->assertEquals($payment_method->label(), $label);
    $this->assertEquals($payment_method->id(), $id);
    $this->assertEquals($payment_method->getOwnerId(), $user->id());
    $plugin_configuration = $payment_method->getPluginConfiguration();
    $this->assertEquals($plugin_configuration['brand_label'], $brand_label);
    $this->assertEquals($plugin_configuration['execute_status_id'], $execute_status_id);
    $this->assertEquals($plugin_configuration['capture'], TRUE);
    $this->assertEquals($plugin_configuration['capture_status_id'], $capture_status_id);
  }

}
