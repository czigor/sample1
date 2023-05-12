<?php

namespace Drupal\piliskor_story;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\piliskor_run\RunManager;
use Drupal\piliskor_run\TrackManagerInterface;

class AnnotationManager implements AnnotationManagerInterface {

  const METERS_PER_CHAR = 1000;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The track manager.
   *
   * @var \Drupal\piliskor_run\TrackManagerInterface
   */
  protected $trackManager;

  /**
   * The run manager.
   *
   * @var \Drupal\piliskor_run\RunManager
   */
  protected $runManager;

  /**
   * The annotation utility service.
   *
   * @var \Drupal\piliskor_story\AnnotationUtility
   */
  protected $annotationUtility;

  /**
   * Constructs a new RunManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\piliskor_run\TrackManagerInterface
   *   The track manager.$this
   * @param \Drupal\piliskor_run\RunManager
   *   The run manager.
   * @param \Drupal\piliskor_story\AnnotationUtility
   *   The annotation utility service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $translation, TimeInterface $time, TrackManagerInterface $track_manager, RunManager $run_manager, AnnotationUtility $annotation_utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $translation;
    $this->time = $time;
    $this->trackManager = $track_manager;
    $this->runManager = $run_manager;
    $this->annotationUtility = $annotation_utility;
  }

  /**
   * {@inheritdoc}
   */
  public function annotateRun(OrderItemInterface $run) {
    if ($run->get('field_run_status')->value !== 'success') {
      return;
    }

    $run_meters = $this->runManager->getRunLength($run);
    $leftover_meters = $this->annotationUtility->getLeftoverMeters();

    // Get leftover annotation from previous run.
    if ($leftover_meters > 0) {
      $last_annotated_run = $this->annotationUtility->getLastAnnotatedRun();
      $leftover_annotation = $this->getAnnotationForRun($last_annotated_run, -$leftover_meters);
    }
    // Add annotation of this run.
    $annotation = $this->getAnnotationForRun($run, $run_meters);
    //dpm($annotation);
    if (!empty($leftover_annotation)) {
      $annotation = array_merge($leftover_annotation, $annotation);
    }

    // Slice the annotation in 1 km pieces.
    $sliced_annotations = $this->sliceAnnotation($annotation);
    return $sliced_annotations;
  }

  /**
   * Slice up the annotation to pieces corresponding to integer characters.
   *
   * @param array $annotations
   *   An array of annotations.
   * @return array
   *   The array of rearranged annotations. Each value is an array and
   *   corresponds to an integer number of character(s).
   */
  protected function sliceAnnotation(array $annotations) {
    $sliced_annotations = [];
    $sliced_annotation = [];
    $length = 0;

    foreach ($annotations as $annotation) {
      $this->addSlicedAnnotation($annotation, $sliced_annotation, $sliced_annotations, $length);
     }
    return $sliced_annotations;
  }

  /**
   * Helper function to turn an annotation into a sliced annotation.
   *
   * @param array $annotation
   *   An annotation array having keys 'uid', 'run_id', 'length' and 'time'.
   * @param array $sliced_annotation
   *   The sliced annotation array corresponding to this batch of characters.
   * @param array $sliced_annotations
   *   The array of sliced annotation arrays corresponsing to a run and the
   *   leftover of the previous run.
   * @param int $length
   *   The length counter of the annotations.
   */
  protected function addSlicedAnnotation(array $annotation, array &$sliced_annotation, array &$sliced_annotations, int &$length) {
    if ($length + $annotation['length'] <= self::METERS_PER_CHAR) {
      $sliced_annotation[] = $annotation;
      $length += $annotation['length'];
    }
    else {
      if (empty($sliced_annotation)) {
        $leftover = $annotation['length'] % self::METERS_PER_CHAR;
        $annotation['length'] -= $leftover;
      }
      else {
        $length_backup = $annotation['length'];
        $annotation['length'] = self::METERS_PER_CHAR - $length;
        $leftover = $length_backup - $annotation['length'];
      }
      $sliced_annotation[] = $annotation;
      if (count($sliced_annotation) == 1) {
        $sliced_annotation['chars'] = (int) $annotation['length'] / self::METERS_PER_CHAR;
      }
      else {
        $sliced_annotation['chars'] = 1;
      }
      $sliced_annotations[] = $sliced_annotation;
      $sliced_annotation = [];
      $length = 0;
      if ($leftover > 0) {
        $annotation['length'] = $leftover;
        $this->addSlicedAnnotation($annotation, $sliced_annotation, $sliced_annotations, $length);
      }
    }
  }

  /**
   * Gets annotation for a run given a length.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $run
   *   The run.
   * @param int $length
   *   The length of the distance to annotate in meters. If negative, we count
   *   from the end of the run: this is used when getting the leftover
   *   annotations.
   */
  protected function getAnnotationForRun(OrderItemInterface $run, int $length) {
    $read_ids = $this->runManager->getValidReadIds($run);
    $reads = $this->entityTypeManager->getStorage('node')->loadMultiple($read_ids);
    $length_counter = 0;
    $annotations = [];
    $annotation = [
      'uid' => 0,
      'run_id' => $run->id(),
      'time' => 0,
      'length' => 0,
    ];

    while (!empty($reads) && $length_counter < abs($length)) {
      /** @var \Drupal\node\NodeInterface $read */
      if ($length < 0) {
        $read = array_pop($reads);
      }
      else {
        $read = array_shift($reads);
      }
      $segment = $read->get('field_segment');
      // Taking over a run case.
      if ($segment->isEmpty()) {
        if ($length > 0) {
          if ($annotation['uid'] != $read->getOwnerId() && $annotation['length'] > 0) {
            $annotations[] = $annotation;
          }
          $annotation['time'] = $read->getCreatedTime();
          $annotation['uid'] = $read->getOwnerId();
        }
        // Leftover case.
        else {
          $annotation['time'] = $read->getCreatedTime();
          if ($annotation['length'] > 0) {
            $annotations[] = $annotation;
          }
        }
        $annotation['length'] = 0;
        continue;
      }

      $segment_length = $segment->entity->get('field_length')->number;
      // Last segment of leftover.
      if ($segment_length + $length_counter > abs($length)) {
        $segment_length = abs($length) - $length_counter;
      }
      $length_counter += $segment_length;
      $annotation['length'] += $segment_length;
      if ($length < 0) {
        $annotation['uid'] = $read->getOwnerId();
        $annotation['time'] = $read->getCreatedTime();
      }
    }
    $annotations[] = $annotation;
    return $annotations;
  }

}
