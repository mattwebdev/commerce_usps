<?php
/**
 * Created by PhpStorm.
 * User: mattheinke
 * Date: 10/13/17
 * Time: 6:02 AM
 */

namespace Drupal\commerce_usps;


use USPS\TrackConfirm;

class USPSTrack extends USPSRequest {

  public function track() {
    // Initiate and set the username provided from usps
    $tracking = new TrackConfirm($this->configuration['api_information']['user_id']);
    // During test mode this seems not to always work as expected
    $tracking->setTestMode(false);
    // Add the test package id to the trackconfirm lookup class
    $tracking->addPackage('EJ958083578US');
    // Perform the call and print out the results
    print_r($tracking->getTracking());
    print_r($tracking->getArrayResponse());
    // Check if it was completed
    if ($tracking->isSuccess()) {
      echo 'Done';
    } else {
      echo 'Error: '.$tracking->getErrorMessage();
    }
  }

}