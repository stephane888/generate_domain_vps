<?php

namespace Drupal\generate_domain_vps\Services;

use Drupal\Core\Controller\ControllerBase;

class GenerateDomainVhost extends ControllerBase {
  
  /**
   *
   * @var string
   */
  protected static $homeVps = '/home/wb-horizon';
  protected static $currentDomain = null;
  
  /**
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  protected $hasError = false;
  
  function __construct() {
    $this->logger = \Drupal::logger('generate_style_theme');
  }
  
  function createDomainOnVPS(string $domain, string $subDomain) {
    $this->init();
    $this->createVHost($domain, $subDomain);
    $this->linkToVhostApache2();
    $this->activeNewHost();
    $this->addDomainToHosts();
  }
  
  /**
   *
   * @param string $domain
   * @param string $subDomain
   */
  protected function createVHost($domain, $subDomain = null) {
    if (!empty($subDomain)) {
      $domain = $subDomain . '.' . $domain;
    }
    self::$currentDomain = $domain;
    $string = '<VirtualHost *:80>
      	ServerAdmin kksasteph888@gmail.com
      	ServerName ' . $domain . '
      	DocumentRoot /var/www/wb_horison_com/public/web
      	<Directory /var/www/wb_horison_com/public/web>
      		Options Indexes FollowSymLinks
      		AllowOverride All
      		Order Deny,Allow
      		Allow from all
      	</Directory>
          # decommenter si vous disposez de plusieurs version de PHP
      	<FilesMatch ".+\.ph(ar|p|tml)$">
      		# SetHandler "proxy:unix:/run/php/php7.0-fpm.sock|fcgi://php70.localhost"
      		# SetHandler "proxy:unix:/run/php/php7.4-fpm.sock|fcgi://php74.localhost"
      		SetHandler "proxy:unix:/run/php/php7.4-fpm.sock|fcgi://php74.localhost"
      	</FilesMatch>
      	ErrorLog /var/www/wb_horison_com/logs/error.log
      	CustomLog /var/www/wb_horison_com/logs/access.log combined
</VirtualHost>';
    //
    $cmd = "echo '$string' > " . self::$homeVps . "/vhosts/" . self::$currentDomain . '.conf';
    $exc = $this->excuteCmd($cmd);
    if ($exc['return_var']) {
      \Drupal::messenger()->addError(" Impossible de generer le fichier vhost ");
      $this->logger->warning(' Error to generate vhost <br> ' . implode("<br>", $exc['output']));
      $this->hasError = true;
    }
  }
  
  /**
   * --
   */
  protected function linkToVhostApache2() {
    if (self::$currentDomain && !$this->hasError) {
      $cmd = "sudo ln -s " . self::$homeVps . "/vhosts/" . self::$currentDomain . ".conf  /etc/apache2/sites-available/" . self::$currentDomain . '.conf';
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        \Drupal::messenger()->addError(" Can't link vhost in apache2 ");
        $this->logger->warning(' Cant link vhost in apache2 <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
  }
  
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
          $this->logger->critical(' Error to disable vhost <br> ' . implode("<br>", $exc['output']));
        }
        $this->hasError = true;
      }
      else {
        $cmd = "sudo systemctl reload apache2";
        $exc = $this->excuteCmd($cmd);
        if ($exc['return_var']) {
          $this->logger->warning(' Error to reload apache2 (1/2) <br> ' . implode("<br>", $exc['output']));
          $this->hasError = true;
          // try to disable.
          $cmd = "sudo a2dissite " . self::$currentDomain . '.conf';
          $exc = $this->excuteCmd($cmd);
          if ($exc['return_var']) {
            $this->logger->critical(' Error to disable vhost <br> ' . implode("<br>", $exc['output']));
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
  
  protected function addDomainToHosts() {
    if (self::$currentDomain && !$this->hasError) {
      $cmd = " sudo echo '152.228.134.19  " . self::$currentDomain . "' | sudo tee -a /etc/hosts ";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        $this->logger->critical(' Error to reload apache2 <br> ' . implode("<br>", $exc['output']));
        $this->hasError = true;
      }
    }
  }
  
  /**
   *
   * @throws \LogicException
   */
  private function init() {
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