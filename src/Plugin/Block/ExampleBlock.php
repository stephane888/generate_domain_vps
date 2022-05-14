<?php

namespace Drupal\generate_domain_vps\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "generate_domain_vps_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("generate domain vps")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
