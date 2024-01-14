<?php

namespace Drupal\umami_site_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\Media;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds 'common_image_field' field.
 *
 * @SearchApiProcessor(
 *   id = "common_image_field",
 *   label = @Translation("Common image field"),
 *   description = @Translation("Common field for all content types."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 * )
 */
class UmamiSearchCommonImageField extends ProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setEntityTypeManager($container->get('entity_type.manager'));
    return $plugin;
  }

  /**
   * Sets entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::service('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Common image field'),
        'description' => $this->t('Common field for all content types'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['common_image_field'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) : void {
    $node = $item->getOriginalObject()->getValue();
    $uri = '';
    switch ($node->bundle()) {
      case 'page':
        // Skip Basic page contents as they are not included.
        break;

      case 'article':
      case 'recipe':
        // Image will be present in “field_media_image” for both Article and
        // Recipe content types.
        $media = $this->getMediaEntity($node, 'field_media_image');
        if ($media instanceof Media) {
          $uri = $this->getUrlFromMedia($media);
        }
        break;

      case 'blog':
        // Image will be present in paragraphs added in "field_banner" field in
        // blog content type.
        if ($node->hasField('field_banner') && !empty($node->get('field_banner')->referencedEntities())) {
          $paragraph = $node->get('field_banner')->referencedEntities()[0];
          // Get the media entity from banner paragraphs.
          $media = $this->getMediaEntity($paragraph, 'field_media');
          if ($media instanceof Media) {
            $uri = $this->getUrlFromMedia($media);
          }
          break;
        }
    }
    // Save the URL to the field.
    if ($uri) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'common_image_field');
      foreach ($fields as $field) {
        $field->addValue($uri);
      }
    }

  }

  /**
   * Helper function to get the URI of of the media item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string $field_name
   *   The media reference field name.
   *
   * @return \Drupal\media\Entity\Media|null
   *   The media entity.
   */
  public function getMediaEntity(EntityInterface $entity, string $field_name) {
    if ($entity->hasField($field_name)) {
      return $entity->$field_name->entity;
    }

    return NULL;
  }

  /**
   * Helper function to get the media URL from media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media item.
   *
   * @return string
   *   URI of the media.
   */
  public function getUrlFromMedia(Media $media) {
    $url = '';
    if (!empty($media)) {
      switch ($media->bundle()) {
        case 'image':
          $url = $media->field_media_image->entity?->createFileUrl();
          break;
      }
      return $url;
    }
  }

}
