<?php

namespace Drupal\reservation\Mail;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Html;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxHeader;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Mail\MailManagerInterface;

//
// A simple test class for sending emails.
//

/**
 * Provides a Mail plugin manager.
 *
 * @see \Drupal\Core\Annotation\Mail
 * @see \Drupal\Core\Mail\MailInterface
 * @see plugin_api
 */

class OrderMailManager {

  /**
   * Composes and optionally sends an email message.
   *
   * @param string $module
   *   A module name to invoke hook_mail() on. The {$module}_mail() hook will be
   *   called to complete the $message structure which will already contain
   *   common defaults.
   * @param string $key
   *   A key to identify the email sent. The final message ID for email altering
   *   will be {$module}_{$key}.
   * @param string $to
   *   The email address or addresses where the message will be sent to. The
   *   formatting of this string will be validated with the
   *   @link http://php.net/manual/filter.filters.validate.php PHP email validation filter. @endlink
   *   Some examples are:
   *   - user@example.com
   *   - user@example.com, anotheruser@example.com
   *   - User <user@example.com>
   *   - User <user@example.com>, Another User <anotheruser@example.com>
   * @param string $langcode
   *   Language code to use to compose the email.
   * @param array $params
   *   (optional) Parameters to build the email. Use the key '_error_message'
   *   to provide translatable markup to display as a message if an error
   *   occurs, or set this to false to disable error display.
   * @param string|null $reply
   *   Optional email address to be used to answer.
   * @param bool $send
   *   If TRUE, call an implementation of
   *   \Drupal\Core\Mail\MailInterface->mail() to deliver the message, and
   *   store the result in $message['result']. Modules implementing
   *   hook_mail_alter() may cancel sending by setting $message['send'] to
   *   FALSE.
   *
   * @return array
   *   The $message array structure containing all details of the message. If
   *   already sent ($send = TRUE), then the 'result' element will contain the
   *   success indicator of the email, failure being already written to the
   *   watchdog. (Success means nothing more than the message being accepted at
   *   php-level, which still doesn't guarantee it to be delivered.)
   *
   * @see \Drupal\Core\Mail\MailManagerInterface::mail()
   */
  public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {

    $site_config = \Drupal::config('system.site');
    $site_mail = $site_config->get('mail');

    // Bundle up the variables into a structured array for altering.
    $message = [
      'id' => $module . '_' . $key,
      'module' => $module,
      'key' => $key,
      'to' => $to,
      'from' => $site_mail,
      'reply-to' => $reply,
      'langcode' => $langcode,
      'params' => $params,
      'send' => TRUE,
      'subject' => '',
      'body' => [],
    ];

    // Build the default headers.
    $headers = [
      'MIME-Version' => '1.0',
      'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
      'Content-Transfer-Encoding' => '8Bit',
      'X-Mailer' => 'Drupal',
    ];
    // To prevent email from looking like spam, the addresses in the Sender and
    // Return-Path headers should have a domain authorized to use the
    // originating SMTP server.
    $headers['Sender'] = $headers['Return-Path'] = $site_mail;
    // Make sure the site-name is a RFC-2822 compliant 'display-name'.
    //$headers['From'] = MailHelper::formatDisplayName($site_config->get('name')) . ' <' . $site_mail . '>';
    $mailbox = new MailboxHeader('From', new Address($site_config->get('name')) . ' <' . $site_mail . '>');
    $headers['From'] = $mailbox->getBodyAsString();
    if ($reply) {
      $headers['Reply-to'] = $reply;
    }
    $message['headers'] = $headers;

    // Build the email (get subject and body, allow additional headers) by
    // invoking hook_mail() on this module. We cannot use
    // moduleHandler()->invoke() as we need to have $message by reference in
    // hook_mail().
    if (function_exists($function = $module . '_mail')) {
      $function($key, $message, $params);
    }

    // Retrieve the responsible implementation for this message.
    $mailManager = \Drupal::service('plugin.manager.mail');
    $system = $mailManager->getInstance(['module' => $module, 'key' => $key]);

    // Attempt to convert relative URLs to absolute.
    foreach ($message['body'] as &$body_part) {
      if ($body_part instanceof MarkupInterface) {
        $body_part = Markup::create(Html::transformRootRelativeUrlsToAbsolute((string) $body_part, \Drupal::request()->getSchemeAndHttpHost()));
      }
    }

    // Format the message body. //Опять порезали хтмл
    $message = $system->format($message);

    // Optionally send email.
    if ($send) {
      // The original caller requested sending. Sending was canceled by one or
      // more hook_mail_alter() implementations. We set 'result' to NULL,
      // because FALSE indicates an error in sending.
      if (empty($message['send'])) {
        $message['result'] = NULL;
      }
      // Sending was originally requested and was not canceled.
      else {
        // Ensure that subject is plain text. By default translated and
        // formatted strings are prepared for the HTML context and email
        // subjects are plain strings.
        if ($message['subject']) {
          $message['subject'] = PlainTextOutput::renderFromHtml($message['subject']);
        }
        $message['result'] = $system->mail($message);
        // Log errors.
        if (!$message['result']) {
          \Drupal::logger('mail')
            ->error('Error sending email (from %from to %to with reply-to %reply).', [
            '%from' => $message['from'],
            '%to' => $message['to'],
            '%reply' => $message['reply-to'] ? $message['reply-to'] : t('not set'),
          ]);
          $error_message = $params['_error_message'] ?? t('Unable to send email. Contact the site administrator if the problem persists.');
          if ($error_message) {
            \Drupal::messenger()->addError($error_message);
          }
        }
      }
    }

    return $message;
  }

}
