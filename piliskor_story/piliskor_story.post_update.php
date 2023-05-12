<?php

/**
 * @file
 * Post update functions for Piliskor Story.
 */

/**
 * Populate the Run annotation number field.
 */
function piliskor_story_post_update_1() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $order_item_storage = $entity_type_manager->getStorage('commerce_order_item');
  $run_ids = $order_item_storage->getQuery()
    ->condition('type', 'run')
    ->condition('field_annotation_time', 1, '>')
    ->condition('field_run_status', 'success')
    ->sort('field_annotation_time', 'ASC')
    ->execute();
  foreach ($run_ids as $run_id) {
    $run = $order_item_storage->load($run_id);
    $run->field_annotation_number = \Drupal::service('piliskor_story.annotation_utility')->getHighestAnnotationNumber() + 1;
    $run->save();
  }
}
