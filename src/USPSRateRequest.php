<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Datetime\DrupalDateTime;
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

  /** @var \Drupal\commerce_usps\USPSShipment */
  protected $usps_shipment;

  /**
   * uspsRateRequest constructor.
   *
   * @param array $configuration
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   */
  public function __construct(array $configuration, ShipmentInterface $commerce_shipment) {

    parent::__construct($configuration);

    $this->commerce_shipment = $commerce_shipment;

    $this->usps_shipment = $this->getUSPSShipment($commerce_shipment);
  }

  /**
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *
   * @return \Drupal\commerce_usps\USPSShipment
   */
  public function getUSPSShipment(ShipmentInterface $commerce_shipment) {

    return new USPSShipment($commerce_shipment);

  }

  /**
   * @return array
   */
  public function getRates() {
    $xml_parser = new xmlParser();

    $rates = [];

    $request = $this->buildRequestObject($this->getShipment());

    $available = 0;

    foreach ($this->configuration['conditions']['conditions'] as $key => $value) {
      if ($key == 'domestic' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 1) {
        $available = 1;
      }
      elseif ($key == 'domestic_plus' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 1) {
        $available = 1;
      }
      elseif ($key == 'domestic_mil' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 1) {
        $available = 1;
      }
      elseif ($key == 'international_ca' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 0) {
        $available = 1;
      }
      elseif ($key == 'international_eu' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 0) {
        $available = 1;
      }
      elseif ($key == 'international_as' && $value == 0 && $this->validateUSAZip(strval($this->getShipment()
          ->getPackageInfo()['ZipDestination'])) == 0) {
        $available = 1;
      }
    }


    if ($available == 0) {
      $rate = $request->getRate();
    }

    if (!empty($rate)) {
      $responseArray = $xml_parser->parse($rate);

      $delivery_response = $this->checkDeliveryDate();
      $delivery_date = New DrupalDateTime($delivery_response['SDCGetLocationsResponse']['NonExpedited'][0]['SchedDlvryDate']);

      $cost = $responseArray['RateV4Response'][0]['Package'][0]['Postage'][0]["Rate"][0];

      $currency = "USD";

      $price = new Price((string) $cost, $currency);

      $serviceCode = $this->getShipment()::SERVICE_PARCEL;

      $shippingService = new ShippingService(
        $serviceCode,
        "USPS" . " " . $this->translateServiceLables($serviceCode)
      );

      $rates[] = new ShippingRate(
        $serviceCode,
        $shippingService,
        $price,
        $delivery_date
      );


    }

    return $rates;
  }

  /**
   * @param $shipment
   *
   * @return \USPS\Rate
   */
  public function buildRequestObject($shipment) {
    $request = $this->getRequest();
    $request->addPackage($shipment);
    return $request;
  }

  /**
   * @return \USPS\Rate
   */
  public function getRequest() {

    return new Rate(
      $this->configuration['api_information']['user_id']
    );

  }

  /**
   * @return \USPS\RatePackage
   */
  public function getShipment() {

    return $this->usps_shipment->getShipment();

  }

  /**
   * @param $zip_code
   *
   * @return int
   */
  function validateUSAZip($zip_code) {
    return preg_match("/^([0-9]{5})(-[0-9]{4})?$/i", $zip_code);
  }

  public function checkDeliveryDate() {
    // Initiate and set the username provided from usps
    $delivery = new ServiceDeliveryCalculator($this->configuration['api_information']['user_id']);
    // Add the zip code we want to lookup the city and state
    $delivery->addRoute(3, '91730', '90025');
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
   * @return mixed
   */
  public function getMode() {

    return $this->configuration['api_information']['mode'];

  }

}
