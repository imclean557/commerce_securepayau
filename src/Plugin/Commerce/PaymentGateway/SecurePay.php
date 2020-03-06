<?php

namespace Drupal\commerce_securepayau\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_securepayau\Controller\SecurePayXML;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the SecurePay payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "secure_pay",
 *   label = "SecurePay",
 *   display_label = "Secure Pay",
 *   forms = {
 *     "add-payment-method" =
 *   "Drupal\commerce_securepayau\PluginForm\SecurePay\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"secure_pay_cc"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard",
 *   "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class SecurePay extends OnsitePaymentGatewayBase implements SecurePayInterface {

  /**
   * The logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $logger;

  /**
   * Constructs a new PaymentGateway object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'merchant_id' => '',
      'password' => '',
      'currency' => 'AUD',
      'gateway_urls' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => t('API Merchant ID and password'),
    ];
    $form['credentials']['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant Id'),
      '#default_value' => $this->configuration['merchant_id'],
      '#required' => TRUE,
    ];
    $form['credentials']['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $this->configuration['password'],
      '#required' => TRUE,
    ];

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Mode/Settings'),
    ];
    $form['settings']['currency'] = [
      '#type' => 'radios',
      '#title' => 'Currency',
      '#requried' => TRUE,
      '#options' => [
        'AUD' => 'AUD',
      ],
      '#default_value' => $this->configuration['currency'],
    ];

    $form['settings']['gateway_urls'] = [
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#title' => t('Gateway URL'),
      '#description' => t("You shouldn't need to update these, they should just work. The are made available incase securepay decides to change their domain and you need to be able to switch this<br/><br/>Securepay have different gateway URLs for different message types. The last part of the URL indicates the type of message so you do not need to enter that here as that will be added by this module at the time of payment request.<br/><br/>Ie:<br/>For the standard payment gateway it is accessed at:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;https://www.securepay.com.au/xmlapi/payment<br/><br/>The 'payment' part is added by this module so you should just enter:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;https://www.securepay.com.au/xmlapi/"),
    ];

    $accounts = [
      'live' => [
        'label' => t('Live transactions in a live account'),
        'url' => 'https://www.securepay.com.au/xmlapi/',
      ],
      'test' => [
        'label' => t('Developer test account transactions'),
        'url' => 'https://test.securepay.com.au/xmlapi/',
      ],
    ];

    foreach ($accounts as $type => $account) {
      $form['settings']['gateway_urls'][$type] = [
        '#type' => 'textfield',
        '#title' => $account['label'],
        '#default_value' => $this->configuration['gateway_urls'][$type],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = &$form_state->getValue($form['#parents']);
      $this->configuration['merchant_id'] = $values['credentials']['merchant_id'];
      $this->configuration['password'] = $values['credentials']['password'];
      $this->configuration['currency'] = $values['settings']['currency'];
      $this->configuration['gateway_urls'] = $values['settings']['gateway_urls'];
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'type', 'number', 'expiration', 'security_code',
    ];

    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    /**@todo Make it more secure by calling creating remote_id */
    static::setPaymentDetails($payment_details);

    // Setting static payment info.
    $payment_method->setReusable(FALSE);
    $payment_method->save();
  }

  /**
   * Set Credit card details to session.
   *
   * @param array $payment_details
   *   The payment information.
   */
  private static function setPaymentDetails(array $payment_details) {
    $request = \Drupal::request();
    $session = $request->getSession();
    $session->set('payment_details', $payment_details);
  }

  /**
   * Get Credit Card details from Session.
   *
   * @return array
   *   The payment details.
   */
  private static function getPaymentDetails() {
    $request = \Drupal::request();
    $session = $request->getSession();
    return $session->get('payment_details');
  }

  /**
   * Unset variable with Credit Card Details.
   */
  private static function destroyPaymentDetails() {
    $request = \Drupal::request();
    $session = $request->getSession();
    $session->remove('payment_details');
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);

    // Perform the create payment request here, throw an exception if it fails.
    $securepay = new SecurePayXML($this->configuration, $payment, static::getPaymentDetails());
    $response = $securepay->sendXmlRequest();

    if (!$response) {
      throw new \Exception("We could not connect to SecurePay.");
    }

    if ($response->Status->statusCode != "000") {
      $this->logger->get('commerce_securepayau')->error(print_r((array) $response->Status, TRUE));
      throw new PaymentGatewayException();
    }

    if ($response->approved == "No") {
      throw new HardDeclineException('The payment was declined');
    }

    // Remember to take into account $capture when performing the request.
    $amount = $payment->getAmount();
    $next_state = $capture ? 'completed' : 'authorization';
    $remote_id = $response['txnID'];
    $payment->setState($next_state);
    $payment->setRemoteId($remote_id);
    static::destroyPaymentDetails();
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

}
