<?php

namespace Drupal\commerce_securepayau\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_securepayau\SecurePayPaypal;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\oauth2_client\Service\Oauth2ClientServiceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Paypal Express Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "securepayau_paypal_express_checkout",
 *   label = @Translation("SecurePay PayPal - Express Checkout"),
 *   display_label = @Translation("PayPal"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_securepayau\PluginForm\Checkout\PaypalPaymentOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 * )
 */
class PaypalExpressCheckout extends OffsitePaymentGatewayBase {

  // Shipping address collection options.
  const SHIPPING_ASK_ALWAYS = 'shipping_ask_always';
  const SHIPPING_ASK_NOT_PRESENT = 'shipping_ask_not_present';
  const SHIPPING_SKIP = 'shipping_skip';

  /**
   * SecurePay PayPal service.
   *
   * @var \Drupal\commerce_securepayau\SecurePayPaypal
   */
  protected $securepayPaypal;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The OAuth2 client service.
   *
   * @var \Drupal\oauth2_client\Service\Oauth2ClientServiceInterface
   */
  protected $oauth2Client;

  /**
   * Constructs a new PaymentGatewayBase object.
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel factory.
   * @param \Drupal\oauth2_client\Service\Oauth2ClientServiceInterface $oauth2_client
   *   The Oauth2 client.
   * @param \Drupal\commerce_securepayau\SecurePayPaypal $securepay_paypal
   *   The SecurePay PayPal service.
   */
  public function __construct(
    array $configuration,
      $plugin_id,
      $plugin_definition,
      EntityTypeManagerInterface $entity_type_manager,
      PaymentTypeManager $payment_type_manager,
      PaymentMethodTypeManager $payment_method_type_manager,
      TimeInterface $time,
      LoggerChannelFactoryInterface $logger_channel_factory,
      Oauth2ClientServiceInterface $oauth2_client,
      SecurePayPaypal $securepay_paypal
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->logger = $logger_channel_factory->get('commerce_securepayau');
    $this->oauth2Client = $oauth2_client;
    $this->securepayPaypal = $securepay_paypal;
  }

  /**
   * {@inheritdoc}
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
      $container->get('logger.factory'),
      $container->get('oauth2_client.service'),
      $container->get('commerce_securepayau.securepay_paypal')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'merchant_id' => '',
      'client_id' => '',
      'client_secret' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant Id'),
      '#default_value' => $this->configuration['merchant_id'],
      '#required' => TRUE,
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $this->configuration['client_id'],
      '#required' => TRUE,
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $this->configuration['client_secret'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['merchant_id'] = $values['merchant_id'];
      $this->configuration['client_id'] = $values['client_id'];
      $this->configuration['client_secret'] = $values['client_secret'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $payerId = $request->query->get('PayerID');
    $orderId = $order->uuid();
    $amount = $order->getTotalPrice()->getNumber() * 100;

    $response = $this->securepayPaypal->executeTransation($orderId, $payerId, $amount);

    if ($response['status'] === 'paid') {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $payment = $payment_storage->create([
        'state' => 'completed',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $this->entityId,
        'order_id' => $order->id(),
        'remote_id' => $response['providerReferenceNumber'],
      ]);
      $payment->save();
      $this->logger->info('SecurePay PayPal transation completed. Payment ID: ' . $request->query->get('paymentId'));
    }
    else {
      $this->logger->info('SecurePay PayPal transaction could not be completed. Status: ' . $response['status']);
    }

  }

}
