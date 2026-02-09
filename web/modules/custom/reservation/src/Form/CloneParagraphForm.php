<?php

namespace Drupal\reservation\Form;

use Drupal;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\HttpFoundation\Request;

class CloneParagraphForm extends FormBase
{


  public function __construct(protected $paragraph, protected string $redirectUrl)
  {
  }

  public static function create(ContainerInterface|\Symfony\Component\DependencyInjection\ContainerInterface $container): CloneParagraphForm|static
  {
    $pid = $container->get('request_stack')->getCurrentRequest()->get('paragraph');
    $redirectUrl = $container->get('request_stack')->getCurrentRequest()->get('redirect');
    return new static(
      $pid,
      $redirectUrl
    );
  }

  public function getFormId()
  {
    return 'clone_paragraph_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['#tree'] = TRUE;

    $paragraph = Paragraph::load($this->paragraph);
    $request = Drupal::request();

    $service_type = ucfirst($paragraph->get('field_is_service_or_menu')->value);
    $title = $this->t('Edit @service_type', ['@service_type' => $service_type]);

    // Add AJAX wrapper for silent submission
    $form['#prefix'] = '<div id="clone-paragraph-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['markup_wrapper'] = [
      '#markup' => $title
    ];

    $form['redirect_url'] = [
      '#type' => 'hidden',
      '#value' => $request->headers->get('referer'),
    ];

    $form['fieldset_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t($service_type.' Details'),
      '#description' => $this->t('Edit the '.$service_type.' information below.'),
    ];

    // Basic Information Section
    $form['fieldset_wrapper']['short_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t($service_type.' Name'),
      '#required' => TRUE,
      '#default_value' => $paragraph->get('field_service_short_description')->value,
      '#description' => $this->t('Enter a descriptive name for this '.strtolower($service_type)),
      '#weight' => 0,
    ];

    $form['fieldset_wrapper']['service_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t($service_type.' Description'),
      '#required' => FALSE,
      '#default_value' => $paragraph->get('field_service_description')->value ?? '',
      '#format' => $paragraph->get('field_service_description')->format ?? 'basic_html',
      '#description' => $this->t('Provide a detailed description of the '.strtolower($service_type).'.'),
      '#rows' => 3,
      '#weight' => 1,
    ];

    $form['fieldset_wrapper']['is_service_or_menu'] = [
      '#type' => 'radios',
      '#title' => $this->t($service_type.' Type'),
      '#options' => [
        'service' => $this->t('Service'),
        'menu' => $this->t('Menu')
      ],
      '#required' => TRUE,
      '#default_value' => $paragraph->get('field_is_service_or_menu')->value,
      '#description' => $this->t('Select whether this is a '.strtolower($service_type).' or a menu item.'),
      '#weight' => 2,
    ];

    // Pricing Section
    $form['fieldset_wrapper']['pricing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pricing Information'),
      '#weight' => 3,
    ];

    $form['fieldset_wrapper']['pricing']['service_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount'),
      '#required' => TRUE,
      '#default_value' => $paragraph->get('field_service_amount')->value,
      '#min' => 0,
      '#step' => 0.01,
      '#description' => $this->t('Enter the price amount.'),
    ];

    $form['fieldset_wrapper']['pricing']['service_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => [
        'eur' => $this->t('EUR (Euro)'),
        'usd' => $this->t('USD (US Dollar)'),
      ],
      '#required' => TRUE,
      '#default_value' => $paragraph->get('field_service_currency')->value,
      '#description' => $this->t('Select the currency for this '.strtolower($service_type).'.'),
    ];

    $form['fieldset_wrapper']['pricing']['service_minimum_order'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Order'),
      '#required' => false,
      '#default_value' => $paragraph->get('field_service_minimum_order')->value,
      '#min' => 0,
      '#description' => $this->t('Minimum quantity required. Enter 0 for no minimum order.'),
    ];

    // Media Section
    $form['fieldset_wrapper']['media'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Media'),
      '#weight' => 4,
    ];

    $form['fieldset_wrapper']['media']['extra_service_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t($service_type.' Image'),
      '#required' => FALSE,
      '#upload_location' => 'public://service-images/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif'], // only images
        'file_validate_size' => [2 * 1024 * 1024], // 2 MB
      ],
      '#description' => $this->t('Upload an image for this service. Allowed formats: png, jpg, jpeg, gif. Maximum size: 2MB.'),
      '#default_value' => $paragraph->get('field_extra_service_image')->target_id ? [$paragraph->get('field_extra_service_image')->target_id] : NULL,
      '#attributes' => [
        'accept' => 'image/*', // ensures only images can be selected in file dialog
      ],
      '#theme' => 'image_widget', // renders as image with preview
      '#preview_image_style' => 'thumbnail', // or any image style you want
    ];

    // Actions with AJAX
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 10,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save '.$service_type.' Details'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'clone-paragraph-form-wrapper',
        'effect' => 'fade',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving service...'),
        ],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['dialog-cancel'],
      ],
    ];

    return $form;
  }

  private function isParagraphAttachedAlready(Paragraph $paragraph, int $nid): bool {
    $pid = $paragraph->id();
    if (!$pid) {
      return FALSE;
    }

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'zaal')
      ->condition('nid', $nid, '=')
      ->accessCheck(FALSE);

    $or = $query->orConditionGroup()
      ->condition('field_extra_room_services', $pid)
      ->condition('field_resuse_menu_and_services', $pid);

    $query->condition($or);

    // 👇 this is the key fix
    $count = $query->count()->execute();
    return $count > 0;
  }

  private function getOneRoom(): array|int
  {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'zaal')
      ->condition('status', 1)
      ->condition('uid', Drupal::currentUser()->id())
      ->accessCheck(FALSE);

    $query->range(0, 1);

    return $query->execute();
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    try {

      $paragraph = Paragraph::load($this->paragraph);
      $redirect_url_value = $form_state->getValue('redirect_url');
      $parsed_url = parse_url($redirect_url_value,PHP_URL_PATH);

      $clonedParagraph = null;
      $edited = false;
      $node = false;
      if (str_ends_with($parsed_url, "/edit")) {

        $nid = substr($parsed_url, 1, -5);
        $nid = substr($nid,  strrpos($nid, "/")+1, strlen($nid));

        if (is_numeric($nid)) {
          if ($this->isParagraphAttachedAlready($paragraph, $nid)) {
            $clonedParagraph = $paragraph;
          } else {
            $clonedParagraph = $paragraph->createDuplicate();
            $edited = true;
            $node = Node::load($nid);
          }
        }
      }
      else {
        $clonedParagraph = $paragraph->createDuplicate();
      }

      // Get form values
      $values = $form_state->getValue('fieldset_wrapper');

      // Update basic fields
      $clonedParagraph->set('field_service_short_description', $values['short_description']);

      // Handle text_format field (service_description)
      $description_value = $values['service_description'];
      if (is_array($description_value)) {
        $clonedParagraph->set('field_service_description', [
          'value' => $description_value['value'],
          'format' => $description_value['format'] ?? 'basic_html',
        ]);
      }
      else {
        $clonedParagraph->set('field_service_description', [
          'value' => $description_value,
          'format' => 'basic_html',
        ]);
      }

      $clonedParagraph->set('field_is_service_or_menu', $values['is_service_or_menu']);

      // Update pricing fields
      $clonedParagraph->set('field_service_amount', $values['pricing']['service_amount']);
      $clonedParagraph->set('field_service_currency', $values['pricing']['service_currency']);
      $clonedParagraph->set('field_service_minimum_order', $values['pricing']['service_minimum_order']);
      $clonedParagraph->set('field_author',['target_id' => Drupal::currentUser()->id()]);

      // Handle image upload
      $image_fids = $form_state->getValue(['fieldset_wrapper', 'media', 'extra_service_image']);
      if (!empty($image_fids[0])) {
        $file = File::load($image_fids[0]);
        if ($file) {
          $file->setPermanent();
          $file->save();
          $clonedParagraph->set('field_extra_service_image', ['target_id' => $file->id()]);
        }
      }

      // Save the cloned paragraph
      $clonedParagraph->save();

//      if ($edited && $node) {
//
//        $olds = $node->get('field_extra_room_services')->getValue();
//        $olds[] = [
//          'target_id' => $clonedParagraph->id(),
//          'target_revision_id' => $clonedParagraph->getRevisionId(),
//        ];
//
//        $node->set('field_extra_room_services', $olds);
//
//        $node->save();
//      }

      $symbol = Drupal::service('reservation.currencies')->getSymbol($clonedParagraph->get('field_service_currency')->value);
      $title = ucfirst($clonedParagraph->get('field_is_service_or_menu')->value);
      $label = "{$title}: {$clonedParagraph->get('field_service_short_description')->value} {$symbol}{$clonedParagraph->get('field_service_amount')->value}";

      // Store success data for AJAX callback
      $pids = [
        'old' => $paragraph->id(),
        'new' => $clonedParagraph->id(),
        'label' => $label,
      ];

      // renderer array for previews
      $base64 = base64_encode(json_encode($pids));
      $redirect_url = $values['redirect_url'] ?? '';

      $form_state->set('clone_success', [
        'pids' => $pids,
        'base64' => $base64,
        'redirect_url' => $redirect_url,
        'message' => $this->t('Service has been successfully cloned.'),
      ]);

      // Log the action
      $this->logger('reservation')->notice('Cloned paragraph @pid to @new_pid with updated values', [
        '@pid' => $paragraph->id(),
        '@new_pid' => $clonedParagraph->id(),
      ]);

    } catch (\Exception $e) {
      // Store error data for AJAX callback
      $form_state->set('clone_error', [
        'message' => $this->t('An error occurred while cloning the service: @error', ['@error' => $e->getMessage()]),
      ]);

      // Log error
      $this->logger('reservation')->error('Failed to clone paragraph: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * AJAX callback for form submission.
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Check for errors
    if ($error = $form_state->get('clone_error')) {
      $response->addCommand(new HtmlCommand('#clone-paragraph-form-wrapper',
        '<div class="messages messages--error">' . $error['message'] . '</div>'
      ));
      return $response;
    }

    // Check for success
    if ($success = $form_state->get('clone_success')) {
      // Close dialog/modal
      $response->addCommand(new CloseDialogCommand());

      // Show a success message
      $response->addCommand(new MessageCommand($success['message']));

      // Insert the new paragraph ID into the noscript element
      $response->addCommand(new HtmlCommand(
        '#clone-paragraph-new-id',
        json_encode($success)
      ));
    }

    return $response;
  }
}
