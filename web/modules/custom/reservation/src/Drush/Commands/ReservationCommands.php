<?php

namespace Drupal\reservation\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Utility\Token;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class ReservationCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a ReservationCommands object.
   */
  public function __construct(
    private readonly Token $token,
  ) {
    parent::__construct();
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'reservation:command-name', aliases: ['foo'])]
  #[CLI\Argument(name: 'arg1', description: 'Argument description.')]
  #[CLI\Option(name: 'option-name', description: 'Option description')]
  #[CLI\Usage(name: 'reservation:command-name foo', description: 'Usage description')]
  public function commandName($arg1, $options = ['option-name' => 'default']) {
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'reservation:token', aliases: ['token'])]
  #[CLI\FieldLabels(labels: [
    'group' => 'Group',
    'token' => 'Token',
    'name' => 'Name'
  ])]
  #[CLI\DefaultTableFields(fields: ['group', 'token', 'name'])]
  #[CLI\FilterDefaultField(field: 'name')]
  public function token($options = ['format' => 'table']): RowsOfFields {
    $all = $this->token->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }


  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'reservation:extra_services_fix', aliases: ['extra_services_fix'])]
  public function extraServicesFix($options = ['format' => 'table']) {
    $rows = [];
    $updated = 0;

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'zaal')
      ->accessCheck(FALSE);

    $or = $query->orConditionGroup()
      ->exists('field_extra_room_services')
      ->exists('field_resuse_menu_and_services');

    $query->condition($or);

    $nids = $query->execute();


    if (empty($nids)) {
      $this->io()->warning('No zaal nodes found with extra room services.');
      return 0;
    }

    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      if ($node->get('field_extra_room_services')->isEmpty()) {
        continue;
      }

      $uid = $node->getOwnerId();

      $items = $node->get('field_extra_room_services')->getValue();
      $items = array_merge($items, $node->get('field_resuse_menu_and_services')->getValue());
      foreach ($items as $item) {
        if (empty($item['target_id'])) {
          continue;
        }

        $paragraph = Paragraph::load($item['target_id']);
        if (!$paragraph) {
          continue;
        }

        // Skip if already set (optional safety)
//        if (!$paragraph->get('field_author')->isEmpty()) {
//          $rows[] = [
//            'nid' => $node->id(),
//            'pid' => $paragraph->id(),
//            'uid' => $uid,
//            'status' => 'Skipped (already set)',
//          ];
//          continue;
//        }

        $paragraph->set('field_author', $uid);
        $paragraph->save();

        $rows[] = [
          'nid' => $node->id(),
          'pid' => $paragraph->id(),
          'uid' => $uid,
          'status' => 'Updated',
        ];

        $updated++;
      }
    }

    // Table output
    if ($options['format'] === 'table') {
      $this->io()->table(
        ['Node ID', 'Paragraph ID', 'User ID', 'Status'],
        array_map(fn($r) => array_values($r), $rows)
      );
    }

    $this->io()->success("Done. Updated {$updated} paragraph(s).");
    return 0;
  }

}
