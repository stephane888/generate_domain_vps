<?php

namespace Drupal\generate_domain_vps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure generate domain vps settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  
  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generate_domain_vps_settings';
  }
  
  /**
   *
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'generate_domain_vps.settings'
    ];
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('generate_domain_vps.settings');
    //
    $form['server_admin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('e-mail'),
      '#default_value' => $config->get('server_admin'),
      '#description' => 'Example: '
    ];
    
    $form['document_root'] = [
      '#type' => 'textfield',
      '#title' => $this->t('document_root'),
      '#default_value' => $config->get('document_root'),
      '#description' => 'Example: /var/www/wb_horison_com/public/web'
    ];
    
    $form['php_version'] = [
      '#type' => 'textfield',
      '#title' => 'Version de PHP',
      '#default_value' => $config->get('php_version'),
      '#description' => 'Example 1 php7.4 :<br> SetHandler "proxy:unix:/run/php/php7.4-fpm.sock|fcgi://localhost <br>
Example 2 php8.1 :<br> SetHandler "proxy:unix:/run/php/php8.1-fpm.sock|fcgi://localhost <br>
Example 3 php8.2 :<br> SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost <br>
laisser vide pour utiliser la version par defaut.'
    ];
    
    $form['logs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logs'),
      '#default_value' => $config->get('logs'),
      '#description' => 'Example: /var/www/wb_horison_com/logs'
    ];
    
    $form['active_ssl_redirection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('active_ssl_redirection'),
      '#default_value' => $config->get('active_ssl_redirection'),
      '#description' => 'Active la redirection https'
    ];
    
    $form['ssl_certificate_file'] = [
      '#type' => 'textarea',
      '#title' => $this->t('ssl_certificate_file'),
      '#default_value' => $config->get('ssl_certificate_file'),
      '#description' => 'Les uri des fichiers SSL. <br> Example : <br>
SSLCertificateFile /etc/letsencrypt/live/xxxx.com/fullchain.pem<br>
SSLCertificateKeyFile /etc/letsencrypt/live/xxxx.com/privkey.pem<br>
Include /etc/letsencrypt/options-ssl-apache.conf
'
    ];
    
    $form['certicate_lego'] = [
      '#type' => 'details',
      '#title' => t('Generation automatique du certificat avec LEGO'),
      '#open' => false
    ];
    
    $form['certicate_lego']['mode'] = [
      '#type' => 'radios',
      '#title' => t('Mode de generation'),
      '#options' => [
        'test' => 'Sandbox',
        'prod' => 'Production'
      ]
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // if ($form_state->getValue('example') != 'example') {
    // $form_state->setErrorByName('example', $this->t('The value is not
    // correct.'));
    // }
    // parent::validateForm($form, $form_state);
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('generate_domain_vps.settings');
    $config->set('server_admin', $form_state->getValue('server_admin'));
    $config->set('document_root', $form_state->getValue('document_root'));
    $config->set('logs', $form_state->getValue('logs'));
    $config->set('active_ssl_redirection', $form_state->getValue('active_ssl_redirection'));
    $config->set('php_version', $form_state->getValue('php_version'));
    $config->set('ssl_certificate_file', $form_state->getValue('ssl_certificate_file'));
    $config->save();
    parent::submitForm($form, $form_state);
  }
  
}
