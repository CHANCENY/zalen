<?php

namespace Drupal\google_place_field\Controller;

use Drupal\google_place_field\GoogleApiCaller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GooglePlaceController {

  public function autocomplete(Request $request): JsonResponse {

    $query = $request->query->get('q');
    if (!$query) {
      return new JsonResponse([]);
    }

    // Get API key from module config
    $googleApiCaller = new GoogleApiCaller(\Drupal::config('google_place_field.settings'));
    $results =$googleApiCaller->searchLocation($query);

    $suggestions = [];

    if (!empty($results['places'])) {
      foreach ($results['places'] as $place) {
        $displayName = $place['displayName']['text'] ?? '';
        $address = $place['formattedAddress'] ?? '';
        $place_id = $place['id'] ?? '';
        $map_url = $place['googleMapsUri'] ?? '';

        $suggestions[] = [
          'value' => $displayName . ' - ' . $address . " (" . $place_id . ")",
          'label' => $displayName . ' - '.$address,
          'place_id' => $place_id,
          'address' => $address,
          'map_url' => $map_url,
        ];
      }
    }

    return new JsonResponse($suggestions);
  }


  public function mapPreview(string $place_id) {
    $api_key = \Drupal::config('google_place_field.settings')->get('api_key');

    if (!$place_id || !$api_key) {
      return [
        '#markup' => '<div>Map not available</div>',
      ];
    }

    // Build the Google Maps Embed URL server-side
    $embed_url = 'https://www.google.com/maps/embed/v1/place?key=' . $api_key . '&q=place_id:' . $place_id;

    return new Response('<iframe src="' . $embed_url . '" width="100%" height="300" allowfullscreen loading="lazy"></iframe>',200,[
      'Content-Type' => 'text/html',
    ]);

  }

}
