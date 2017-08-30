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
    $rates = [];

    $address = $this->commerce_shipment->getShippingProfile()->get('address');
    $request = $this->buildRequestObject($this->getShipment());
    $rate = $request->getRate();
    $xml_parser = new XMLParser();


    if (!empty($rate)) {
      $responseArray = $xml_parser->parse($rate);

      $delivery_response = $this->checkDeliveryDate();
      $delivery_date = New DrupalDateTime($delivery_response['SDCGetLocationsResponse']['NonExpedited'][0]['SchedDlvryDate']);

      $cost = $responseArray['RateV4Response'][0]['Package'][0]['Postage'][0]["Rate"][0];

      $currency = $this->commerce_shipment->getAmount()->getCurrencyCode();

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
