<?php

namespace Drupal\room_invoice\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\room_invoice\Entity\InvoicePayment;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class InvoiceRedirectController.
 */
class InvoiceRedirectController extends ControllerBase {

  ///**
  // * Event dispatcher.
  // * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
  // */
  //protected $eventDispatcher;
  ///**
  // * RedirectController constructor.
  // * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
  // * Event dispatcher.
  // */
  //public function __construct(EventDispatcherInterface $eventDispatcher) {
  //  $this->eventDispatcher = $eventDispatcher;
  //}
  ///**
  // * {@inheritdoc}
  // */
  //public static function create(ContainerInterface $container) {
  //  return new static(
  //    $container->get('event_dispatcher')
  //  );
  //}



  /**
   *  Create an order confirmation page.
   * @param string $context_id
   * The ID of the context that requested the payment.
   */
  public function invokeStatusCheckInvoice($context_id = '') {

    if ($context_id == 'connection') {
      return $this->invokeStatusConnection();
    };

    /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
    $invoice = InvoicePayment::load($context_id);
    /** @var \Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $elements = [];

    //https://drupal.stackexchange.com/questions/258362/create-custom-table-with-pagination
    //$build = $this->entityTypeManager()->getViewBuilder($invoice->getEntityTypeId())->view($invoice);

    if (!$invoice) {
      return $elements[] = ['#type' => 'markup', '#markup' => $this->t('Data for the current request could not be provided.'),];
    } else if (!$current_user->hasPermission('administrator') && !($invoice->getOwnerId() == $current_user->id() || $invoice->getRecipientID() == $current_user->id())) {
      return $elements[] = ['#type' => 'markup', '#markup' => $this->t('You cannot view invoices not related to your account.'),];
    };

    $elements[] = ['#type' => 'markup', '#markup' => $this->t('Invoice №:@id.', ['@id' => $invoice->id()]),];

    if ($invoice->getPaymentStatus() !== 'paid') {
      $elements[] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Invoice not paid'),
      ];
      return $elements;
    };

    $elements[] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Service provided:'),
    ];

    ///** @var \Drupal\Core\Extension\ThemeHandler $theme_handler */
    //$theme_handler = \Drupal::service('theme_handler');
    //$logo = $theme_handler->getThemeSetting('logo.url', 'bartik');
    ///** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
    //$theme_manager = \Drupal::service('theme.manager');
    //$logo = \Drupal::theme()->getActiveTheme()->getLogo();
    //$elements[] = [
    //  '#theme' => 'image_style', '#style_name' => 'thumbnail',
    //  '#uri' => theme_get_setting('logo.url'),//'public://image.png'
    //];

    /** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
    $theme_manager = \Drupal::service('theme.manager');
    /** @var \Symfony\Component\HttpFoundation\Request $request */
    $request = \Drupal::request();
    $elements[] = [
      '#type' => 'markup',
      '#markup' => '<img src="' . $request->getBasePath() . '/' . $theme_manager->getActiveTheme()->getLogo() . '" alt="Logo" width="50" height="50">',
    ];
    $site_config = $this->config('system.site');
    $elements[] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Name: @name.', ['@name' => $site_config->get('name')]),
    ];
    $elements[] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Slogan: @slogan.', [
        '@slogan' => $site_config->get('slogan') ?: $this->t('Fundamentals of space. Intelligent problem solving. We will help you find each other and formalize relationships in the real estate market.'),
      ]),
    ];

    $data_build = $data_item = $data_total = null;
    if ($invoice->getTarget() == 'reservation') {
      /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $reference_item */
      $reference_item = $invoice->adjust[0];
      $data_build = [
        'bill_to' => ['data' => ['#theme' => 'username', '#account' => $invoice->getRecipient(),]],
        'shop_to' => ['data' => ['#theme' => 'username', '#account' => $invoice->getOwner(),]],
        'date' => DrupalDateTime::createFromTimestamp($invoice->getChangedTime())->format('H:i:s, j F Y'),
        'subject' => ['data' => $reference_item->view()],
      ];
      $data_item[] = [
        'qty' => '1',
        'description' => $this->t('Room booking fee.'),
        'unit_price' => $invoice->getAmountValue()/100 . ' ' . $invoice->getPaymentCurrency(),
        'amount' => '1',
      ];
      $data_total = $data_item[0]['unit_price'];
    };

    $build = array(
      '#type' => 'table',
      '#caption' => $this->t('Detailed order'),
      '#header' => array(
        $this->t('Bill to'),
        $this->t('Shop to'),
        $this->t('Date'),
        $this->t('Subject'),
      ),
      '#rows' => array(
        $data_build ?: array('xx','xx','xx','xx'),
      ),
      '#empty' => $this->t('There is no data'),
      '#responsive' => FALSE,
      '#sticky' => TRUE,
    );

    $item = array(
      '#type' => 'table',
      '#caption' => $this->t('Detailed order'),
      '#header' => array(
        $this->t('QTY'),
        $this->t('Description'),
        $this->t('Unit price'),
        $this->t('Amount'),
      ),
      '#rows' => array(
        array('1','2','3','4'),
      ),
      '#empty' => $this->t('There is no data'),
      '#responsive' => FALSE,
      '#sticky' => TRUE,
    );
    if (is_array($data_item)) {
      foreach ($data_item as $k) {
        $item['#rows'][] = [$k['qty'], $k['description'], $k['unit_price'], $k['amount'],];
      };
    } else {
      $item['#rows'][] = array('xx','xx','xx','xx');
    };
    $item['#rows'][] = ['', '', $this->t('Total'), $data_total ?: 'xx',];

    $elements[] = $build;
    $elements[] = $item;
    
    $elements[] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Thank you very much for your order!'),
    ];

    return $elements;

  }






  /* Create an... */
  public function invokeStatusConnection() {
    $elements[] = ['#type' => 'markup', '#markup' => $this->t('Page connection'),];
    return $elements;
  }



}
