<?php

declare(strict_types=1);

namespace Drupal\ow_onecall\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure openweather_api settings for this site.
 */
final class OWOneCallApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openweather_api_open_weather_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ow_onecall.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /**
     * @var \Drupal\Core\Config\ImmutableConfig $config
     */
    $config = $this->config('ow_onecall.settings');
    $signUpLink = Url::fromUri('https://home.openweathermap.org/users/sign_up');
    $docsLink = Url::fromUri('https://openweathermap.org/api/one-call-3');
    $settingLink = Url::fromRoute('system.status');
    $intro = <<<INTRO
<p>Use this form to configure the
<a href="{$docsLink->toString()}" target="_blank">OpenWeather One Call API 3.0</a>
REST service. Before being able to use this service, you must
<a href="{$signUpLink->toString()}" target="_blank">sign up for an account</a>
and generate your api key.</p>
<p>Free accounts are limited to 1000 api calls per day. Open Weather does not have
a way to tell you how many API calls that you have made, but you can check the
<a href="{$settingLink->toString()}">status report page</a> for the api call count from this application.</p>
INTRO;

    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $intro
    ];

    $form['api_key_storage_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('API key storage'),
      '#description' => $this->t('Where is your api key stored?'),
      '#options' => [
        'database' => $this->t('Database (default)'),
        'environment' => $this->t('Environment variable'),
      ],
      '#default_value' => $config->get('api_key_storage_type') ?? 'database',
      '#ajax' => [
        'wrapper' => 'environment-data-wrapper',
        'callback' => '::environmentChangeCallback',
        'event' => 'change',
        'disable-refocus' => TRUE,
        'progress' => [
          'throbber' => FALSE,
        ],
      ]
    ];

    $form['environment_data'] = [
      '#type' => 'item',
      '#title' => $this->t('API Key Storage'),
      '#prefix' => '<div id="environment-data-wrapper">',
      '#suffix' => '</div>'
    ];

    $default_key_storage = $form_state->getValue('api_key_storage_type') ?? $config->get('api_key_storage_type');

    if ( $default_key_storage == 'environment' ) {
      $form['environment_data']['environment_storage_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Environment variable name'),
        '#description' => $this->t('What is the environment variable name where the One Call api key is stored? This is case sensitive.'),
        '#default_value' => $config->get('environment_storage_key') ?? '',
        '#required' => TRUE,
      ];
    } else {
      $form['environment_data']['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API key'),
        '#description' => $this->t(''),
        '#default_value' => $config->get('api_key') ?? '',
        '#required' => TRUE,
      ];
    }

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#description' => $this->t(''),
      '#default_value' => $config->get('debug') ?? 0
    ];

    return parent::buildForm($form, $form_state);
  }

  public function environmentChangeCallback(array $form, FormStateInterface &$form_state) {
    $form_state->setRebuild(TRUE);
    return $form['environment_data'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ow_onecall.settings')
      ->set('api_key_storage_type', $form_state->getValue('api_key_storage_type'))
      ->set('environment_storage_key', $form_state->getValue('environment_storage_key') ?? '')
      ->set('api_key', $form_state->getValue('api_key') ?? '')
      ->set('debug', (bool) $form_state->getValue('debug'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
