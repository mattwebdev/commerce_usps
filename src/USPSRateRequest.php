<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use USPS\Rate;
use USPS\ServiceDeliveryCalculator;

/**
 * Class uspsRateRequest
 *
 * @package Drupal\commerce_usps
 */
class USPSRateRequest extends USPSRequest {

  /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface */
  protected $commerce_shipment;

  /** @var array */
  protected $configuration;

  /**
   * @var \USPS\Rate
   */
  protected $usps_request;

  /**
   * uspsRateRequest constructor.
   *
   * @param array $configuration
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   */
  public function __construct(array $configuration, ShipmentInterface $commerce_shipment) {
    parent::__construct($configuration);
    $this->commerce_shipment = $commerce_shipment;

    // Initialize the USPS request.
    $this->initRequest();
  }

  /**
   * @return array
   */
  public function getRates() {
    $rates = [];
    $limit_by_class_id = (isset($this->configuration['options']['select_services']['enabled']) && !empty($this->configuration['options']['select_services']['enabled']['class_id']));

    // Add each package the the request.
    foreach ($this->getPackages() as $package) {
      $this->usps_request->addPackage($package);
    }

    // Fetch the rates.
    $this->usps_request->getRate();
    $response = $this->usps_request->getArrayResponse();

    // Parse the rate response and create shipping rates array.
    if (!empty($response['RateV4Response']['Package']['Postage'])) {
      foreach ($response['RateV4Response']['Package']['Postage'] as $rate) {
        $price = $rate['Rate'];
        $service_code = $rate['@attributes']['CLASSID'];

        // Limit serivces by Class ID if this is configured.
        $class_id = '_' . $service_code;
        if ($limit_by_class_id && (!\in_array($class_id, $this->configuration['options']['select_services']['class_id'], TRUE) || empty($this->configuration['options']['select_services']['class_id'][$class_id]))) {
          continue;
        }

        // @Todo: Add Service ID Support.

        $service_name = $this->cleanServiceName($rate['MailService']);

        $shipping_service = new ShippingService(
          $service_code,
          $service_name
        );

        $rates[] = new ShippingRate(
          $service_code,
          $shipping_service,
          new Price($price, 'USD')
        );
      }
    }

    return $rates;
  }

  protected function initRequest() {
    $this->usps_request = new Rate(
      $this->configuration['api_information']['user_id']
    );
    $this->setMode();
  }

  protected function setMode() {
    $this->usps_request->setTestMode($this->configuration['api_information']['mode'] == 'test');
  }

  protected function getPackages() {
    // @todo: Support multiple packages.
    $package = new USPSPackage($this->commerce_shipment);
    return [$package->getPackage()];
  }

  protected function cleanServiceName($service) {
    // Remove the html encoded trademark markup since it's
    // not supported in radio labels.
    return str_replace('&lt;sup&gt;&#8482;&lt;/sup&gt;', '', $service);
  }

  public function checkDeliveryDate() {
    $to_address = $this->commerce_shipment->getShippingProfile()->get('address');
    $from_address = $this->commerce_shipment->getOrder()->getStore()->getAddress();
    // Initiate and set the username provided from usps
    $delivery = new ServiceDeliveryCalculator($this->configuration['api_information']['user_id']);
    // Add the zip code we want to lookup the city and state
    $delivery->addRoute(3,$from_address->getPostalCode(),$to_address->postal_code);
    // Perform the call and print out the results
    $delivery->getServiceDeliveryCalculation();
    return $delivery->getArrayResponse();
  }

  /**
   * @param $serviceCode
   *
   * @return string
   */
  public function translateServiceLables($serviceCode) {
    $label = '';
    if (strtolower($serviceCode) == 'parcel') {
      $label = 'ground';
    }

    return $label;
  }

  /**
   * @param $zip_code
   *
   * @return int
   */
  function validateUSAZip($zip_code) {
    return preg_match("/^([0-9]{5})(-[0-9]{4})?$/i", $zip_code);
  }

  /**
   * @return mixed
   */
  public function getMode() {
    return $this->configuration['api_information']['mode'];
  }

}
