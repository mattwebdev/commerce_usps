<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_usps\USPSRateRequest;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CommerceUsps.
 *
 * @CommerceShippingMethod(
 *  id = "usps",
 *  label = @Translation("USPS"),
 * )
 */
class CommerceUsps extends ShippingMethodBase {

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
        'select_services' => [
          'enabled' => [],
          'class_id' => [],
        ]
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

    $form['options']['select_services'] = [
      '#type' => 'details',
      '#title' => $this->t('Configure Services'),
      '#description' => $this->t('If not configured/enabled, all shipping services will be shown.'),
    ];

    $form['options']['select_services']['enabled'] = [
      '#type' => 'checkboxes',
      '#options' => [
        'class_id' => $this->t('Class ID'),
        'service_id' => $this->t('Service ID'),
      ],
      '#default_value' => $this->configuration['options']['select_services']['enabled'],
    ];

    $class_id_services = [
      '_0' => 'First-Class Mail (Large Envelope, Letter, Parcel, Postcards',
      '_1' => 'Priority Mail ____',
      '_2' => 'Priority Mail Express ____ Hold For Pickup',
      '_3' => 'Priority Mail Express ____',
      '_4' => 'Standard Post',
      '_6' => 'Media Mail',
      '_7' => 'Library Mail',
      '_13' => 'Priority Mail Express ____ Flat Rate Envelope',
      '_15' => 'First-Class Mail Large Postcards',
      '_16' => 'Priority Mail ____ Flat Rate Envelope',
      '_17' => 'Priority Mail ____ Medium Flat Rate Box',
      '_22' => 'Priority Mail ____ Large Flat Rate Box',
      '_23' => 'Priority Mail Express ____ Sunday/Holiday Delivery',
      '_25' => 'Priority Mail Express ____ Sunday/Holiday Delivery Flat Rate Envelope',
      '_27' => 'Priority Mail Express ____ Flat Rate Envelope Hold For Pickup',
      '_28' => 'Priority Mail ____ Small Flat Rate Box',
      '_29' => 'Priority Mail ____ Padded Flat Rate Envelope',
      '_30' => 'Priority Mail Express ____ Legal Flat Rate Envelope',
      '_31' => 'Priority Mail Express ____ Legal Flat Rate Envelope Hold For Pickup',
      '_32' => 'Priority Mail Express ____ Sunday/Holiday Delivery Legal Flat Rate Envelope',
      '_33' => 'Priority Mail ____ Hold For Pickup',
      '_34' => 'Priority Mail ____ Large Flat Rate Box Hold For Pickup',
      '_35' => 'Priority Mail ____ Medium Flat Rate Box Hold For Pickup',
      '_36' => 'Priority Mail ____ Small Flat Rate Box Hold For Pickup',
      '_37' => 'Priority Mail ____ Flat Rate Envelope Hold For Pickup',
      '-38' => 'Priority Mail ____ Gift Card Flat Rate Envelope',
      '_39' => 'Priority Mail ____ Gift Card Flat Rate Envelope Hold For Pickup',
      '_40' => 'Priority Mail ____ Window Flat Rate Envelope',
      '_41' => 'Priority Mail ____ Window Flat Rate Envelope Hold For Pickup',
      '_42' => 'Priority Mail ____ Small Flat Rate Envelope',
      '_43' => 'Priority Mail ____ Small Flat Rate Envelope Hold For Pickup',
      '_44' => 'Priority Mail ____ Legal Flat Rate Envelope',
      '_45' => 'Priority Mail ____ Legal Flat Rate Envelope Hold For Pickup',
      '_46' => 'Priority Mail ____ Padded Flat Rate Envelope Hold For Pickup',
      '_47' => 'Priority Mail ____ Regional Rate Box A',
      '_48' => 'Priority Mail ____ Regional Rate Box A Hold For Pickup',
      '_49' => 'Priority Mail ____ Regional Rate Box B',
      '_50' => 'Priority Mail ____ Regional Rate Box B Hold For Pickup',
      '_53' => 'First-Class/ Package Service Hold For Pickup',
      '_55' => 'Priority Mail Express ____ Flat Rate Boxes',
      '_56' => 'Priority Mail Express ____ Flat Rate Boxes Hold For Pickup',
      '_57' => 'Priority Mail Express ____ Sunday/Holiday Delivery Flat Rate Boxes',
      '_58' => 'Priority Mail ____ Regional Rate Box C',
      '_59' => 'Priority Mail ____ Regional Rate Box C Hold For Pickup',
      '_61' => 'First-Class/ Package Service',
      '_62' => 'Priority Mail Express ____ Padded Flat Rate Envelope',
      '_63' => 'Priority Mail Express ____ Padded Flat Rate Envelope Hold For Pickup',
      '_64' => 'Priority Mail Express ____ Sunday/Holiday Delivery Padded Flat Rate Envelope',
    ];

    $form['options']['select_services']['class_id'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipping Services by Class ID'),
      '#options' => $class_id_services,
      '#default_value' => $this->configuration['options']['select_services']['class_id'],
      '#states' => [
        'visible' => [
          ':input[name="plugin[0][target_plugin_configuration][usps][options][select_services][enabled][class_id]"]' => [
            'checked' => TRUE,
          ],
        ]
      ]
    ];

    // @Todo: Add Service ID Support.

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

      $this->configuration['options']['select_services']['enabled'] = $values['options']['select_services']['enabled'];
      $this->configuration['options']['select_services']['class_id'] = $values['options']['select_services']['class_id'];
      $this->configuration['options']['log'] = $values['options']['log'];

      $this->configuration['conditions']['conditions'] = $values['conditions']['conditions'];

      // This is in ShippingMethodBase but it's not run because we are not using 'services'.
      $this->configuration['default_package_type'] = $values['default_package_type'];

    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Calculates rates for the given shipment.
   *
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
