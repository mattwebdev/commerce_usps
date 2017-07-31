<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use USPS\RatePackage;

require(drupal_get_path('module', 'commerce_usps') . '/vendor/autoload.php');

/**
 * @CommerceShippingMethod(
 *  id = "commerce_shipping_method",
 *  label = @Translation("USPS"),
 *  services = {
 *   "USPS FIRST CLASS",
 *   "USPS FIRST CLASS COMMERCIAL",
 *   "USPS FIRST CLASS HFP COMMERCIAL",
 *   "USPS PRIORITY",
 *   "USPS PRIORITY COMMERCIAL",
 *   "USPS PRIORITY HFP COMMERCIAL",
 *   "USPS EXPRESS",
 *   "USPS EXPRESS COMMERCIAL",
 *   "USPS EXPRESS SH",
 *   "USPS EXPRESS SH COMMERCIAL",
 *   "USPS EXPRESS HFP",
 *   "USPS EXPRESS HFP COMMERCIAL",
 *   "USPS PARCEL",
 *   "USPS MEDIA",
 *   "USPS LIBRARY",
 *   "USPS ALL",
 *   "USPS ONLINE"
 *   },
 * )
 */
class CommerceUSPS extends ShippingMethodBase {


  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The shipping services.
   *
   * @var \Drupal\commerce_shipping\ShippingService[]
   */
  protected $services = [];

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager);

    $this->packageTypeManager = $package_type_manager;
    foreach ($this->pluginDefinition['services'] as $id => $label) {
      $this->services[$id] = new ShippingService($id, (string) $label);
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'username' => '',
      'password' => '',
      'default_package_type' => 'basic_box',
      'services' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPackageType() {
    $package_type_id = $this->configuration['default_package_type'];
    return $this->packageTypeManager->createInstance($package_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getServices() {
    // Filter out shipping services disabled by the merchant.
    return array_intersect_key($this->services, array_flip($this->configuration['services']));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $package_types = $this->packageTypeManager->getDefinitionsByShippingMethod($this->pluginId);
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);
    $services = array_map(function ($service) {
      return $service->getLabel();
    }, $this->services);
    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t(''),
      '#default_value' => $this->configuration['username'],
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#description' => t(''),
      '#default_value' => $this->configuration['password'],
      '#required' => TRUE,
    ];

    $form['default_package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default package type'),
      '#options' => $package_types,
      '#default_value' => $this->configuration['default_package_type'],
      '#required' => TRUE,
      '#access' => count($package_types) > 1,
    ];
    $form['services'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipping services'),
      '#options' => $services,
      '#default_value' => $this->configuration['services'],
      '#required' => TRUE,
      '#access' => count($services) > 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      if (!empty($values['services'])) {
        $values['services'] = array_filter($values['services']);

        $this->configuration['default_package_type'] = $values['default_package_type'];
        $this->configuration['services'] = array_keys($values['services']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    // Plugins can override this method to store additional information
    // on the shipment when the rate is selected (for example, the rate ID).
    $shipment->setShippingService($rate->getService()->getId());
    $shipment->setAmount($rate->getAmount());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $uspsPackage = $this->BuildUSPSPackage($shipment);
    $rates = [];
    $ups = $this->getUSPSRate($shipment, $uspsPackage);
    $upsRate = $ups->getRate();

    if ($ups->isSuccess()) {
      $responseArray = $this->parseXML($upsRate);
      $cost = $responseArray['RateV4Response'][0]['Package'][0]['Postage'][0]["Rate"][0];
      $currency = "USD";
      $price = new Price((string) $cost, $currency);
      $ServiceCode = $this->getUSPSServices($uspsPackage);

      $shippingService = new ShippingService(
        $ServiceCode,
        $ServiceCode
      );

      $rates[] = new ShippingRate(
        $ServiceCode,
        $shippingService,
        $price
      );
    }
    else {
      dpm('Error: ' . $ups->getErrorMessage());
    }

    return $rates;

  }

  protected function BuildUSPSPackage(ShipmentInterface $shipment) {

    $store = $shipment->getOrder()->getStore();

    $package = new RatePackage();

    $service = $this->getUSPSServices($package);
    $package->setService($service);
    $package->setFirstClassMailType(RatePackage::MAIL_TYPE_PACKAGE);
    $package->setZipOrigination($store->getAddress()->getPostalCode());
    $package->setZipDestination($this->getShiptoAddress($shipment)
      ->getPostalCode());
    $package->setPounds(10);
    $package->setOunces(0);
    $package->setContainer('');
    $package->setSize(RatePackage::SIZE_REGULAR);
    $package->setField('Machinable', TRUE);

    return $package;
  }

  protected function getUSPSServices(RatePackage $package) {

    return $package::SERVICE_PARCEL;

  }

  protected function getShiptoAddress(ShipmentInterface $shipment) {
    $ShippingProfileAddress = $shipment->getShippingProfile()->get('address');
    return $ShippingProfileAddress->first();

  }

  protected function getUSPSRate(ShipmentInterface $shipment, RatePackage $uspsPackage) {
    if ($shipment->getShippingProfile()->address->isEmpty()) {
      return [];
    }
    else {
      $rate = new \USPS\Rate($this->configuration['username']);
      $rate->addPackage($uspsPackage);

      return $rate;
    }
  }


  protected function parseXML($string) {
    $parser = new \Drupal\commerce_usps\Controller\XMLParser();
    return $parser->parse($string);
  }
}
