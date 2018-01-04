<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_usps\USPSRateRequest;
use Drupal\Core\Form\FormStateInterface;


/**
 * @CommerceShippingMethod(
 *  id = "usps",
 *  label = @Translation("USPS"),
 * )
 */
class CommerceUsps extends ShippingMethodBase {

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $packageTypeManager
   *
   * @internal param \Drupal\commerce_shipping\PackageTypeManagerInterface
   *   $package_type_manager The package type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $packageTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $packageTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'api_information' => [
          'user_id' => '',
          'password' => '',
          'mode' => 'test',
          'rate_setting' => 0,
        ],
        'options' => [
          'log' => [],
        ],
        'conditions' => [
          'conditions' => [],
        ],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $this->isConfigured() ? $this->t('Update your usps API information.') : $this->t('Fill in your usps API information.'),
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];

    $form['api_information']['user_id'] = [
      '#type' => 'textfield',
      '#title' => t('User ID'),
      '#description' => t(''),
      '#default_value' => $this->configuration['api_information']['user_id'],
      '#required' => TRUE,
    ];

    $form['api_information']['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#description' => t(''),
      '#default_value' => $this->configuration['api_information']['password'],
      '#required' => TRUE,
    ];

    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use the test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('usps Options'),
      '#description' => $this->t('Additional options for usps'),
    ];

    $form['options']['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['options']['log'],
    ];

    $form['conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('usps rate conditionings'),
      '#description' => $this->t('setting when USPS Rates are excluded'),
    ];

    $form['conditions']['conditions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude USPS Rates'),
      '#options' => [
        'domestic' => $this->t('Domestic Shipment to Lower 48 States'),
        'domestic_plus' => $this->t('Domestic Shipment to Alaska & Hawaii'),
        'domestic_mil' => $this->t('Miliary State Codes: AP, AA, AE'),
        'international_ca' => $this->t('International Shipment to Canada'),
        'international_eu' => $this->t('International Shipment to Europe'),
        'international_as' => $this->t('International Shipment to Asia'),
      ],
      '#default_value' => $this->configuration['conditions']['conditions'],
    ];

    return $form;
  }

  /**
   * Determine if we have the minimum information to connect to usps.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {

    $api_information = $this->configuration['api_information'];

    return (
      !empty($api_information['user_id'])
      &&
      !empty($api_information['password'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

    parent::validateConfigurationForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['user_id'] = $values['api_information']['user_id'];
      $this->configuration['api_information']['password'] = $values['api_information']['password'];
      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];

      $this->configuration['options']['log'] = $values['options']['log'];

      $this->configuration['conditions']['conditions'] = $values['conditions']['conditions'];

      //this is in ShippingMethodBase but it's not run because we are not using 'services'
      $this->configuration['default_package_type'] = $values['default_package_type'];

    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Calculates rates for the given shipment.
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $rate = [];


    if (!$shipment->getShippingProfile()->get('address')->isEmpty()) {
      $rate_request = new USPSRateRequest($this->configuration, $shipment);
      $rate = $rate_request->getRates();
    }

    return $rate;

  }
}

