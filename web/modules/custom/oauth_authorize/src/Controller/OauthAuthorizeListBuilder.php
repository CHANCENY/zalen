<?php

namespace Drupal\oauth_authorize\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DrupalDateTime;

class OauthAuthorizeListBuilder extends EntityListBuilder {

  /** @inheritdoc */
  public function buildHeader() {
    $header = [];
    $header['autor'] = $this->t('Owner');
    $header['provider'] = $this->t('Provider');
    $header['created'] = $this->t('Date created');
    $header['changed'] = $this->t('Date changed');
    $header['value'] = $this->t('Value');
    $header['refresh_value'] = $this->t('Refresh value');
    $header['expire'] = $this->t('Expire');
    $header['scopes'] = $this->t('Scopes');
    $header['organization_id'] = $this->t('Organization ID');
    return $header + parent::buildHeader();
  }

  /** @inheritdoc */
  public function buildRow(EntityInterface $oauth2Authorize) {

    /** @var \Drupal\oauth_authorize\Entity\OauthAuthorize $oauth2Authorize */
    $row = [];
    $row['autor']['data'] = [
      '#theme' => 'username',
      '#account' => $oauth2Authorize->getOwner(),
    ];
    $row['provider'] = $oauth2Authorize->toLink();
    $row['created'] = DrupalDateTime::createFromTimestamp($oauth2Authorize->getCreatedTime())->format('H:i:s l j F Y');
    $row['changed'] = DrupalDateTime::createFromTimestamp($oauth2Authorize->getChangedTime())->format('H:i:s l j F Y');
    $row['value'] = substr($oauth2Authorize->getTokenValue(), 0, 6) . '...';
    $row['refresh_value'] = substr($oauth2Authorize->getRefreshToken(), 0, 6) . '...';
    $row['expire']['data']['#markup'] =  $this->t('Expires in @time seconds.<br/>To: @date', [
      '@time' => $oauth2Authorize->getExpireTime(),
      '@date' => DrupalDateTime::createFromTimestamp($oauth2Authorize->getExpireDate())->format('H:i:s l j F Y'),
    ]);
    $row['scopes']['data'] = [
      '#theme' => 'item_list',
      '#items' => $oauth2Authorize->getScopesList(),
      '#empty' => $this->t('No scopes'),
    ];
    $row['organization_id'] = $oauth2Authorize->getOrganizationId();


    return $row + parent::buildRow($oauth2Authorize);
  }

}
