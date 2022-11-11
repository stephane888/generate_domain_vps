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

  function __construct() {
    $this->logger = \Drupal::logger('generate_style_theme');
  }

  function createDomainOnVPS(string $domain, string $subDomain) {
    $this->init($domain, $subDomain);
    $this->createVHost();
    $this->linkToVhostApache2();
    $this->activeNewHost();
    $this->addDomainToHosts();
  }

  function removeDomainOnVps($domain, $subDomain) {
    $this->init($domain, $subDomain);
    $this->deleteFileVhost();
  }

  /**
   *
   * @param string $domain
   * @param string $subDomain
   */
  protected function createVHost() {
    $conf = ConfigDrupal::config('generate_domain_vps.settings');
    if (!empty($conf['document_root'])) {
      $documentRoot = $conf['document_root'];
      $serverAdmin = $conf['server_admin'];
      $logs = $conf['logs'];
      $ssl_redirection = '';
      if (!empty($conf['active_ssl_redirection'])) {
        $ssl_redirection = '
  #redirect to https
  RewriteEngine On
  RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
';
      }

      $string = '<VirtualHost *:80>
      	ServerAdmin ' . $serverAdmin . '
      	ServerName ' . self::$currentDomain . '
      	DocumentRoot ' . $documentRoot . '
      	<Directory ' . $documentRoot . '>
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
      	ErrorLog ' . $logs . '/error.log
      	CustomLog ' . $logs . '/access.log combined
' . $ssl_redirection . '
</VirtualHost>';
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
          $this->logger->warning(' Error to reload apache2 (1/2) <br> ' . implode("<br>", $exc['output']));
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

  protected function addDomainToHosts() {
    if (self::$currentDomain && !$this->hasError) {
      $conf = ConfigDrupal::config('ovh_api_rest.settings');
      $ip = $conf['target'];
      $cmd = " sudo echo '" . $ip . "  " . self::$currentDomain . "' | sudo tee -a /etc/hosts ";
      $exc = $this->excuteCmd($cmd);
      if ($exc['return_var']) {
        $this->logger->critical(' Error to reload apache2 <br> ' . implode("<br>", $exc['output']));
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