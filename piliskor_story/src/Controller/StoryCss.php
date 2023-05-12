<?php

namespace Drupal\piliskor_story\Controller;

use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\piliskor_story\AnnotationUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StoryCss implements ContainerInjectionInterface{

  protected $annotationUtility;

  /**
   * Constructs a new StoryCss.
   *
   * @param \Drupal\piliskor_story\AnnotationUtility
   *   The annotation utility.
   */
  public function __construct(AnnotationUtility $annotation_utility) {
    $this->annotationUtility = $annotation_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('piliskor_story.annotation_utility')
    );
  }

  /**
   * Creates the dynamic css for a book page.
   *
   * @param NodeInterface $node
   *   The book node.
   */
  public function view(NodeInterface $node) {
    $content = '';
    if ($node->bundle() !== 'book') {
      return $content;
    }
    $single_uids = [];
    foreach ($node->get('field_uids_for_css') as $item) {
      $uids = [];
      foreach (explode('-', $item->value) as $uid) {
        if (!in_array($uid, $single_uids)) {
          $single_uids[] = $uid;
          $content .= '.colorize.story-color-' . $uid . ' {background-color: ' . $this->annotationUtility->integerToRandomColor($uid) . ';}';
        }
        $uids[] = $uid;
      }
      switch (count($uids)) {
        case '1':
          $content .= '.colorize.story-color-' . $uids[0] . ' {background-color: ' . $this->annotationUtility->integerToRandomColor($uids[0]) . ';}';
          break;
        case '2':
          $color1 = $this->annotationUtility->integerToRandomColor($uids[0]);
          $color2 = $this->annotationUtility->integerToRandomColor($uids[1]);
          $content .= '.colorize.story-color-' . $uids[0] . '-' . $uids[1] . " {background-image: linear-gradient(0deg, $color1 0, $color1 50%, $color2 50%, $color2 100%);}";
          break;
        case '3':
          $color1 = $this->annotationUtility->integerToRandomColor($uids[0]);
          $color2 = $this->annotationUtility->integerToRandomColor($uids[1]);
          $color3 = $this->annotationUtility->integerToRandomColor($uids[2]);
          $content .= '.colorize.story-color-' . $uids[0] . '-' . $uids[1] . '-' . $uids[2] . " {background-image: linear-gradient(0deg, $color1 0, $color1 33%, $color2 33%, $color2 66%, $color3 66%, $color3 100%); background-size: 18px;}";
          break;
        case '4':
          $color1 = $this->annotationUtility->integerToRandomColor($uids[0]);
          $color2 = $this->annotationUtility->integerToRandomColor($uids[1]);
          $color3 = $this->annotationUtility->integerToRandomColor($uids[2]);
          $color4 = $this->annotationUtility->integerToRandomColor($uids[3]);
          $content .= '.colorize.story-color-' . $uids[0] . '-' . $uids[1] . '-' . $uids[2] . '-' . $uids[3] . " {background-image: linear-gradient(0deg, $color1 0, $color1 25%, $color2 25%, $color2 50%, $color3 50%, $color3 75%, $color4 75%, $color4 100%);}";
          break;
      }
    }

    $response = new Response($content);
    $response->headers->set('content-type','text/css');
    return $response;
  }

}
