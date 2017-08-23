<?php

namespace Drupal\commerce_usps;

use DateTime;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use USPS\Address;
use USPS\RatePackage;

class USPSShipment extends USPSEntity {
  protected $shipment;

  public function __construct(ShipmentInterface $shipment) {
    parent::__construct();
    $this->shipment = $shipment;
  }

  /**
   * @return \USPS\RatePackage
   */
  public function getShipment() {
    $api_shipment = new RatePackage();
    $this->setService($api_shipment);
    $this->setMailType($api_shipment);
    $this->setShipFrom($api_shipment);
    $this->setShipTo($api_shipment);
    $this->setWeight($api_shipment);
    $this->setContainer($api_shipment);
    $this->setPackageSize($api_shipment);
    $this->setExtraOptions($api_shipment);
    return $api_shipment;
  }

  /**
   * @param \USPS\RatePackage $api_shipment
   * @return void
   */
  public function setShipTo(RatePackage $api_shipment) {

    $address = $this->shipment->getShippingProfile()->address;
    $to_address = new Address();
    $to_address->setAddress($address->address_line1);
    $to_address->setApt($address->address_line2);
    $to_address->setCity($address->locality);
    $to_address->setState($address->administrative_area);
    $to_address->setZip5($address->postal_code);
    $api_shipment->setZipDestination(93405);
  }

  /**
   * @param \USPS\RatePackage $api_shipment
   * @return void
   */
  public function setShipFrom(RatePackage $api_shipment) {

    $address = $this->shipment->getOrder()->getStore()->getAddress();

    $from_address = new Address();
    $from_address->setAddress($address->getAddressLine1());
    $from_address->setCity($address->getLocality());
    $from_address->setState($address->getAdministrativeArea());
    $from_address->setZip5($address->getPostalCode());
    $from_address->setZip4($address->getPostalCode());
    $from_address->setFirmName($address->getName());

    $api_shipment->setZipOrigination($address->getPostalCode());
  }


  /**
   * @param \USPS\RatePackage $api_shipment
   * @return void
   */
  public function setPackageSize(RatePackage $api_shipment) {
    $api_shipment->setSize(RatePackage::SIZE_REGULAR);
  }

  /**
   * @param \USPS\RatePackage $api_shipment
   * @return void
   */
  public function setWeight(RatePackage $api_shipment) {
    $api_shipment->setPounds(intval($this->shipment->getPackageType()->getWeight()->getNumber()));
    $api_shipment->setOunces("0");
  }

  /**
   * @param \USPS\RatePackage $package
   * @return void
   */
  public function setService(RatePackage $package) {
    $package->setService(RatePackage::SERVICE_PARCEL);
  }

  /**
   * @param \USPS\RatePackage $package
   * @return void
   */
  public function setMailType(RatePackage $package) {
    $package->setFirstClassMailType(RatePackage::MAIL_TYPE_PACKAGE);
  }

  /**
   * @param \USPS\RatePackage $package
   *
   * @return void
   */
  public function setContainer(RatePackage $package) {
     $package->setContainer('');
  }

  /**
   * @param \USPS\RatePackage $package
   * @return void
   */
  public function setExtraOptions(RatePackage $package) {
    $package->setField('Machinable', true);
    $package->setField('ShipDate', $this->getProductionDate());
  }

  public function getProductionDate() {
    $date = date('Y-m-d',strtotime("+10 days"));
    return $date;
  }

}
