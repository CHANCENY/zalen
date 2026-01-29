<?php

namespace Drupal\reservation\Form;

use Drupal\reservation\ReservationInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\payment\Payment;
use Drupal\payment\DatabaseQueue;
use Drupal\payment\Tests\Generate;
use Drupal\KernelTests\KernelTestBase;

/**
 * Provides the reservations overview administration form.
 *
 * @internal
 */
class ReservationAdminOverview extends FormBase
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The reservation storage.
   *
   * @var \Drupal\reservation\ReservationStorageInterface
   */
  protected $reservationStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Creates a ReservationAdminOverview form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, ModuleHandlerInterface $module_handler, PrivateTempStoreFactory $temp_store_factory)
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->reservationStorage = $entity_type_manager->getStorage('reservation');
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'reservation_admin_overview';
  }

  /**
   * Form constructor for the reservation overview administration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $type
   *   The type of the overview form ('approval' or 'new').
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = 'new')
  {

    // Build an 'Update options' form.
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Update options'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['container-inline']],
    ];

    if ($type == 'approval') {
      $options['publish'] = $this->t('Publish the selected reservations');
    } else {
      $options['unpublish'] = $this->t('Unpublish the selected reservations');
    }
    $options['delete'] = $this->t('Delete the selected reservations');

    $form['options']['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => 'publish',
    ];
    $form['options']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('UpdateOne'),
    ];

    // Load the reservations that need to be displayed.
    $status = ($type == 'approval') ? ReservationInterface::NOT_PUBLISHED : ReservationInterface::PUBLISHED;
    $header = [
      'subject' => [
        'data' => $this->t('Subject'),
        'specifier' => 'subject',
      ],
      'author' => [
        'data' => $this->t('Author'),
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'posted_in' => [
        'data' => $this->t('Posted in'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'changed' => [
        'data' => $this->t('Updated'),
        'specifier' => 'changed',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'operations' => $this->t('Operations'),
    ];
    $cids = $this->reservationStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', $status)
      ->tableSort($header)
      ->pager(50)
      ->execute();

    /** @var $reservations \Drupal\reservation\ReservationInterface[] */
    $reservations = $this->reservationStorage->loadMultiple($cids);

    // Build a table listing the appropriate reservations.
    $options = [];
    $destination = $this->getDestinationArray();

    $reservationed_entity_ids = [];
    $reservationed_entities = [];

    foreach ($reservations as $reservation) {
      $reservationed_entity_ids[$reservation->getReservationedEntityTypeId()][] = $reservation->getReservationedEntityId();
    }

    foreach ($reservationed_entity_ids as $entity_type => $ids) {
      $reservationed_entities[$entity_type] = $this->entityTypeManager
        ->getStorage($entity_type)
        ->loadMultiple($ids);
    }

    foreach ($reservations as $reservation) {
      /** @var $reservationed_entity \Drupal\Core\Entity\EntityInterface */
      $reservationed_entity = $reservationed_entities[$reservation->getReservationedEntityTypeId()][$reservation->getReservationedEntityId()];
      $reservation_permalink = $reservation->permalink();
      if ($reservation->hasField('reservation_body') && ($body = $reservation->get('reservation_body')->value)) {
        $attributes = $reservation_permalink->getOption('attributes') ?: [];
        $attributes += ['title' => Unicode::truncate($body, 128)];
        $reservation_permalink->setOption('attributes', $attributes);
      }
      $options[$reservation->id()] = [
        'title' => ['data' => ['#title' => $reservation->getSubject() ?: $reservation->id()]],
        'subject' => [
          'data' => [
            '#type' => 'link',
            '#title' => $reservation->getSubject(),
            '#url' => $reservation_permalink,
          ],
        ],
        'author' => [
          'data' => [
            '#theme' => 'username',
            '#account' => $reservation->getOwner(),
          ],
        ],
        'posted_in' => [
          'data' => [
            '#type' => 'link',
            '#title' => $reservationed_entity->label(),
            '#access' => $reservationed_entity->access('view'),
            '#url' => $reservationed_entity->toUrl(),
          ],
        ],
        'changed' => $this->dateFormatter->format($reservation->getChangedTimeAcrossTranslations(), 'short'),
      ];
      $reservation_uri_options = $reservation->toUrl()->getOptions() + ['query' => $destination];
      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $reservation->toUrl('edit-form', $reservation_uri_options),
      ];
      if ($this->moduleHandler->moduleExists('content_translation') && $this->moduleHandler->invoke('content_translation', 'translate_access', [$reservation])->isAllowed()) {
        $links['translate'] = [
          'title' => $this->t('Translate'),
          'url' => $reservation->toUrl('drupal:content-translation-overview', $reservation_uri_options),
        ];
      }
      $options[$reservation->id()]['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    $form['reservations'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No reservations available.'),
    ];

    $form['pager'] = ['#type' => 'pager'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->setValue('reservations', array_diff($form_state->getValue('reservations'), [0]));
    // We can't execute any 'Update options' if no reservations were selected.
    if (count($form_state->getValue('reservations')) == 0) {
      $form_state->setErrorByName('', $this->t('Select one or more reservations to perform the update on.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $operation = $form_state->getValue('operation');
    $cids = $form_state->getValue('reservations');
    /** @var \Drupal\reservation\ReservationInterface[] $reservations */
    $reservations = $this->reservationStorage->loadMultiple($cids);

    if ($operation != 'delete') {
      foreach ($reservations as $reservation) {
        if ($operation == 'unpublish') {
          $reservation->setUnpublished();
        } elseif ($operation == 'publish') {

          $reservation->setPublished();
          // dd($reservation);
          $nid = $reservation->getReservationedEntityId();
          $node_storage = \Drupal::entityTypeManager()->getStorage('node');
          $node = $node_storage->load($nid);
          $field_uurprijs = $node->get('field_uurprijs')->value;
          $field_dagprijs = $node->get('field_dagprijs')->value;
          $field_prijs_per_persoon = $node->get('field_prijs_per_persoon')->value;
          $total_price  = $field_uurprijs + $field_dagprijs + $field_prijs_per_persoon;
          // dd($total_price);
          // dd($field_uurprijs, $field_dagprijs, $field_prijs_per_persoon, $total_price);
          // dd($node);//field_dagprijs, field_uurprijs

          // Mollie integration here
          $reservation->total_price = $total_price;
          $reservation->checkout = $this->getMollieCheckout($reservation);
          // send message to user
          //get user administrator or manager
          $accounts['admin']['source'] = \Drupal\user\Entity\User::load(1);
          //get user owner of a node or zaal
          // $accounts['owner']['source'] = \Drupal\user\Entity\User::load(1);
          $accounts['owner']['source'] = $reservation->getOwner();

          //current node
          $page['curent_page']['source'] = \Drupal\user\Entity\User::load(1);
          \Drupal::service('reservation.user_manager_mail')->send($accounts, $page, $reservation);
          // add payment to db with pending status
          $payment = $this->addPayment2DB($reservation);
          // dd($payment);
          $this->messenger()->addStatus($this->t('Mollie API Response: ' . $payment->get('uuid')->value));
          // dd($payment);
        }
        $reservation->save();
      }

      ///////////////// ######################################################################
      $this->messenger()->addStatus($this->t('The update has been performed.'));
      $form_state->setRedirect('reservation.admin');
    } else {
      $info = [];
      /** @var \Drupal\reservation\ReservationInterface $reservation */
      foreach ($reservations as $reservation) {
        $langcode = $reservation->language()->getId();
        $info[$reservation->id()][$langcode] = $langcode;
      }
      $this->tempStoreFactory
        ->get('entity_delete_multiple_confirm')
        ->set($this->currentUser()->id() . ':reservation', $info);
      $form_state->setRedirect('entity.reservation.delete_multiple_form');
    }
  }
  protected function getMollieCheckout($reservation)
  {
    $payment = null;

    $initial_configuration['payment_settings']['payment_method'] = 'payment';
    $initial_configuration['payment_settings']['payment_provider'] = 'mollie';
    $initial_configuration['payment_settings']['payment_parameter'] = [];

    $managerPaymentProvider = \Drupal::service('plugin.manager.payment_provider');
    $pluginPaymentProvider = $managerPaymentProvider->createInstance($initial_configuration['payment_settings']['payment_provider']);
    // https://eventrooms.eu/web/en/reservation/reply/node/18/field_form_booking#reservation-form
    // $entity_id = $reservation->toArray()['entity_id'][0]['target_id'];
    $entity_id = $reservation->getReservationedEntityId();
    $cid = $reservation->toArray()['cid'][0]['value'];
    $payment_data = [
      "amount" => [
        "currency" => "EUR",
        "value" => sprintf("%.2f", $reservation->total_price) //"10.00" You must send the correct number of decimals, thus we enforce the use of strings
      ],
      "description" => "Order #" . $cid,
      "redirectUrl" => "https://eventrooms.eu/web/en/reservation/reply/node/" . $entity_id . "/field_form_booking#reservation-form",//$reservation->permalink();
      "webhookUrl" => "https://eventrooms.eu/web/en/payment_provider/mollie/webhook/payments",
      "metadata" => [
        "order_id" => $cid,
      ],
    ];
    // \Drupal::service('plugin.manager.payment_provider')::pluginInterface()->payWithClient('payment', $payment_data);
    $payment = $pluginPaymentProvider->payWithClient(
      $initial_configuration['payment_settings']['payment_method'],
      $payment_data,
      $initial_configuration['payment_settings']['payment_parameter']
    );

    // return $payment->_links->checkout->href;
    return $payment->getCheckoutUrl();
  }
  protected function addPayment2DB($reservation)
  {
    ////////////////// Payment database test #######################################
    // dd($reservation);
    $payment = null;
    // $uid = 7;
    $uid = $reservation->toArray()['uid'][0]['target_id'];

    $payment_method = Payment::methodManager()->createInstance('payment_on_mollie');
    $paymentStatusManager = \Drupal::service('plugin.manager.payment.status');
    $paymentMethodManager = \Drupal::service('plugin.manager.payment.method');
    $database = \Drupal::database();
    // $queue_id = 'queue_id';
    $queue_id = 'queue_' . time();
    $queue = new DatabaseQueue($queue_id, $database, \Drupal::service('payment.event_dispatcher'), $paymentStatusManager);
    // $category_id = 'payment';
    $category_id = 'category_payment';// . time();
    //line items
    $line_item_manager = Payment::lineItemManager();
    /** @var \Drupal\currency\ConfigImporterInterface $config_importer */
    $config_importer = \Drupal::service('currency.config_importer');
    $config_importer->importCurrency('EUR');
    $line_items = [
      $line_item_manager->createInstance('payment_basic', [])
        ->setName('Reservation for zaal')
        ->setAmount($reservation->total_price)//40.9
        // The Dutch guilder has 100 subunits, which is most common, but is no
        // longer in circulation.
        ->setCurrencyCode('EUR')
        ->setDescription('Description'),
    ];
    // $payment = Generate::createPayment(2);

    // $payments = \Drupal::entityTypeManager()
    // ->getStorage('payment')
    // ->loadByProperties([
    //   'bundle' => 'payment_on_mollie',
    // ]);
    // // Get the first payment entity.
    // $payment = reset($payments);

    if (!$payment_method) {
      $payment_method = Payment::methodManager()->createInstance('payment_on_mollie');
    }
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = \Drupal\payment\Entity\Payment::create([
      'bundle' => 'payment_on_mollie',
    ]);
    $payment->setCurrencyCode('EUR')
      ->setPaymentMethod($payment_method)
      ->setOwnerId($uid)
      ->setLineItems($line_items);

    $payment->setPaymentStatus($paymentStatusManager->createInstance('payment_pending'));//payment_success
    $payment->save();

    // queue save().
    $queue->save($category_id, $payment->id());

    return $payment;
  }
}
