<?php

namespace Drupal\reservation\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Utility\Mail as MailHelper;

/**
 * Notifies user about successful authentication.
 */
final class UserOwnerMail {

  /**
   * The mail handler.
   *
   * @var \Drupal\reservation\Mail\MailHandler
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
   * @param \Drupal\reservation\Mail\MailHandler $mail_handler
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
   *
   * @return bool
   *   The message status.
   */

  public function send($accounts, $page, $reservation): bool {

    $accounts['admin']['name'] = $accounts['admin']['source']->getDisplayName();
    $accounts['admin']['email'] = $accounts['admin']['source']->getEmail();
    $accounts['admin']['langcode'] = $accounts['admin']['source']->getPreferredLangcode();

    $accounts['owner']['name'] = $accounts['owner']['source']->getDisplayName();
    $accounts['owner']['email'] = $accounts['owner']['source']->getEmail();
    $accounts['owner']['langcode'] = $accounts['owner']['source']->getPreferredLangcode();

    $accounts['author']['name'] = $reservation['curent_order']['source']->getAuthorName();
    $accounts['author']['email'] = $reservation['curent_order']['source']->getAuthorEmail();
    $accounts['author']['langcode'] = $reservation['curent_order']['source']->getOwner()->getPreferredLangcode();

    $page['title'] = $page['curent_page']['source']->getTitle();
    $page['url'] = $page['curent_page']['source']->toUrl()->toString();
    $page['body'] = $page['curent_page']['source']->get('body')->value;

    $reservation['title'] = $reservation['curent_order']['source']->getSubject();
    $reservation['url'] = $reservation['curent_order']['source']->permalink()->toString();
    $reservation['date'] = $reservation['curent_order']['source']->getCreatedTime();
    $reservation['body'] = $reservation['curent_order']['source']->get('reservation_body')->value;
    $order_data = $reservation['curent_order']['source']->toArray();
    $reservation['occupied']['start'] = $order_data['field_date_booking'][0]['value'];
    $reservation['occupied']['end'] = $order_data['field_date_booking'][0]['end_value'];

    $to = $accounts['owner']['email'];
    $user_agent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
    $base_url = $this->requestStack->getCurrentRequest()->getBaseUrl();
    $clean_host = $this->requestStack->getCurrentRequest()->getHost();
    $client_ip = $this->requestStack->getCurrentRequest()->getClientIp();
    $site_name = $this->configFactory->get('system.site')->get('name');
    $site_mail = $this->configFactory->get('system.site')->get('mail');
    $subject = new TranslatableMarkup('New order in to your @site account', [
      '@site' => $site_name,
    ]);
    $customer_support = 'support@' . $site_name;
    $sender_mail = 'info@' . $site_name;
    $host = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $img_path = \Drupal::service('extension.path.resolver')->getPath('module', 'reservation');

    $body = [

      'content' => [
        '#markup' => \Drupal\Core\Render\Markup::create(preg_replace('/\s+/', ' ', '
        <table style="max-width:100%;">
            <tbody>
                <tr style="max-width:100%;">
                    <td style="max-width:100%;" class="mailContainer">
                        <div style="overflow-x:hidden;overflow-y:auto;" id="message_body">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#ebeef0">
                                <tbody>
                                    <tr>
                                        <td width="100%" style="background-color:#ffffff;border-bottom:1px solid #d7dcdf;padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ffffff" style="border-radius:0">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td style="padding:12px 20px;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <a href="' .
                                                                            $host
                                                                            . '"style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank">
                                                                            <img src="' .
                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/logo.png')
                                                                            . '" width="143" height="35" style="width:143;height:35;border:0;border-radius:0;display:inline-block">
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td height="18"></td>
                                    </tr>
                                    <tr>
                                        <td width="100%" style="padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ffffff" style="border-radius:3px 3px 0 0">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td style="padding:35px 20px 20px;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <span style="font-size:18px;font-weight:bold;text-decoration:none;color:#010101">' .
                                                                            new TranslatableMarkup('Hello, @account_user_name', [
                                                                              '@account_user_name' => $accounts["owner"]["name"],])
                                                                            . '!</span>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td class="content" style="padding:0 20px;color:#424242;font-size:16px;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td align="left" style="width:auto;padding:0 10px 0 0px;vertical-align:middle">' .
                                                                                          new TranslatableMarkup('In the announcement created by you ')
                                                                                          . '<b>"</b><a href="' .
                                                                                            $page['url']
                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank">' .
                                                                                            $page['title']
                                                                                            . '</a><b>"</b>' .
                                                                                            new TranslatableMarkup('new order ')
                                                                                            . '<b>"</b><a href="' .
                                                                                            $reservation['url']
                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank">' .
                                                                                            $reservation['title']
                                                                                          . '</a><b>"</b>.
                                                                                        </td>
                                                                                        <td class="width-120" align="left" style="width:125px;padding:0 0px 0 10px;vertical-align:top">
                                                                                            <img class="width-120 height-auto" src="' .
                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/banner.png')
                                                                                            . '" width="125" height="115" style="width:125;height:115;border:0;border-radius:0;display:inline-block">
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding:20px 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="padding:0 0 0 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                                          <span style="font-size:inherit;font-weight:bold;text-decoration:none;color:inherit">' .
                                                                                                          new TranslatableMarkup('Order details: ')
                                                                                                          . '</span>' .
                                                                                                          mb_strimwidth(\Drupal\Core\Mail\MailFormatHelper::htmlToText($reservation['body']), 0, 200, "...")
                                                                                                        . '</td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="padding:0 0 0 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                                            <span style="font-size:inherit;font-weight:bold;text-decoration:none;color:inherit">' .
                                                                                                            new TranslatableMarkup('Order time: ')
                                                                                                            . '</span>' .
                                                                                                            DrupalDateTime::createFromTimestamp($reservation['occupied']['start'])->format('dS F, Y, g:i a') . ' :: ' .
                                                                                                            DrupalDateTime::createFromTimestamp($reservation['occupied']['end'])->format('dS F, Y, g:i a')
                                                                                                        . '</td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="padding:0 0 0 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                                            <span style="font-size:inherit;font-weight:bold;text-decoration:none;color:inherit">' .
                                                                                                            new TranslatableMarkup('Publication time: ')
                                                                                                            . '</span>' .
                                                                                                            DrupalDateTime::createFromTimestamp($reservation['date'])->format('dS F, Y, g:i a')
                                                                                                        . '</td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td align="center">
                                                                                            <table border="0" cellspacing="0" cellpadding="0">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td bgcolor="#ff5722"style="padding:10px 25px 9px 25px;border-radius:3px;background-color:#ff5722" align="center">
                                                                                                            <a class="button" href="' .
                                                                                                              $reservation['url']
                                                                                                              . '" style="display:inline-block;text-decoration:none;font-size:16px;line-height:20px;font-weight:bold;color:#ffffff" target="_blank">' .
                                                                                                              new TranslatableMarkup('View order')
                                                                                                              . '</a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding:20px 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                            <table width="100%" height="23" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td height="11">
                                                                                                        </td>
                                                                                                        <td width="44" height="23" rowspan="3" valign="middle" align="center">
                                                                                                            <img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/logo_cuted.png')
                                                                                                            . '" width="24" height="23" style="width:24;height:23;border:0;border-radius:0;display:inline-block">
                                                                                                        </td>
                                                                                                        <td height="11">
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                    <tr>
                                                                                                        <td style="border-top:1px solid #ebeef0">
                                                                                                        </td>
                                                                                                        <tdstyle="border-top:1px solid #ebeef0">
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                    <tr>
                                                                                                        <td></td>
                                                                                                        <td></td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding:30px 20px 20px;color:#424242;font-size:inherit;border-radius:0;border:6px solid #1e905d">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td align="center">
                                                                                                            <span style="font-size:inherit;font-weight:bold;text-decoration:none;color:inherit">' .
                                                                                                            new TranslatableMarkup('How to increase conversions and awareness')
                                                                                                            . '?</span>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                            <table border="0" cellpadding="0" cellspacing="0" height="20" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="height:20px">
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td align="center">
                                                                                                            <span style="font-size:inherit;font-weight:normal;text-decoration:none;color:inherit"><a href="' .
                                                                                                            $host . 'zaal-huren'
                                                                                                              . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank">' .
                                                                                                              new TranslatableMarkup('Rent a room')
                                                                                                              . '</a>' .
                                                                                                              new TranslatableMarkup(' in categories that interest you and earn more than your competitors.')
                                                                                                              . '<a href="' .
                                                                                                            $host . 'over-zalen'
                                                                                                              . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank">' .
                                                                                                              new TranslatableMarkup('Read the details')
                                                                                                              . '</a>' .
                                                                                                              new TranslatableMarkup(' in the reference section of the site.')
                                                                                                              . '</span>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="100%" style="padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ffffff" style="border-radius:0">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td style="padding:50px 20px 0;color:#424242;font-size:16px;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td height="11"></td>
                                                                                        <td rowspan="3" valign="middle" width="280" style="padding:0 10px;text-align:center;font-weight:bold;color:#424242">' .
                                                                                        ucfirst(new TranslatableMarkup('@site in your smartphone', ['@site' => $site_name,],))
                                                                                        . '</td>
                                                                                        <td height="11"></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td style="border-top:2px solid #1e905d">
                                                                                        </td>
                                                                                        <td style="border-top:2px solid #1e905d">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" height="40" width="100%">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="height:40px"></td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td align="center" style="width:auto;padding:0 10px 0 0px;vertical-align:middle">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="130" align="center">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="padding:0 0 0 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                                            <a href="' .
                                                                                                            'https://play.google.com/store/apps/details'
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/app_google_play.png')
                                                                                                            . '" width="130" height="auto" style="width:130px;height:auto;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                        <td align="center" style="width:auto;padding:0 0px 0 10px;vertical-align:middle">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="130" align="center">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td style="padding:0 0 0 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                                                            <a href="' .
                                                                                                            'https://apps.apple.com'
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/app_app_store.png')
                                                                                                            . '" width="130" height="auto" style="width:130px;height:auto;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="100%" style="padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ffffff" style="border-radius:0 0 3px 3px">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td style="padding:20px 0;color:#424242;font-size:inherit;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <table width="100%" height="23" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td height="11"></td>
                                                                                        <td width="44" height="23" rowspan="3" valign="middle" align="center"><img src="' .
                                                                                        \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/logo_cuted.png')
                                                                                        . '" width="24" height="23" style="width:24;height:23;border:0;border-radius:0;display:inline-block">
                                                                                        </td>
                                                                                        <td height="11"></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td style="border-top:1px solid #ebeef0">
                                                                                        </td>
                                                                                        <td style="border-top:1px solid #ebeef0">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="100%" style="padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ffffff" style="border-radius:0">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td class="footer" style="padding:0 20px 20px;color:#424242;font-size:16px;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">
                                                                            <table class="grid" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed">
                                                                                <tbody>
                                                                                    <tr class="grid">
                                                                                        <td class="grid-item_indent_20" style="width:370px;padding:0 10px 0 0px">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td align="left" style="width:30px;padding:0 10px 0 0px;vertical-align:middle">
                                                                                                            <a href="compose.php?send_to=' .
                                                                                                            $customer_support
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/headsets.png')
                                                                                                            . '" width="30" height="29" style="width:30;height:29;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                        <td align="left" style="width:auto;padding:0 0px 0 10px;vertical-align:middle">' .
                                                                                                            new TranslatableMarkup('Having difficulties working on the service')
                                                                                                            . '?
                                                                                                            <table border="0" cellpadding="0" cellspacing="0" height="5" width="100%">
                                                                                                                <tbody>
                                                                                                                    <tr>
                                                                                                                        <td style="height:5px">
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </tbody>
                                                                                                            </table><a href="' .
                                                                                                                $host . 'veel-gestelde-vragen'
                                                                                                                . '" style="font-size:inherit;font-weight:normal;color:#424242;text-decoration:underline" target="_blank">' .
                                                                                                                new TranslatableMarkup('Check out the help section')
                                                                                                                . '</a>
                                                                                                            <table border="0" cellpadding="0" cellspacing="0" height="5" width="100%">
                                                                                                                <tbody>
                                                                                                                    <tr>
                                                                                                                        <td
                                                                                                                            style="height:5px">
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </tbody>
                                                                                                            </table><a
                                                                                                                href="compose.php?send_to=' .
                                                                                                                $customer_support
                                                                                                                . '" style="font-size:inherit;font-weight:normal;color:#424242;text-decoration:underline" target="_blank">' .
                                                                                                                new TranslatableMarkup('Write to support')
                                                                                                                . '</a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                        <td class="grid-item_indent_20" style="width:119px;padding:0 0px 0 10px">
                                                                                            <table border="0" cellpadding="0" cellspacing="0" width="auto" align="center" style="table-layout:fixed">
                                                                                                <tbody>
                                                                                                    <tr>
                                                                                                        <td align="left" style="width:33;padding:0 5px 0 5px;vertical-align:top">
                                                                                                            <a href="
                                                                                                            https://www.facebook.com/' . $site_name
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/ic_facebook.png')
                                                                                                            . '" width="33" height="33" style="width:33;height:33;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                        <td align="left" style="width:33;padding:0 5px 0 5px;vertical-align:top">
                                                                                                            <a href="
                                                                                                            https://www.instagram.com/' . $site_name
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/ic_instagram.png')
                                                                                                            . '" width="33" height="33" style="width:33;height:33;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                        <td align="left" style="width:33;padding:0 0px 0 5px;vertical-align:top">
                                                                                                            <a href="
                                                                                                            https://www.youtube.com/c/' . $site_name
                                                                                                            . '" style="font-size:inherit;font-weight:bold;color:#1e905d;text-decoration:none" target="_blank"><img src="' .
                                                                                                            \Drupal::service('file_url_generator')->generateAbsoluteString($img_path . '/img/ic_youtube.png')
                                                                                                            . '" width="33" height="33" style="width:33;height:33;border:0;border-radius:0;display:inline-block"></a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <table border="0" cellpadding="0" cellspacing="0" height="20" width="100%">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="height:20px"></td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>' .
                                                                            new TranslatableMarkup('Yours faithfully')
                                                                            . ',<br> ' . new TranslatableMarkup('service team') . ' ' . ucfirst($site_name) . '
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="100%" style="padding:0 20px">
                                            <table class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#ebeef0" style="border-radius:0">
                                                <tbody>
                                                    <tr>
                                                        <td width="100%">
                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                <tbody>
                                                                    <tr>
                                                                        <td style="padding:20px 0;color:#959ea4;font-size:13px;border-radius:0;border-top:none;border-right:none;border-bottom:none;border-left:none">' .
                                                                        new TranslatableMarkup('You received this newsletter because you are registered at @site. If you do not want to receive our news in the future, then click ', ['@site' => ucfirst($site_name)],)
                                                                            . '<a href="' .
                                                                            $host . 'over-zalen'
                                                                            . '" style="font-size:inherit;font-weight:normal;color:#959ea4;text-decoration:none" target="_blank">' .
                                                                            new TranslatableMarkup('here')
                                                                            . '</a>.</td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>'.
                        '</div>
                    </td>
                </tr>
            </tbody>
        </table>
        ')),
      ],
    ];

    $params = [
      'id' => 'order_user_owner',
      'langcode' => $accounts['owner']['langcode'],
    ];

    $test_mail = null;
    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
