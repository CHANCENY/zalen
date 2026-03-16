<?php

namespace Drupal\reservation\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ZaalController extends ControllerBase
{
  public function previewMenuServicesParagraphs(Request $request)
  {
    $paragraphs = json_decode($request->getContent(), true);
    $paragraphIds = array_column($paragraphs, 'value');

    if (empty($paragraphIds)) {
      return new JsonResponse([]);
    }

    // load paragraphs
    $paragraphs = $this->entityTypeManager()->getStorage('paragraph')->loadMultiple($paragraphIds);

    $list = [];

    foreach ($paragraphs as $paragraph) {
      $file = $paragraph->get('field_extra_service_image')->getValue()[0]['target_id'] ?? null;
      $image = null;
      if (is_numeric($file)) {
        $file = File::load($file);
        if ($file) {
          // Convert to web URL
          $image = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
        }

      }

      // Make sure $url is already built as you did:
      $url = Url::fromRoute('reservation.reservation.paragraph.edit', [
        'paragraph' => $paragraph->id(),
        'redirect' => Drupal::request()->getRequestUri(),
      ], [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-drupal-link-system-path' => '/reservation/room/menus-services/paragraph/' . $paragraph->id() . '/edit',
         // 'data-once' => 'ajax'
          ],
      ]);

      // Build the render array for the link
      $link = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $url,
        '#attributes' => $url->getOptions()['attributes'] ?? [],
      ];

      // Render it to HTML if you need markup directly:
      $link_markup = \Drupal::service('renderer')->render($link);

      $list[] = [
        'id' => $paragraph->id(),
        'title' => ucfirst($paragraph->get('field_is_service_or_menu')->value). ":" . $paragraph->get('field_service_short_description')->value,
        'description' => $paragraph->get('field_service_description')->value,
        'currency' => \Drupal::service('reservation.currencies')->getSymbol($paragraph->get('field_service_currency')->value),
        'price' => $paragraph->get('field_service_amount')->value,
        'image' => $image,
        'link' => $link_markup,

      ];
    }

    // renderer array for previews
    $build = [
      '#theme' => 'menu_services_preview',
      '#title' => 'Services Preview',
      '#content' => NULL,
      '#list' => $list,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    $renderer = \Drupal::service('renderer');
    $html = $renderer->renderRoot($build);
    return new JsonResponse([
      'html' =>  $html
    ]);
  }
}
