<?php

namespace Drupal\piliskor_story\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a formatter to display story contributors.
 *
 * @FieldFormatter(
 *   id = "piliskor_story_uids_for_css",
 *   module = "piliskor_story",
 *   label = @Translation("UIDs for CSS"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class UidsForCssFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $uids = [];
    if ($items->isEmpty()) {
      return [];
    }
    foreach ($items as $item) {
      foreach (explode('-', $item->value) as $uid) {
        if (!in_array($uid, $uids)) {
          $uids[] = $uid;
        }
      }
    }
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    $list_items = [];
    foreach ($users as $user) {
      $list_items[$user->id()] = $user->get('field_full_name')->value;
    }
    // Words with accented letters need Collator to be sorted properly.
    $collator = new \Collator('hu_HU');
    $collator->asort($list_items);
    foreach ($list_items as $key => &$list_item) {
      $list_item = [
        '#wrapper_attributes' => [
          'class' => ['story-color-' . $key, 'colorize'],
          'data-uid' => 'story-uid-' . $key,
        ],
        '#markup' => $list_item,
      ];
    }
    return [
      [
        'names' => [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $list_items,
          '#attributes' => ['class' => 'story-contributors'],
        ],
        'show_all' => [
          '#prefix' => '<div class="show-all">',
          '#suffix' => '</div>',
          '#markup' => $this->t('Show everyone'),
        ],
        'hide_all' => [
          '#prefix' => '<div class="hide-all">',
          '#suffix' => '</div>',
          '#markup' => $this->t('Hide everyone'),
        ],
      ],
    ];
  }

}
