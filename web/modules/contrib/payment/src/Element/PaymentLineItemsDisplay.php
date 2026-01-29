<?php

namespace Drupal\payment\Element;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\currency\FormElementCallbackTrait;
use Drupal\payment\LineItemCollectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an element to display payment line items.
 *
 * @RenderElement("payment_line_items_display")
 */
class PaymentLineItemsDisplay extends FormElement implements ContainerFactoryPluginInterface {

  use FormElementCallbackTrait;

  /**
   * The currency storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $currencyStorage;

  /**
   * Constructs a new instance.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\Core\Entity\EntityStorageInterface $currency_storage
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, EntityStorageInterface $currency_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currencyStorage = $currency_storage;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');

    return new static($configuration, $plugin_id, $plugin_definition, $container->get('string_translation'), $entity_type_manager->getStorage('currency'));
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $plugin_id = $this->getPluginId();

    return [
      // A \Drupal\payment\LineItemCollectionInterface instance (required).
      '#payment_line_items' => NULL,
      '#pre_render' => [[get_class($this), 'instantiate#preRender#' . $plugin_id]],
    ];
  }

  /**
   * Implements form #pre_render callback.
   *
   * @throws \InvalidArgumentException
   */
  public function preRender(array $element) {
    if (!isset($element['#payment_line_items']) || !($element['#payment_line_items'] instanceof LineItemCollectionInterface)) {
      throw new \InvalidArgumentException(sprintf('#payment_line_items must be an instance of %s.', LineItemCollectionInterface::class));
    }

    $element['table'] = [
      '#empty' => $this->t('There are no line items.'),
      '#header' => [$this->t('Description'), $this->t('Quantity'), $this->t('Amount'), $this->t('Total')],
      '#type' => 'table',
    ];

    /** @var \Drupal\payment\LineItemCollectionInterface $payment_line_items */
    $payment_line_items = $element['#payment_line_items'];

    foreach ($payment_line_items->getLineItems() as $delta => $payment_line_item) {
      /** @var \Drupal\currency\Entity\CurrencyInterface $currency */
      $currency = $this->currencyStorage->load($payment_line_item->getCurrencyCode());
      $element['table']['line_item_' . $payment_line_item->getName()] = [
        '#attributes' => [
          'class' => [
            'payment-line-item',
            'payment-line-item-name-' . $payment_line_item->getName(),
            'payment-line-item-plugin-' . $payment_line_item->getPluginId(),
          ],
        ],
        'description' => [
          '#attributes' => [
            'class' => ['payment-line-item-description'],
          ],
          '#markup' => $payment_line_item->getDescription(),
        ],
        'quantity' => [
          '#attributes' => [
            'class' => ['payment-line-item-quantity'],
          ],
          '#markup' => $payment_line_item->getQuantity(),
        ],
        'amount' => [
          '#attributes' => [
            'class' => ['payment-line-item-amount'],
          ],
          '#markup' => $currency->formatAmount($payment_line_item->getAmount()),
        ],
        'total' => [
          '#attributes' => [
            'class' => ['payment-line-item-amount-total'],
          ],
          '#markup' => $currency->formatAmount($payment_line_item->getTotalAmount()),
        ],
      ];
    }

    /** @var \Drupal\currency\Entity\CurrencyInterface $payment_line_items_currency */
    $payment_line_items_currency = $this->currencyStorage->load($payment_line_items->getCurrencyCode());
    $element['table']['payment_total'] = [
      '#attributes' => [
        'class' => ['payment-amount'],
      ],
      'label' => [
        '#attributes' => [
          'class' => ['payment-amount-label'],
          'colspan' => 3,
        ],
        '#markup' => $this->t('Total amount'),
      ],
      'total' => [
        '#attributes' => [
          'class' => ['payment-amount-total'],
        ],
        '#markup' => $payment_line_items_currency->formatAmount($payment_line_items->getAmount()),
      ],
    ];

    return $element;
  }

}
