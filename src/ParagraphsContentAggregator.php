<?php

namespace Drupal\umami_site_search;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helper service that gives content from all paragraphs in a node.
 */
class ParagraphsContentAggregator {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository = NULL) {
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * Loads all text contents from paragraphs and returns the concatanated text.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node object.
   *
   * @return string
   *   Aggregated text from all paragraphs in the provided entity.
   */
  public function getContentFromAllParagraphs(Node $node): string {
    $concatanated_string = '';
    // Get the paragarph reference fields of the node.
    $node_paragraph_fields = $this->getEntityFieldsAsPerWeight($node, 'node');
    if ($node_paragraph_fields) {
      foreach ($node_paragraph_fields as $field_name) {
        $field_definition = $node->getFieldDefinition($field_name);
        if ($field_definition) {
          $field_storage_definition = $field_definition->getFieldStorageDefinition();
          $field_settings = $field_storage_definition->getSettings();
          if (isset($field_settings['target_type']) && $field_settings['target_type'] == "paragraph") {
            if (!$node->get($field_name)->isEmpty()) {
              foreach ($node->get($field_name)->referencedEntities() as $paragraph_item) {
                $concatanated_string .= $this->getContentsFromParagraph($paragraph_item);
              }
            }
          }
        }
      }
    }
    return $concatanated_string;
  }

  /**
   * Retrives field names of an entity as per weight in form display.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node/paragraph entity.
   * @param string $type
   *   Node or paragraph.
   */
  public function getEntityFieldsAsPerWeight(EntityInterface $entity, string $type = 'paragraph'): array {
    $form_display = $this->entityDisplayRepository->getFormDisplay($type, $entity->bundle(), 'default');
    $fields = $form_display?->get('content');
    if (!empty($fields)) {
      // Sort the fields according to weight.
      uasort($fields, function ($a, $b) {
        return $a['weight'] - $b['weight'];
      });

      if ($type == 'node') {
        // Return only the paragraph reference fields.
        $paragraph_fields = array_filter($fields, function ($field) {
          if (isset($field['type'])) {
            return ($field['type']) ? str_contains($field['type'], 'paragraph') : FALSE;
          }
          else {
            return FALSE;
          }
        });

        return array_keys($paragraph_fields);
      }
      else {
        return array_keys($fields);
      }
    }
    return [];
  }

  /**
   * Get contents from the paragarph.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph_item
   *   The paragraph entity.
   */
  public function getContentsFromParagraph(Paragraph $paragraph_item): string {
    $string = '';
    $fields_as_per_form_display = $this->getEntityFieldsAsPerWeight($paragraph_item);
    foreach ($fields_as_per_form_display as $field_name) {
      $field_definition = $paragraph_item->getFieldDefinition($field_name);
      if ($field_definition) {
        $field_storage_definition = $field_definition->getFieldStorageDefinition();
        // Skip base fields.
        if ($field_storage_definition->isBaseField()) {
          continue;
        }

        // Handle text fields.
        $field_type = $field_definition->getType();
        if (in_array($field_type, ['text_long', 'string', 'string_long'])) {
          if (!$paragraph_item->get($field_name)->isEmpty()) {
            $value = $paragraph_item->get($field_name)->getValue();
            $value = strip_tags($value[0]['value']);
            $string .= trim($value) . ' ';
          }
        }
        // Handle other paragraph fields.
        elseif ($field_type == 'entity_reference_revisions') {
          $field_settings = $field_storage_definition->getSettings();
          if (isset($field_settings['target_type']) && $field_settings['target_type'] == "paragraph") {
            if (!$paragraph_item->get($field_name)->isEmpty()) {
              foreach ($paragraph_item->get($field_name)->referencedEntities() as $inner_paragraph_item) {
                $string .= $this->getContentsFromParagraph($inner_paragraph_item);
              }
            }
          }
        }
      }
    }
    return $string;
  }

}
