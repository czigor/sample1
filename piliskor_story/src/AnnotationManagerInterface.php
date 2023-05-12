<?php

namespace Drupal\piliskor_story;

use Drupal\commerce_order\Entity\OrderItemInterface;

interface AnnotationManagerInterface {

  /**
   * Perform annotation for a run.
   *
   * @param OrderItemInterface $run
   *   The run order item.
   */
  public function annotateRun(OrderItemInterface $run);

}