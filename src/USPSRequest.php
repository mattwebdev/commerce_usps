<?php

namespace Drupal\commerce_usps;

/**
 * USPS API Service.
 *
 * @package Drupal\commerce_usps
 */
class USPSRequest implements USPSRequestInterface {
  protected $configuration;

  /**
   * USPSRequest constructor.
   *
   * @param $configuration
   */
  public function __construct($configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Returns authentication array for a request.
   *
   * @return array
   */
  protected function getAuth() {
    return [
      'user_id' => $this->configuration['api_information']['user_id'],
      'password' => $this->configuration['api_information']['password'],
    ];
  }

}
