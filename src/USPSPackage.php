<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use USPS\Address;
use USPS\RatePackage;

class USPSPackage {
  protected $shipment;

  protected $usps_package;

  public function __construct(ShipmentInterface $shipment) {
    $this->shipment = $shipment;
    $this->usps_package = new RatePackage();
  }

  /**
   * @return \USPS\RatePackage
   */
  public function getPackage() {
    $this->setService();
    $this->setMailType();
    $this->setShipFrom();
    $this->setShipTo();
    $this->setWeight();
    $this->setContainer();
    $this->setPackageSize();
    $this->setExtraOptions();
    return $this->usps_package;
  }

  public function setShipTo() {
    $address = $this->shipment->getShippingProfile()->address;
    $to_address = new Address();
    $to_address->setAddress($address->address_line1);
    $to_address->setApt($address->address_line2);
    $to_address->setCity($address->locality);
    $to_address->setState($address->administrative_area);
    $to_address->setZip5($address->postal_code);
    $this->usps_package->setZipDestination($address->postal_code);
  }

  public function setShipFrom() {
    $address = $this->shipment->getOrder()->getStore()->getAddress();
    $from_address = new Address();
    $from_address->setAddress($address->getAddressLine1());
    $from_address->setCity($address->getLocality());
    $from_address->setState($address->getAdministrativeArea());
    $from_address->setZip5($address->getPostalCode());
    $from_address->setZip4($address->getPostalCode());
    $from_address->setFirmName($address->getName());
    $this->usps_package->setZipOrigination($address->getPostalCode());
  }

  public function setPackageSize() {
    $this->usps_package->setSize(RatePackage::SIZE_REGULAR);
  }

  public function setWeight() {
    $weight = $this->shipment->getWeight();
    if ($weight->getNumber() > 0) {
      $ounces = $weight->convert('oz')->getNumber();
      $this->usps_package->setPounds(floor($ounces / 16));
      $this->usps_package->setOunces($ounces % 16);
    }
  }

  public function setService() {
    $this->usps_package->setService(RatePackage::SERVICE_ALL);
  }

  public function setMailType() {
//    $this->usps_package->setFirstClassMailType(RatePackage::MAIL_TYPE_PACKAGE);
  }

  public function setContainer() {
    $remote_id = $this->shipment->getPackageType()->getRemoteId();
    $container = !empty($remote_id) && $remote_id != 'custom' ? $remote_id : RatePackage::CONTAINER_RECTANGULAR;
//    $this->usps_package->setContainer(strtoupper($container));
    $this->usps_package->setContainer(RatePackage::CONTAINER_VARIABLE);
  }

  public function setExtraOptions() {
    $this->usps_package->setField('Machinable', true);
    $this->usps_package->setField('ShipDate', $this->getProductionDate());
  }

  public function getProductionDate() {
    $date = date('Y-m-d', strtotime("now"));
    return $date;
  }

}
