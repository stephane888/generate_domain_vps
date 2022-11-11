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
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
