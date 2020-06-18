<?php

namespace Drupal\commerce_securepayau\PluginForm\Checkout;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_securepayau\SecurePayPaypal;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Off-site form for PayPal Checkout.
 *
 * This is provided as a fallback when no "review" step is present in Checkout.
 */
class PaypalPaymentOffsiteForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  /**
   * SecurePay PayPal service.
   *
   * @var \Drupal\commerce_securepayau\SecurePayPaypal
   */
  protected $securepayPaypal;

  /**
   * The constructor.
   *
   * @param \Drupal\commerce_securepayau\SecurePayPaypal $securepayPaypal
   *   The SecurePay PayPal service.
   */
  public function __construct(SecurePayPaypal $securepayPaypal) {
    $this->securepayPaypal = $securepayPaypal;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_securepayau.securepay_paypal')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_securepayau\Plugin\Commerce\PaymentGateway\PaypalExpressCheckout $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $config = $payment_gateway_plugin->getConfiguration();

    $extra = [
      'redirectUrls' => [
        'cancelUrl' => $form['#cancel_url'],
        'successUrl' => $form['#return_url'],
      ],
      'merchantCode' => $config['merchant_id'],

    ];

    $paypal_response = $this->securepayPaypal->initiateTransaction($payment, $extra);

    return $this->buildRedirectForm($form, $form_state, $paypal_response['paymentUrl'], [], 'get');
  }

}
