<?php

namespace Drupal\generate_domain_vps\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\generate_domain_vps\Services\GenerateDomainVhost;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for generate domain vps routes.
 */
class GenerateDomainVpsController extends ControllerBase {
  /**
   *
   * @var GenerateDomainVhost
   */
  protected $GenerateDomainVhost;
  
  /**
   *
   * @param GenerateDomainVhost $GenerateDomainVhost
   */
  function __construct(GenerateDomainVhost $GenerateDomainVhost) {
    $this->GenerateDomainVhost = $GenerateDomainVhost;
  }
  
  /**
   *
   * @param ContainerInterface $container
   * @return \Drupal\generate_domain_vps\Controller\GenerateDomainVpsController
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('generate_domain_vps.vhosts'));
  }
  
  /**
   * Pour les tests ou à titre d'example.
   */
  public function build() {
    $domain = 'generate--' . time() . '.kksa';
    $subDomain = '';
    $this->GenerateDomainVhost->createDomainOnVPS($domain, $subDomain);
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t(' Domaine is generate .... ' . $domain)
    ];
    return $build;
  }
  
}
