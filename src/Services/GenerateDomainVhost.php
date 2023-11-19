<?php

namespace Drupal\generate_domain_vps\Services;

use Drupal\Core\Controller\ControllerBase;
use Stephane888\Debug\Repositories\ConfigDrupal;

/**
 * Gere la creation et la suppresion d'un vhost.
 *
 * @author stephane
 *        
 */
class GenerateDomainVhost extends ControllerBase {
  
  /**
   *
   * @var string
   */
  protected static $homeVps = '/home/wb-horizon';
  /**
   */
  protected static $currentDomain = null;
  
  /**
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  protected $hasError = false;
  /**
   *
   * @var array
   */
  protected $config = NULL;
  /**
   * Contient les chemins vers les ertificats (publi et privés).
   *
   * @var string
   */
  protected $sslFile = null;
  /**
   * Permet d'empecher de generer la partie SSL, cela peut etre necessaire si la
   * generation du SSL a echoue.
   *
   * @var boolean
   */
  protected $forceDisableVhsotSSL = false;
  
  /**
   * --
   */
  function __construct() {
    $this->logger = \Drupal::logger('generate_domain_vps');
  }
  
  /**
   * Permet de creer les enregistrement necessaire pour un vhost.
   * Adapter pour les sous domaine, pour les nouveaux domains il faut generer le
   * ssl, en amont et le passet dans le processus.
   * NB: Dans cette logique, tous les sous domaine utilise le domaine parent
   * mais necessite un ServerAlias avec wilcatd.
   * Example : ServerAlias *.example.com
   *
   * @param string $domain
   * @param string $subDomain
   */
  function createDomainOnVPS(string $domain, string $subDomain = null) {
    $this->init($domain, $subDomain);
    $this->addDomainToHosts();
  }
  
  /**
   * Permet de creer les enregistrement necessaire pour un vhost.
   * Adapter pour les sous domaine, pour les nouveaux domains il faut generer le
   * ssl, en amont et le passet dans le processus.
   * NB: dans cette logique un fichier par sous domaine.
   *
   * @param string $domain
   * @param string $subDomain
   */
  function createSubDomainOnVPS(string $domain, string $subDomain = null) {
    $this->init($domain, $subDomain);
    $this->createVHost();
    $this->linkToVhostApache2();
    $this->activeNewHost();
    $this->addDomainToHosts();
  }
  
  /**
   * Le certifical peut etre definie dans la configuration (pour les sous
   * domaine), mais il peut aussi etre definie à l'exterieur pour les nouveaux
   * domains.
   *
   * @return string
   */
  public function getSSLfiles() {
    $conf = $this->defaultConfig();
    if (!empty($conf['active_ssl_redirection']) && !$this->forceDisableVhsotSSL) {
      if ($this->sslFile)
        return $this->sslFile;
      else {
        return $conf['ssl_certificate_file'];
      }
    }
    return false;
  }
  
  /**
   * Le ssl peut etre fournir de l'exterieur.
   *
   * @param string $value
   */
  public function setSSLfiles($value) {
    $this->sslFile = $value;
  }
  
  /**
   * Permet de generer le domaine et ensuite generer le ne cessaire pour la
   * configuration du host.
   *
   * @param string $domain
   */
  public function generateSSLForDomainAndCreatedomainOnVps($domain) {
    $domain = str_replace("www.", "", $domain);
    $this->init($domain);
    $dd = " -d $domain ";
    $with_www = false;
    // on verifie s'il s'agit d'un sous domain.
    if (substr_count($domain, '.') >= 2)
      $this->addDomainToHosts();
    else {
      $this->addDomainToHosts(true);
      $dd .= " -d www.$domain ";
      $with_www = true;
    }
    
    if (!$this->hasError) {
      // On commence par cree le vhost afin de pouvoir effectuer le chalenge via
      // la methode HTTP-01,
      // On force la desactivation du SSL.
      $this->forceDisableVhsotSSL = true;
      $this->createVHost();
      $this->linkToVhostApache2();
      $this->activeNewHost();
      
      if ($this->PrepareGenerateSSL($domain, $dd)) {
        // On re-cree le vhost en y ajoutant le SSL.
        $this->createVHost($with_www);
        $this->linkToVhostApache2();
        $this->activeNewHost();
      }
    }
    return null;
  }
  
  /**
   * Elle permet de suivre le nombre de creation afin de ne pas deppaser les
   * limites.
   * return true si le SSL c'est generé et a été sauvegardé.
   *
   * @param string $domain
   * @param string $dd
   *
   */
  protected function PrepareGenerateSSL($domain, $dd) {
    $query = $this->entityTypeManager()->getStorage('domain_ssl')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('label', $domain);
    $ids = $query->execute();
    if (!empty($ids)) {
      $id = reset($ids);
      $DomainSsl = \Drupal\generate_domain_vps\Entity\DomainSsl::load($id);
      if ($DomainSsl->getStatusSsl())
        return TRUE;
      if (!$DomainSsl->getStatusSsl() && $DomainSsl->checkRateLimit()) {
        $status_generate_SSL = $this->GenerateSSL($domain, $dd);
        if ($status_generate_SSL) {
          $DomainSsl->setStatusSSL(TRUE);
        }
        if ($this->runProdSSL())
          $DomainSsl->save();
        return $status_generate_SSL;
      }
    }
    else {
      $status_generate_SSL = $this->GenerateSSL($domain, $dd);
      if ($this->runProdSSL()) {
        $values = [
          'label' => $domain,
          'status_ssl' => $status_generate_SSL
        ];
        $DomainSsl = \Drupal\generate_domain_vps\Entity\DomainSsl::create($values);
        $DomainSsl->save();
      }
      return $status_generate_SSL;
    }
    return false;
  }
  
  /**
   *
   * @param string $domain
   * @param string $dd
   */
  protected function GenerateSSL($domain, $dd) {
    $webroot = "/var/www/wb_horison_com_v2/public/web";
    $email = " --email kksasteph888@gmail.com ";
    $test_servser = " --server=https://acme-staging-v02.api.letsencrypt.org/directory ";
    
    // /////////////////////////////////
    // Test de generation du certificat.
    $cmd = "cd /home/wb-horizon && sudo lego --accept-tos  $test_servser  $email  --http --http.webroot $webroot  --http.port 80 $dd run";
    $exc = $this->excuteCmd($cmd);
    $this->forceDisableVhsotSSL = true;
    if ($exc['return_var']) {
      \Stephane888\Debug\debugLog::kintDebugDrupal($exc, 'error-sandbox-GenerateSSL', true);
      $this->messenger()->addWarning(" Le certificat SSL n'a pas pu etre generer (Test) ");
      $this->getLogger('generate_domain_vps')->error(" Le certificat SSL n'a pas pu etre generer (Test) ");
    }
    elseif ($this->runProdSSL()) {
      // /////////////////////////////////
      // Generation reelle du certificat.
      $cmd = "cd /home/wb-horizon && sudo lego --accept-tos  $email  --http --http.webroot $webroot  --http.port 80 $dd run";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        \Stephane888\Debug\debugLog::kintDebugDrupal($exc, 'error-prod-GenerateSSL', true);
        $this->messenger()->addWarning(" Le certificat SSL n'a pas pu etre generer ");
        $this->getLogger('generate_domain_vps')->error(" Le certificat SSL n'a pas pu etre generer");
        $this->forceDisableVhsotSSL = true;
      }
      else {
        $this->forceDisableVhsotSSL = false;
        $this->sslFile = "
SSLCertificateFile /home/wb-horizon/.lego/certificates/$domain.crt
SSLCertificateKeyFile /home/wb-horizon/.lego/certificates/$domain.key
";
        return true;
      }
    }
    return false;
  }
  
  private function runProdSSL() {
    $config = $this->defaultConfig();
    return isset($config['certicate_lego']['mode']) ? $config['certicate_lego']['mode'] : false;
  }
  
  /**
   * Permet de supprimer les fichiers de configuration du vhost.
   *
   * @param string $domain
   * @param string $subDomain
   */
  public function removeDomainOnVps($domain, $subDomain) {
    $this->init($domain, $subDomain);
    $this->deleteFileVhost();
  }
  
  /**
   * Permet de derterminer si une erreur s'est produite.
   */
  public function hasError() {
    return $this->hasError;
  }
  
  /**
   * Create file vhost
   *
   * @param string $domain
   * @param string $subDomain
   */
  protected function createVHost($add_WWW = false) {
    $conf = $this->defaultConfig();
    if (!empty($conf['document_root'])) {
      $documentRoot = $conf['document_root'];
      $serverAdmin = $conf['server_admin'];
      $logs = $conf['logs'];
      $ssl_redirection = '';
      $ssl_certificate_file = $this->getSSLfiles();
      if ($ssl_certificate_file) {
        $ssl_redirection = '
      #redirect to https
      RewriteEngine On
      RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
    ';
      }
      // define php_version
      $php_version = '';
      if (!empty($conf['php_version'])) {
        $php_version = '<FilesMatch ".+\.php$"> 
        ' . $conf['php_version'] . '
        </FilesMatch>';
      }
      $alias = '';
      if ($add_WWW)
        $alias = "ServerAlias www." . self::$currentDomain;
      $string = '<VirtualHost *:80>
      	ServerAdmin ' . $serverAdmin . '
      	ServerName ' . self::$currentDomain . '
        ' . $alias . '
      	DocumentRoot ' . $documentRoot . '
      	<Directory ' . $documentRoot . '>
      		Options Indexes FollowSymLinks
      		AllowOverride All
      		Order Deny,Allow
      		Allow from all
      	</Directory>
        ' . $php_version . '
      	ErrorLog ' . $logs . '/error.log
      	CustomLog ' . $logs . '/access.log combined
' . $ssl_redirection . '
</VirtualHost>
'; // cette ligne est necessaire car cela peut causser une erreur
      // d'eexecution au niveau de apache.( si </VirtualHost> et <VirtualHost
      // *:443> sont sur la meme ligne erreur d'execution ).
      
      if ($ssl_certificate_file) {
        $string .= '
<VirtualHost *:443>
        ServerAdmin ' . $serverAdmin . '
        ServerName ' . self::$currentDomain . '
        ' . $alias . '
        DocumentRoot ' . $documentRoot . '
        <Directory ' . $documentRoot . '>
                Options Indexes FollowSymLinks
                AllowOverride All
                Order Deny,Allow
                Allow from all
        </Directory>
        ' . $php_version . '
        ErrorLog ' . $logs . '/error.log
        CustomLog ' . $logs . '/access.log combined
#SSL conf.
' . $ssl_certificate_file . '
</VirtualHost>';
      }
      //
      $f_vhost = self::$homeVps . "/vhosts/" . self::$currentDomain . '.conf';
      if (file_exists($f_vhost)) {
        $this->deleteFileVhost();
      }
      $cmd = "echo '$string' > " . $f_vhost;
      $exc = $this->excuteCmd($cmd);
      // dump($exc);
      if ($exc['return_var']) {
        \Drupal::messenger()->addError(" Impossible de generer le fichier vhost ");
        $this->logger->warning(' Error to generate vhost <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
    else {
      $this->hasError = true;
    }
  }
  
  /**
   * Permet de supprimer un domaine.
   * 1 - on desactive le domaine
   */
  public function deleteFileVhost($domain = null) {
    if ($domain) {
      // on doit valider le domaine,
      self::$currentDomain = $domain;
    }
    //
    if (self::$currentDomain) {
      // remove and disabled vhost
      $this->disabledVHost();
      // remove file in /vhost/
      $cmd = " sudo rm  " . self::$homeVps . "/vhosts/" . self::$currentDomain . ".conf ";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        \Drupal::messenger()->addError(" Error to remove file Vhost ");
        $this->logger->warning(' Error to remove file Vhost <br> ' . $cmd . '<br>' . implode("<br>", $exc['output']));
      }
      //
      $this->removeDomainToHosts();
    }
  }
  
  /**
   * --
   */
  protected function linkToVhostApache2() {
    if (self::$currentDomain && !$this->hasError) {
      $f_vhost = '/etc/apache2/sites-available/' . self::$currentDomain . '.conf';
      if (file_exists($f_vhost)) {
        $this->disabledVHost();
      }
      $cmd = "sudo ln -s " . self::$homeVps . "/vhosts/" . self::$currentDomain . ".conf  /etc/apache2/sites-available/" . self::$currentDomain . '.conf';
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        \Drupal::messenger()->addError(" Can't link vhost in apache2 ");
        $this->logger->warning(' Cant link vhost in apache2 <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
  }
  
  /**
   *
   * @return boolean
   */
  protected function disabledVHost() {
    if (self::$currentDomain) {
      $f_vhost = '/etc/apache2/sites-available/' . self::$currentDomain . '.conf';
      if (file_exists($f_vhost)) {
        //
        $cmd = " sudo a2dissite " . self::$currentDomain . '.conf';
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->critical(' Error to disable vhost ' . self::$currentDomain . '.conf <br> ' . $cmd . '<br>' . implode("<br>", $exc['output']));
          return false;
        }
        else {
          $cmd = " sudo systemctl reload apache2 ";
          $exc = $this->excuteCmd($cmd);
          if ($exc['return_var']) {
            $this->logger->critical(' Error Apache not reload after disable : ' . self::$currentDomain . '.conf <br> ' . implode("<br>", $exc['output']));
          }
        }
        //
        $cmd = " sudo rm  " . $f_vhost;
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->warning(' Error to remove file Vhost in disabledVHost <br> ' . $cmd . '<br>' . implode("<br>", $exc['output']));
        }
      }
    }
  }
  
  /**
   * --
   */
  protected function activeNewHost() {
    if (self::$currentDomain && !$this->hasError) {
      $cmd = "sudo a2ensite " . self::$currentDomain . '.conf';
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        \Drupal::messenger()->addError(" Impossible d'activer le vhost ");
        $this->logger->warning(' Error to active vhost <br> ' . implode("<br>", $exc['output']));
        // try to disable.
        $cmd = "sudo a2dissite " . self::$currentDomain . '.conf';
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->critical(' Error to disable vhost <br> ' . $cmd . '<br>' . implode("<br>", $exc['output']));
        }
        $this->hasError = true;
      }
      else {
        $cmd = "sudo systemctl reload apache2";
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->warning(' Error to reload apache2 (1/2) => ' . self::$currentDomain . ' <br> ' . implode("<br>", $exc['output']));
          $this->hasError = true;
          // try to disable.
          $cmd = "sudo a2dissite " . self::$currentDomain . '.conf';
          $exc = $this->excuteCmd($cmd);
          if ($exc['return_var']) {
            $this->logger->critical(' Error to disable vhost (2) <br> ' . $cmd . '<br>' . implode("<br>", $exc['output']));
          }
          //
          $cmd = "sudo systemctl reload apache2";
          $exc2 = $this->excuteCmd($cmd);
          if ($exc2['return_var']) {
            $this->logger->critical(' Error to reload apache2 (2/2) <br> ' . implode("<br>", $exc['output']));
          }
        }
      }
    }
  }
  
  /**
   * Ajouter un nouveau domain dans le fichier /etc/hosts tout en evitant les
   * doublons.
   */
  protected function addDomainToHosts($add_WWW = false) {
    if (self::$currentDomain && !$this->hasError) {
      $conf = ConfigDrupal::config('ovh_api_rest.settings');
      $ip = $conf['target'];
      $hosts = file('/etc/hosts', FILE_SKIP_EMPTY_LINES);
      foreach ($hosts as $k => $line_host) {
        if (str_contains($line_host, self::$currentDomain)) {
          unset($hosts[$k]);
        }
      }
      
      if ($add_WWW) {
        $hosts[] = $ip . "\t" . self::$currentDomain . "\n";
        $hosts[] = $ip . "\t" . 'www.' . self::$currentDomain;
      }
      else
        $hosts[] = $ip . "\t" . self::$currentDomain;
      
      $hosts_file = implode("", $hosts);
      $cmd = " echo '$hosts_file' | sudo tee  /etc/hosts ";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        $this->logger->critical(' Error add domain to /etc/hosts <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
  }
  
  /**
   *
   * @deprecated deprecier, car elle ne permet pas de verifier si une valeur
   *             existe deja.
   */
  protected function addDomainToHostsOLd() {
    if (self::$currentDomain && !$this->hasError) {
      $conf = ConfigDrupal::config('ovh_api_rest.settings');
      $ip = $conf['target'];
      $cmd = " sudo echo '" . $ip . "  " . self::$currentDomain . "' | sudo tee -a /etc/hosts ";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        $this->logger->critical(' Error add domain to /etc/hosts <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
  }
  
  /**
   * --
   */
  protected function removeDomainToHosts() {
    if (self::$currentDomain) {
      // $conf = ConfigDrupal::config('ovh_api_rest.settings');
      $file = '/etc/hosts';
      if (file_exists($file)) {
        $out = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($out as $k => $value) {
          if (str_contains($value, " " . self::$currentDomain)) {
            unset($out[$k]);
          }
        }
        //
        $str = implode("\n", $out);
        $cmd = " echo '" . $str . "' | sudo tee /etc/hosts ";
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->critical(' Error to update /etc/hosts <br> ' . implode("<br>", $exc['output']));
          $this->hasError = true;
        }
      }
    }
  }
  
  /**
   * Permet de recuperer la configuration.
   *
   * @return array|NULL|number|mixed|\Drupal\Component\Render\MarkupInterface|string
   */
  private function defaultConfig() {
    if (!$this->config)
      $this->config = ConfigDrupal::config('generate_domain_vps.settings');
    return $this->config;
  }
  
  /**
   *
   * @throws \LogicException
   */
  private function init($domain, $subDomain = null) {
    if (!empty($subDomain)) {
      $domain = $subDomain . '.' . $domain;
    }
    self::$currentDomain = $domain;
    // Get current pwd.
    $cmd = 'pwd';
    $exc = $this->excuteCmd($cmd);
    if ($exc['return_var']) {
      $this->logger->warning(' Impossible de recuperer le dossier le courant <br>' . implode("<br>", $exc['output']));
      throw new \LogicException();
    }
    // Le current dir doit logiquement donnée sur un dossier ( web ou similaire
    // ), on recupere le dossier parent.
    if (!empty($exc['output'][0])) {
      $dir = $exc['output'][0];
      $dir = explode("/", $dir);
      array_pop($dir);
      self::$homeVps = implode("/", $dir);
    }
    
    // Create dir 'vhosts' if not exit:
    $cmd = 'mkdir -p ' . self::$homeVps . '/vhosts';
    $exc = $this->excuteCmd($cmd);
    if ($exc['return_var']) {
      $this->logger->warning(' Impossible de creer le dossier vhosts <br>' . implode("<br>", $exc['output']));
      throw new \LogicException(' Impossible de creer le dossier vhosts <br>');
    }
  }
  
  private function excuteCmd($cmd) {
    ob_start();
    $return_var = '';
    $output = '';
    exec($cmd . " 2>&1", $output, $return_var);
    $result = ob_get_contents();
    ob_end_clean();
    $debug = [
      'output' => $output,
      'return_var' => $return_var,
      'result' => $result,
      'script' => $cmd
    ];
    return $debug;
  }
  
}