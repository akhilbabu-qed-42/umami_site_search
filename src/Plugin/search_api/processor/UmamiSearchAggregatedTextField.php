<?php

namespace Drupal\umami_site_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\umami_site_search\ParagraphsContentAggregator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds 'aggregated_text_field' field.
 *
 * @SearchApiProcessor(
 *   id = "aggregated_text_field",
 *   label = @Translation("Aggregated text field"),
 *   description = @Translation("Aggregates text from all fields"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 * )
 */
class UmamiSearchAggregatedTextField extends ProcessorPluginBase {

  /**
   * The index helper service.
   *
   * @var \Drupal\umami_site_search\ParagraphsContentAggregator
   */
  protected $paragraphsContentAggregator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setParagraphsContentAggregator($container->get('umami_site_search.aggregate_paragraph_contents'));
    return $plugin;
  }

  /**
   * Sets the index helper service.
   *
   * @param \Drupal\umami_site_search\ParagraphsContentAggregator $algolia_index_helper
   *   The index helper service.
   *
   * @return $this
   */
  public function setParagraphsContentAggregator(ParagraphsContentAggregator $algolia_index_helper) {
    $this->paragraphsContentAggregator = $algolia_index_helper;
    return $this;
  }

  /**
   * Retrieves the index helper service.
   *
   * @return \Drupal\umami_site_search\ParagraphsContentAggregator
   *   The index helper service.
   */
  protected function getParagraphsContentAggregator() {
    return $this->paragraphsContentAggregator ?: \Drupal::service('umami_site_search.aggregate_paragraph_contents');
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Aggregated text field'),
        'description' => $this->t('Aggregates text from all fields'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['aggregated_text_field'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) : void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $item->getOriginalObject()->getValue();
    // Fist, get the text from all content type fields.
    $fields_in_order = [
      'body', 'field_body_2', 'field_recipe_instruction', 'field_ingredients',
    ];
    $aggregated_text = '';
    foreach ($fields_in_order as $field) {
      if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
        // The 'field_ingredients' field in recipe content tye is a list field
        // and needs separate logic to get data.
        if ('field_ingredients' === $field) {
          $aggregated_text .= trim(strip_tags($node->get($field)->getString())) . ' ';
        }
        else {
          $aggregated_text .= trim(strip_tags($node->get($field)->value)) . ' ';
        }
      }
    }

    // Concatanate the result with aggregated text from paragraphs.
    $aggregated_text .= $this->getParagraphsContentAggregator()->getContentFromAllParagraphs($node);
    // Save the value to the field.
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'aggregated_text_field');
    foreach ($fields as $field) {
      $field->addValue($aggregated_text);
    }

  }

}
