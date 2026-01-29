<?php

namespace Drupal\room_custom_setting\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Notifies user about successful authentication.
 */
final class UserManagerMail {

  /**
   * The mail handler.
   *
   * @var \Drupal\room_custom_setting\Mail\MailHandler
   */
  protected $mailHandler;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new UserLoginEmail object.
   *
   * @param \Drupal\room_custom_setting\Mail\MailHandler $mail_handler
   *   The mail handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(MailHandler $mail_handler, RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->mailHandler = $mail_handler;
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
  }

  /**
   * Sends email to user.
   *
   * @param $accounts
   * @param $page
   * @param $reservation
   *
   * @return bool
   *   The message status.
   */

  public function send($accounts, $page, $reservation): bool {

//    $accounts['admin']['name'] = $accounts['admin']['source']->getDisplayName();
//    $accounts['admin']['email'] = $accounts['admin']['source']->getEmail();
//    $accounts['admin']['langcode'] = $accounts['admin']['source']->getPreferredLangcode();
//
//    $accounts['owner']['name'] = $accounts['owner']['source']->getDisplayName();
//    $accounts['owner']['email'] = $accounts['owner']['source']->getEmail();
//    $accounts['owner']['langcode'] = $accounts['owner']['source']->getPreferredLangcode();
//
//    $accounts['author']['name'] = $reservation['curent_order']['source']->getAuthorName();
//    $accounts['author']['email'] = $reservation['curent_order']['source']->getAuthorEmail();
//    $accounts['author']['langcode'] = $reservation['curent_order']['source']->getOwner()->getPreferredLangcode();
//
//    $page['title'] = $page['curent_page']['source']->getTitle();
//    $page['url'] = $page['curent_page']['source']->toUrl()->toString();
//    $page['body'] = $page['curent_page']['source']->get('body')->value;
//
//    $reservation['title'] = $reservation['curent_order']['source']->getSubject();
//    $reservation['url'] = $reservation['curent_order']['source']->permalink()->toString();
//    $reservation['date'] = $reservation['curent_order']['source']->getCreatedTime();
//    $reservation['body'] = $reservation['curent_order']['source']->get('reservation_body')->value;
//    $order_data = $reservation['curent_order']['source']->toArray();
//    $reservation['occupied']['start'] = $order_data['field_date_booking'][0]['value'];
//    $reservation['occupied']['end'] = $order_data['field_date_booking'][0]['end_value'];
//
//    $to = $accounts['owner']['email'];
//    $user_agent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
//    $base_url = $this->requestStack->getCurrentRequest()->getBaseUrl();
//    $clean_host = $this->requestStack->getCurrentRequest()->getHost();
//    $client_ip = $this->requestStack->getCurrentRequest()->getClientIp();
//    $site_name = $this->configFactory->get('system.site')->get('name');
//    $site_mail = $this->configFactory->get('system.site')->get('mail');
//    $customer_support = 'support@' . $site_name;
//    $host = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
//    $img_path = \Drupal::service('extension.path.resolver')->getPath('module', 'room_custom_setting');
//
//    $subject = new TranslatableMarkup('New order in to your @site', ['@site' => $this->configFactory->get('system.site')->get('name'),]);
//
//    $body = [
//      'title' => [
//        '#type' => 'html_tag',
//        '#tag' => 'h2',
//        '#value' => new TranslatableMarkup('A new order has appeared on the web site.'),
//      ],
//      'device' => [
//        '#type' => 'html_tag',
//        '#tag' => 'p',
//        '#value' => new TranslatableMarkup('IP-adress: @ipadress. Device: @user_agent.', [
//          '@user_agent' => $user_agent,
//          '@ipadress' => $client_ip,
//        ]),
//      ],
//      'content' => [
//        '#markup' => '<p style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none">' . new TranslatableMarkup('about order') . '</p>',
//      ],
//    ];
//
//    $params = [
//      'id' => 'order_user_manager',
//      'langcode' => $accounts['admin']['langcode'],
//    ];
   // return $this->mailHandler->sendMail($to, $subject, $body, $params);
    return TRUE;
  }

}
