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
 *  services = {
 *    "_0" = @translation("First-Class Mail (Large Envelope, Letter, Parcel, Postcards"),
 *    "_1" = @translation("Priority Mail ____"),
 *    "_2" = @translation("Priority Mail Express ____ Hold For Pickup"),
 *    "_3" = @translation("Priority Mail Express ____"),
 *    "_4" = @translation("Standard Post"),
 *    "_6" = @translation("Media Mail"),
 *    "_7" = @translation("Library Mail"),
 *    "_13"= @translation("Priority Mail Express ____ Flat Rate Envelope"),
 *    "_15" = @translation("First-Class Mail Large Postcards"),
 *    "_16" = @translation("Priority Mail ____ Flat Rate Envelope"),
 *    "_17" = @translation("Priority Mail ____ Medium Flat Rate Box"),
 *    "_22" = @translation("Priority Mail ____ Large Flat Rate Box"),
 *    "_23" = @translation("Priority Mail Express ____ Sunday/Holiday Delivery"),
 *    "_25" = @translation("Priority Mail Express ____ Sunday/Holiday Delivery Flat Rate Envelope"),
 *    "_27" = @translation("Priority Mail Express ____ Flat Rate Envelope Hold For Pickup"),
 *    "_28" = @translation("Priority Mail ____ Small Flat Rate Box"),
 *    "_29" = @translation("Priority Mail ____ Padded Flat Rate Envelope"),
 *    "_30" = @translation("Priority Mail Express ____ Legal Flat Rate Envelope"),
 *    "_31" = @translation("Priority Mail Express ____ Legal Flat Rate Envelope Hold For Pickup"),
 *    "_32" = @translation("Priority Mail Express ____ Sunday/Holiday Delivery Legal Flat Rate Envelope"),
 *    "_33" = @translation("Priority Mail ____ Hold For Pickup"),
 *    "_34" = @translation("Priority Mail ____ Large Flat Rate Box Hold For Pickup"),
 *    "_35" = @translation("Priority Mail ____ Medium Flat Rate Box Hold For Pickup"),
 *    "_36" = @translation("Priority Mail ____ Small Flat Rate Box Hold For Pickup"),
 *    "_37" = @translation("Priority Mail ____ Flat Rate Envelope Hold For Pickup"),
 *    "-38" = @translation("Priority Mail ____ Gift Card Flat Rate Envelope"),
 *    "_39" = @translation("Priority Mail ____ Gift Card Flat Rate Envelope Hold For Pickup"),
 *    "_40" = @translation("Priority Mail ____ Window Flat Rate Envelope"),
 *    "_41" = @translation("Priority Mail ____ Window Flat Rate Envelope Hold For Pickup"),
 *    "_42" = @translation("Priority Mail ____ Small Flat Rate Envelope"),
 *    "_43" = @translation("Priority Mail ____ Small Flat Rate Envelope Hold For Pickup"),
 *    "_44" = @translation("Priority Mail ____ Legal Flat Rate Envelope"),
 *    "_45" = @translation("Priority Mail ____ Legal Flat Rate Envelope Hold For Pickup"),
 *    "_46" = @translation("Priority Mail ____ Padded Flat Rate Envelope Hold For Pickup"),
 *    "_47" = @translation("Priority Mail ____ Regional Rate Box A"),
 *    "_48" = @translation("Priority Mail ____ Regional Rate Box A Hold For Pickup"),
 *    "_49" = @translation("Priority Mail ____ Regional Rate Box B"),
 *    "_50" = @translation("Priority Mail ____ Regional Rate Box B Hold For Pickup"),
 *    "_53" = @translation("First-Class/ Package Service Hold For Pickup"),
 *    "_55" = @translation("Priority Mail Express ____ Flat Rate Boxes"),
 *    "_56" = @translation("Priority Mail Express ____ Flat Rate Boxes Hold For Pickup"),
 *    "_57" = @translation("Priority Mail Express ____ Sunday/Holiday Delivery Flat Rate Boxes"),
 *    "_58" = @translation("Priority Mail ____ Regional Rate Box C"),
 *    "_59" = @translation("Priority Mail ____ Regional Rate Box C Hold For Pickup"),
 *    "_61" = @translation("First-Class/ Package Service"),
 *    "_62" = @translation("Priority Mail Express ____ Padded Flat Rate Envelope"),
 *    "_63" = @translation("Priority Mail Express ____ Padded Flat Rate Envelope Hold For Pickup"),
 *    "_64" = @translation("Priority Mail Express ____ Sunday/Holiday Delivery Padded Flat Rate Envelope"),
 *   },
 * )
 */
class CommerceUsps extends ShippingMethodBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager) {
    // Rewrite the service keys to be integers.
    $plugin_definition = $this->preparePluginDefinition($plugin_definition);

    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager);
  }

  /**
   * Prepares the service array keys to support integer values.
   *
   * This uses the ClassID to make services selectable.
   * See https://docs.rocketship.it/php/1-0/usps-class-ids.html for a full list.
   *
   * See https://www.drupal.org/node/2904467 for more information.
   * todo: Remove once core issue has been addressed.
   *
   * @param array $plugin_definition
   *   The plugin definition provided to the class.
   *
   * @return array
   *   The prepared plugin definition.
   */
  private function preparePluginDefinition(array $plugin_definition) {
    // Cache and unset the parsed plugin definitions for services.
    $services = $plugin_definition['services'];
    unset($plugin_definition['services']);

    // Loop over each service definition and redefine them with
    // integer keys that match the UPS API.
    foreach ($services as $key => $service) {
      // Remove the "_" from the service key.
      $key_trimmed = str_replace('_', '', $key);
      $plugin_definition['services'][$key_trimmed] = $service;
    }

    return $plugin_definition;
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

