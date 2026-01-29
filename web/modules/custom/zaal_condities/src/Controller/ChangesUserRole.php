<?php declare(strict_types = 1);

namespace Drupal\zaal_condities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for zaal_condities routes.
 */
final class ChangesUserRole extends ControllerBase {

  /**
   * Builds the response.
   * @throws \Exception
   */
  public function __invoke(): JsonResponse
  {
    $message = 'NO User for changes role';
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $user_storage = $entity_type_manager->getStorage('user');
    $user_query = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', 'premium_zaal')
      ->accessCheck('FALSE');
    $user_ids = $user_query->execute();
    $users = $user_storage->loadMultiple($user_ids);

    foreach ($users as $usr) {
      $user = $entity_type_manager->getStorage('user')->load($usr->id());
      $expiryDate = ($user && $user->hasField('field_vip_abonnement_vervaldatum')) ? $user->get('field_vip_abonnement_vervaldatum')->value : null;
      if ($expiryDate && $expiryDate <= date('Y-m-d')) {
        $usr->removeRole('premium_zaal');
        $usr->addrole('magnus');
        $usr->save();
        $message = 'User role changed successfully';
      }
    }
    return new JsonResponse($message, 200);
  }
}
