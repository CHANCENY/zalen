<?php

namespace Drupal\reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
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
      $list[] = [
        'id' => $paragraph->id(),
        'title' => ucfirst($paragraph->get('field_is_service_or_menu')->value). ":" . $paragraph->get('field_service_short_description')->value,
        'description' => $paragraph->get('field_service_description')->value,
        'currency' => \Drupal::service('reservation.currencies')->getSymbol($paragraph->get('field_service_currency')->value),
        'price' => $paragraph->get('field_service_amount')->value,
        'image' => $image,
      ];
    }

    return new JsonResponse($list);
  }
}
