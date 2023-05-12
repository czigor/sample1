<?php

namespace Drupal\piliskor_story;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\piliskor_run\RunManager;
use Drupal\piliskor_run\TrackManagerInterface;

class AnnotationMarkup {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The annotation manager.
   *
   * @var \Drupal\piliskor_story\AnnotationManager
   */
  protected $annotationManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The markup string to add to display.
   *
   * @var string
   */
  protected string $markup = '';

  /**
   * The popup contents markup.
   *
   * @var string
   */
  protected string $popup = '';

  /**
   * The popup CSS classes.
   *
   * @var array
   */
  protected array $popupClasses = [];

  /**
   * The uid combinations used in popup CSS classes for coloring.
   *
   * @var array
   */
  protected array $uidsForCss = [];

  /**
   * The number of characters (starting from 0) where text annotating
   * should start.
   *
   * @var integer
   */
  protected int $start;

  /**
   * The current position (number of characters) of the annotation processing
   * in the text.
   *
   * @var integer
   */
  protected int $positionPointer = 0;

  /**
   * The current DOM node value being processed.
   *
   * @var string
   */
  protected string $nodeText = '';

  /**
   * The array of sliced annotations.
   *
   * @var array
   */
  protected array $slicedAnnotations = [];

  /**
   * Constructs a new RunManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\piliskor_run\TrackManagerInterface
   *   The track manager.
   * @param \Drupal\piliskor_run\RunManager
   *   The run manager.
   * @param \Drupal\piliskor_story\AnnotationUtility
   *   The annotation utility service.
   * @param \Drupal\piliskor_story\AnnotationManager
   *   The annotation manager.
   * @param \Drupal\Core\Render\Renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $translation, TrackManagerInterface $track_manager, RunManager $run_manager, AnnotationUtility $annotation_utility, AnnotationManager $annotation_manager, Renderer $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $translation;
    $this->trackManager = $track_manager;
    $this->runManager = $run_manager;
    $this->annotationUtility = $annotation_utility;
    $this->annotationManager = $annotation_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function annotationMarkupForBook(OrderItemInterface $run) {
    if ($run->get('field_run_status')->value !== 'success') {
      return;
    }

    $this->init();
    $run_meters = $this->runManager->getRunLength($run);
    $leftover_meters = $this->annotationUtility->getLeftoverMeters();
    $meters_to_annotate = $run_meters + $leftover_meters;
    $chars_for_this_book = floor($meters_to_annotate / AnnotationManager::METERS_PER_CHAR);
    if ($chars_for_this_book > $this->annotationUtility->getCurrentBookUnannotatedCharNumber()) {
      $chars_for_this_book = $this->annotationUtility->getCurrentBookUnannotatedCharNumber();
    }

    $this->slicedAnnotations = $this->annotationManager->annotateRun($run);
    while (!empty($this->slicedAnnotations)) {
      $current_book = $this->annotationUtility->getCurrentBook();
      if (!$current_book) {
        return;
      }
      $this->start = $this->annotationUtility->getBookAnnotatedCharNumber($current_book);
      $annotated_text = $current_book->get('field_annotated_text')->value;
      $original_text = $current_book->get('body')->value;

      if (!$current_book->get('field_uids_for_css')->isEmpty()) {
        foreach ($current_book->get('field_uids_for_css') as $uids) {
          if (!in_array($uids->value, $this->uidsForCss)) {
            $this->uidsForCss[] = $uids->value;
          }
        }
      }

      $dom = new \DOMDocument();
      $dom->loadHTML('<?xml encoding="utf-8" ?>' . $original_text);
      // The html text is wrapped in <html> and <body> tags.
      $body = $dom->lastChild->firstChild;
      $this->processNode($body);
      $annotated_text .= $this->markup;
      $current_book->set('field_annotated_text', $annotated_text);
      $current_book->field_annotated_text->format = 'annotated_text';

      $popup = $current_book->get('field_annotation_popup')->value;
      $current_book->set('field_annotation_popup', $popup . $this->popup);
      $current_book->field_annotation_popup->format = 'annotated_text';

      $current_book->field_uids_for_css = $this->uidsForCss;
      if ($this->annotationUtility->getCurrentBookUnannotatedCharNumber() == 0) {
        $current_book->field_fully_annotated = TRUE;
        $this->init();
      }
      $current_book->save();
    }
    $run->field_annotation_number = $this->annotationUtility->getHighestAnnotationNumber() + 1;
    $run->save();
  }

  /**
   * Initialize properties.
   */
  protected function init() {
    $this->positionPointer = 0;
    $this->markup = '';
    $this->popup = '';
    $this->popupClasses = [];
    $this->uidsForCss = [];
  }

  /**
   * Process a DOM node to annotate the text.
   *
   * @param \DOMNode $node
   *   The node to process.
   */
  protected function processNode(\DOMNode $node) {
    foreach ($node->childNodes as $child) {
      // Avoid adding stray <br />s occuring later in the original text.
      if ($this->positionPointer >= $this->start && empty($this->slicedAnnotations) ) {
        return;
      }
      if ($child->nodeName !== '#text') {
        if ($this->positionPointer == $this->start && !empty($this->slicedAnnotations)) {
          $this->markup .= '<' . $child->nodeName . '>';
        }
        $this->processNode($child);
        if ($this->positionPointer == $this->start && empty($this->nodeText)) {
          $this->markup .= '</' . $child->nodeName . '>';
        }
      }
      else {
        $this->nodeText = $this->annotationUtility->removeTagsAndNewlines($child->nodeValue);
        $node_text_length = mb_strlen($this->nodeText);
        if ($this->positionPointer <= $this->start && $this->positionPointer + $node_text_length > $this->start) {
          // Move positionPointer to start.
          if ($this->positionPointer != $this->start) {
            $diff = $this->start - $this->positionPointer;
            $this->nodeText = mb_substr($this->nodeText, $diff);
            $this->positionPointer += $diff;
          }
          $this->createMarkupForText($this->slicedAnnotations);
        }
        else {
          $this->positionPointer += $node_text_length;
        }
      }
    }
  }

  /**
   * Create markup for the current node text.
   */
  protected function createMarkupForText() {
    while (!empty($this->nodeText) && !empty($this->slicedAnnotations)) {
      $node_text_length = mb_strlen($this->nodeText);
      $sliced_annotation = array_shift($this->slicedAnnotations);
      if ($node_text_length < $sliced_annotation['chars']) {
        $new_annotation_chars = $sliced_annotation['chars'] - $node_text_length;
        $new_annotation = $sliced_annotation;
        // We leave the length as is on purpose to keep things simple.
        $new_annotation['chars'] = $new_annotation_chars;
        array_unshift($this->slicedAnnotations, $new_annotation);
        $sliced_annotation['chars'] = $node_text_length;
      }
      $run_ids = [];
      $uids = [];
      $times = [];
      foreach ($sliced_annotation as $key => $annotation_line) {
        if ('chars' === $key) {
          continue;
        }
        $run_ids[] = $annotation_line['run_id'];
        $uids[] = $annotation_line['uid'];
        $times[] = $annotation_line['time'];
      }
      $run_ids = implode('-', $run_ids);
      $uids_with_dashes = implode('-', $uids);
      $times = implode('-', $times);
      $popup_class = 'popup-run-' . $run_ids . '-uid-' . $uids_with_dashes . '-time-' . $times;

      $annotated_text = [
        '#theme' => 'piliskor_story_annotated_text',
        '#text' => mb_substr($this->nodeText, 0, $sliced_annotation['chars']),
        '#annotation' => $sliced_annotation,
        '#attributes' => [
          'class' => [
            'story-text',
            'story-text-' . (count($sliced_annotation) - 1),
            'story-color-' . $uids_with_dashes,
            'colorize',
          ],
          'data-story-text-runner-count' => count($sliced_annotation) - 1,
          'data-story-text-runner-uids' => $uids_with_dashes,
          'data-popup-class' => $popup_class
        ],
      ];
      foreach ($uids as $uid) {
        $annotated_text['#attributes']['class'][] = 'story-uid-' . $uid;
      }
      $popup = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'popup-container',
            $popup_class,
          ],
        ],
      ];
      $this->nodeText = mb_substr($this->nodeText, $sliced_annotation['chars']);

      foreach ($sliced_annotation as $key => $annotation_line) {
        if ('chars' === $key) {
          continue;
        }
        foreach ($annotation_line as $key => $value) {
          $annotation_line['#' . $key] = $value;
          unset($annotation_line[$key]);
        }
        $annotation_line['#theme'] = 'piliskor_story_popup_line';
        $annotation_line['#type'] = 'container';
        $annotation_line['#attributes'] = [
          'class' => [
            'story-line',
            'story-color-' . $annotation_line['#uid'],
          ],
        ];
        $popup[] = $annotation_line;
      }
      $this->markup .= $this->renderer->renderPlain($annotated_text);
      if (!in_array($popup_class, $this->popupClasses)) {
        $this->popupClasses[] = $popup_class;
        $this->popup .= $this->renderer->renderPlain($popup);
      }
      if (!in_array($uids_with_dashes, $this->uidsForCss)) {
        $this->uidsForCss[] = $uids_with_dashes;
      }
      $this->positionPointer += $sliced_annotation['chars'];
      $this->start += $sliced_annotation['chars'];
    }
  }

}
