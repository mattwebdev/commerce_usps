<?php

namespace Drupal\commerce_usps;

/**
 * usps API Service.
 *
 * @package Drupal\commerce_usps
 */
class USPSRequest implements USPSRequestInterface {
  protected $configuration;

  /**
   * uspsRequest constructor.
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

  /**
   * Determine if integration mode (test or live) should be used.
   *
   * @return boolean
   *   Integration mode (ie: test) is the default.
   */
  public function useIntegrationMode() {
    // If live mode is enabled, do not use integration mode.
    if (!empty($this->configuration['api_information']['mode'])
      && $this->configuration['api_information']['mode'] == 'live') {
      return FALSE;
    }

    // Use integration mode by default.
    return TRUE;
  }
}
