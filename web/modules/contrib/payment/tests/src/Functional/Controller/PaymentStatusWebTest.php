<?php

namespace Drupal\Tests\payment\Functional\Controller;

use Drupal\payment\Payment;
use Drupal\Tests\BrowserTestBase;

/**
 * Payment status UI.
 *
 * @group Payment
 */
class PaymentStatusWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['payment', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The payment status storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public $paymentStatusStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->paymentStatusStorage = \Drupal::entityTypeManager()->getStorage('payment_status');
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests listing.
   */
  public function testList() {
    $payment_status_id = strtolower($this->randomMachineName());
    /** @var \Drupal\payment\Entity\PaymentStatusInterface $status */
    $status = $this->paymentStatusStorage->create([]);
    $status->setId($payment_status_id)
      ->setLabel($this->randomMachineName())
      ->save();

    $path = 'admin/config/services/payment/status';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_status.administer']));
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    // Assert that the "Add payment status" link is visible.
    $this->assertSession()->linkByHrefExists('admin/config/services/payment/status/add');

    // Assert that all plugins are visible.
    $manager = Payment::statusManager();
    foreach ($manager->getDefinitions() as $definition) {
      $this->assertSession()->pageTextContains($definition['label']);
      if ($definition['description']) {
        $this->assertSession()->pageTextContains($definition['description']);
      }
    }

    // Assert that all config entity operations are visible.
    $this->assertSession()->linkByHrefExists('admin/config/services/payment/status/edit/' . $payment_status_id);
    $this->assertSession()->linkByHrefExists('admin/config/services/payment/status/delete/' . $payment_status_id);
  }

  /**
   * Tests adding and editing a payment status.
   */
  public function testAdd() {
    $path = 'admin/config/services/payment/status/add';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_status.administer']));
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    // Test a valid submission.
    $payment_status_id = strtolower($this->randomMachineName());
    $label = $this->randomString();
    $parent_id = 'payment_success';
    $description = $this->randomString();
    $this->drupalGet($path);
    $this->submitForm([
      'label' => $label,
      'id' => $payment_status_id,
      'container[select][container][plugin_id]' => $parent_id,
      'description' => $description,
    ], t('Save'));
    /** @var \Drupal\payment\Entity\PaymentStatusInterface $status */
    $status = $this->paymentStatusStorage->loadUnchanged($payment_status_id);
    $this->assertEquals($status->id(), $payment_status_id);
    $this->assertEquals($status->label(), $label);
    $this->assertEquals($status->getParentId(), $parent_id);
    $this->assertEquals($status->getDescription(), $description);

    // Test editing a payment status.
    $this->drupalGet('admin/config/services/payment/status/edit/' . $payment_status_id);
    $this->assertSession()->linkByHrefExists('admin/config/services/payment/status/delete/' . $payment_status_id);
    $label = $this->randomString();
    $parent_id = 'payment_success';
    $description = $this->randomString();
    $this->submitForm([
      'label' => $label,
      'container[select][container][plugin_id]' => $parent_id,
      'description' => $description,
    ], t('Save'));
    /** @var \Drupal\payment\Entity\PaymentStatusInterface $status */
    $status = $this->paymentStatusStorage->loadUnchanged($payment_status_id);
    $this->assertEquals($status->id(), $payment_status_id);
    $this->assertEquals($status->label(), $label);
    $this->assertEquals($status->getParentId(), $parent_id);
    $this->assertEquals($status->getDescription(), $description);

    // Test an invalid submission.
    $this->drupalGet($path);
    $this->submitForm([
      'label' => $label,
      'id' => $payment_status_id,
    ], t('Save'));
    $this->assertSession()->elementExists('xpath', '//input[@id="edit-id" and contains(@class, "error")]');
  }

  /**
   * Tests deleting a payment status.
   */
  public function testDelete() {
    $payment_status_id = strtolower($this->randomMachineName());
    /** @var \Drupal\payment\Entity\PaymentStatusInterface $status */
    $status = $this->paymentStatusStorage->create([]);
    $status->setId($payment_status_id)
      ->setLabel('test')
      ->save();

    $path = 'admin/config/services/payment/status/delete/' . $payment_status_id;
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['payment.payment_status.administer']));
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('Do you really want to delete test? | Drupal');
    $this->submitForm([], t('Delete'));
    $this->assertNull($this->paymentStatusStorage->loadUnchanged($payment_status_id));
  }

}
