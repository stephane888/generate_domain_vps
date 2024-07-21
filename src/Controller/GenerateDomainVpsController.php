<?php

namespace Drupal\generate_domain_vps\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\generate_domain_vps\Services\GenerateDomainVhost;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\DrupalUtility\HttpResponse;
use Stephane888\Debug\ExceptionDebug;
use Stephane888\Debug\ExceptionExtractMessage;
use Drupal\Component\Serialization\Json;
use Drupal\generate_domain_vps\Services\PrepareGenerateDomain;

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
   * @var PrepareGenerateDomain
   */
  protected $PrepareGenerateDomain;
  
  /**
   *
   * @param GenerateDomainVhost $GenerateDomainVhost
   */
  function __construct(GenerateDomainVhost $GenerateDomainVhost, PrepareGenerateDomain $PrepareGenerateDomain) {
    $this->GenerateDomainVhost = $GenerateDomainVhost;
    $this->PrepareGenerateDomain = $PrepareGenerateDomain;
  }
  
  /**
   *
   * @param ContainerInterface $container
   * @return \Drupal\generate_domain_vps\Controller\GenerateDomainVpsController
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('generate_domain_vps.vhosts'), $container->get('generate_domain_vps.prepare'));
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
  
  /**
   * Permet de crrer les entitées.
   *
   * @param Request $Request
   * @param string $name
   * @throws \Exception
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function generateDomainVps(Request $Request, string $name) {
    try {
      $DomainOvh = $this->PrepareGenerateDomain->CreateEntities($name);
      return HttpResponse::response($DomainOvh->toArray());
    }
    catch (\Exception $e) {
      return HttpResponse::response(ExceptionExtractMessage::errorAll($e), 431, $e->getMessage());
    }
    catch (\Error $e) {
      return HttpResponse::response(ExceptionExtractMessage::errorAll($e), 431, $e->getMessage());
    }
  }
  
}
