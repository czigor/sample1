<?php

namespace Drupal\piliskor_story;

use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\piliskor_run\TrackManagerInterface;
use Drupal\piliskor_run\RunManager;

class AnnotationUtility {

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
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $translation, TimeInterface $time, TrackManagerInterface $track_manager, RunManager $run_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $translation;
    $this->time = $time;
    $this->trackManager = $track_manager;
    $this->runManager = $run_manager;
  }

  /**
   * Gets the current book page to annotate.
   *
   * @return NodeInterface
   *   The current book page.
   */
  public function getCurrentBook() : NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $nid = $storage->getQuery()
      ->condition('type', 'book')
      ->condition('status', 1)
      ->condition('field_fully_annotated', FALSE)
      ->sort('nid', 'ASC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    return $storage->load(reset($nid));
  }

  /**
   * Get the total length of annotated runs.
   *
   * The last annotated run might have some unannotated meters at its end.
   * These unannotated meters are also included.
   *
   * @return int
   *   The total length in meters.
   */
  public function getAnnotatedRunsTotalLength() : int {
    $storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $run_ids = $storage->getQuery()
      ->condition('type', 'run')
      ->exists('field_annotation_number')
      ->condition('field_run_status', 'success')
      ->accessCheck(FALSE)
      ->execute();
    $length = 0;
    foreach ($run_ids as $run_id) {
      $run = $storage->load($run_id);
      $length += $this->runManager->getRunLength($run);
    }
    return $length;
  }

  /**
   * Gets the last annotated run.
   */
  public function getLastAnnotatedRun() {
    $storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $run_ids = $storage->getQuery()
      ->condition('type', 'run')
      ->exists('field_annotation_number')
      ->condition('field_run_status', 'success')
      ->sort('field_annotation_number', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    if (empty($run_ids)) {
      return;
    }
    return $storage->load(reset($run_ids));
  }

  /**
   * Gets the length that has not been annotated from the last annotated run.
   *
   * @return number
   *   The unannotated meters.
   */
  public function getLeftoverMeters() {
    $total_length = $this->getAnnotatedRunsTotalLength();
    return $total_length % AnnotationManager::METERS_PER_CHAR;
  }

  /**
   * Get the number of unannotated characters from the current book page.
   *
   * @return int
   *   The number of unannotated chartacters.
   */
  public function getCurrentBookUnannotatedCharNumber() : int {
    $book = $this->getCurrentBook();
    return $this->getBookUnannotatedCharNumber($book);
  }

  public function getBookAnnotatedCharNumber(NodeInterface $book) : int {
    $annotated_text = $book->get('field_annotated_text')->value;
    if (empty($annotated_text)) {
      return 0;
    }
    return mb_strlen($this->removeTagsAndNewlines($annotated_text));
  }

  /**
   * Get the total number of characters in a book drupal node.
   *
   * @param NodeInterface $book
   *   The book node.
   *
   * @return int
   *   The number of characters (without markup) in the book node body.
   */
  public function getBookTotalCharNumber(NodeInterface $book) : int {
    $original_text = $book->get('body')->value;
    return mb_strlen($this->removeTagsAndNewlines($original_text));
  }

  /**
   * Get the number of unannotated characters in a book drupal node.
   *
   * @param NodeInterface $book
   *   The book node.
   *
   * @return int
   *   The number of unannotated characters (without markup) in the book node.
   */
  public function getBookUnannotatedCharNumber(NodeInterface $book) : int {
    $original_text = $book->get('body')->value;
    $annotated_text = $book->get('field_annotated_text')->value;
    if (empty($annotated_text)) {
      return mb_strlen($this->removeTagsAndNewlines($original_text));
    }
    return mb_strlen($this->removeTagsAndNewlines($original_text)) - mb_strlen($this->removeTagsAndNewlines($annotated_text));
  }

  /**
   * Remove html tags and newlines from a string.
   *
   * @param string $string
   *   The string to purge.
   *
   * @return string
   *   The string without tags and newlines.
   */
  public function removeTagsAndNewlines(string $string) : string {
    $return = strip_tags($string);
    $return = str_replace(["\r", "\n"], '', $return);
    return $return;
  }

  /**
   * Convert an integer number to a random hexadecimal color code in the #xxx
   * format.
   *
   * @param int $i
   *   An integer to convert.
   *
   * @return string
   *   A hsl color used in CSS.
   */
  public function integerToRandomColor(int $i) {
    // @see https://en.wikipedia.org/wiki/Perfect_hash_function.
    // A hash between 0 and 4095.
    $hash = ((83682354363539 * $i) % 37634541363577) % 4095;
    return 'hsl(' . floor($hash * 0.0878) . ', 100%, 80%)';
  }

  /**
   * Returns the highest annotation number.
   *
   * @return int
   *   The highest annotation number.
   */
  public function getHighestAnnotationNumber() {
    $storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $run_ids = $storage->getQuery()
      ->condition('type', 'run')
      ->exists('field_annotation_number')
      ->condition('field_run_status', 'success')
      ->sort('field_annotation_number', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    if (empty ($run_ids)) {
      return 0;
    }
    $run = $storage->load(reset($run_ids));
    return $run->field_annotation_number->value;
  }

}
