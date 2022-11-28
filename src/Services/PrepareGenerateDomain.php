<?php

namespace Drupal\generate_domain_vps\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\Repositories\ConfigDrupal;
use Jawira\CaseConverter\Convert;

/**
 * Gere la creation et la suppresion d'un vhost.
 *
 * @author stephane
 *        
 */
class PrepareGenerateDomain extends ControllerBase {
  
  /**
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  
  /**
   * On fixe la taille max dela partie texte du domaine.
   *
   * @var integer
   */
  protected $domainSize = 20;
  
  /**
   * --
   */
  function __construct() {
    $this->logger = \Drupal::logger('generate_domain_vps');
  }
  
  /**
   * Permet de creer les entités, domain et DomainsOVhEntity, cela permet de
   * preparer le terrain pour l'enregistrement du domaine (vhost).
   *
   * @param string $name
   * @throws \Exception
   */
  public function CreateEntities($name) {
    $sub_domain = $this->gnerateUniqueSubDomain($name);
    $conf = ConfigDrupal::config('ovh_api_rest.settings');
    if (empty($conf['zone_name'])) {
      $this->logger->warning(" Le module ovh n'est pas correctement configurer ");
      throw new \Exception(" Le module ovh n'est pas correctement configurer ");
    }
    
    $DomainOvh = \Drupal\ovh_api_rest\Entity\DomainOvhEntity::create();
    $DomainOvh->set('name', ' Generate domain : ' . $name);
    $DomainOvh->set('zone_name', $conf['zone_name']);
    $DomainOvh->set('field_type', $conf['field_type']);
    // $DomainOvh->set('sub_domain', $sub_domain);
    $DomainOvh->setSubDomain($sub_domain);
    $DomainOvh->set('target', $conf['target']);
    $DomainOvh->set('path', $conf['path']);
    $domaineHost = $DomainOvh->getsubDomain() . '.' . $DomainOvh->getZoneName();
    
    // On cree l'entité domain.
    $domain = \Drupal\vuejs_entity\VuejsEntity::createDomainFromData($domaineHost);
    if ($domain) {
      $DomainOvh->set('domain_id_drupal', $domain->id());
      $DomainOvh->save();
      return $DomainOvh;
    }
    else
      throw new \Exception(" Impossible de creer ou de recuperer le domain. ");
  }
  
  /**
   *
   * @param string $name
   * @return boolean
   */
  protected function validationDatas($name) {
    // Represente la valeur qui est utiliser pour creer le sous domaine.
    if (strlen($name) < 3)
      throw new \Exception(" La taille doit etre > à 3 ");
  }
  
  /**
   * Permet de generer un nom de sous domaine unique.
   *
   * @param string $name
   * @return String
   */
  public function gnerateUniqueSubDomain($name) {
    $this->validationDatas($name);
    $textConvert = new Convert($name);
    $sub_domain = preg_replace('/[^a-z0-9\-]/', "", $textConvert->toKebab());
    $sub_domain = substr($sub_domain, 0, $this->domainSize);
    // Verifie si le nom de domaine existe deja.
    $query = $this->entityTypeManager()->getStorage('domain_ovh_entity')->getQuery();
    $query->condition('sub_domain', $sub_domain . "%", 'LIKE');
    $entities = $query->execute();
    //
    if (!empty($entities)) {
      $query = $this->entityTypeManager()->getStorage('domain_ovh_entity')->getQuery();
      $query->sort('id', 'DESC');
      $query->range(0, 1);
      $ids = $query->execute();
      $id = reset($ids) + 1;
      $sub_domain .= $id;
    }
    return $sub_domain;
  }
  
}