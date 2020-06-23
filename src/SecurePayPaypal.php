<?php

namespace Drupal\commerce_securepayau;

use Drupal\oauth2_client\Service\Oauth2ClientServiceInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * SecurePay's Paypal integration.
 */
class SecurePayPaypal {
  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * SecurePay configuration.
   *
   * @var array
   */
  private $configuration;

  /**
   * Variable for payme.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  private $payment;

  /**
   * HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The OAuth2 client service.
   *
   * @var \Drupal\oauth2_client\Service\Oauth2ClientServiceInterface
   */
  private $oauth2Client;

  /**
   * The constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTP Client.
   * @param Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, RequestStack $request_stack, ConfigFactoryInterface $config) {
    $this->httpClient = $http_client;
    $this->requestStack = $request_stack;
    $this->config = $config;
    $this->configuration = $config->get('commerce_payment.commerce_payment_gateway.securepay_paypal')->get()['configuration'];
  }

  /**
   * Sends a JSON request to securepay.com.au.
   *
   * @return array
   *   Decoded JSON response from SecurePay.
   */
  public function sendRequest($post_url, $body) {
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $this->getAccessToken(),
    ];
    try {
      $response = $this->httpClient->request('POST', $post_url, [
        'headers' => $headers,
        'body' => $body,
      ]);

      return Json::decode($response->getBody()->getContents());
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      $body = $response->getBody();
      $message = Json::decode($body->getContents());
      print '<pre>';
      print_r($message);
      print '</pre>';
      return false;
      // @TODO display error properly.
    }

  }

  /**
   * Initiates the PayPal transaction.
   *
   * @var Drupal\commerce_payment\Entity\PaymentInterface $payment
   *
   * @var array $extra
   */
  public function initiateTransaction(PaymentInterface $payment, $extra) {
    $body = $extra += [
      'amount' => ($payment->getAmount()->getNumber() * 100),
      'merchantCode' => $this->configuration['merchant_id'],
      'ip' => $this->requestStack->getCurrentRequest()->getClientIp(),
      'orderId' => $payment->getOrder()->uuid(),
      'paymentType' => 'sale',
      'noShipping' => 'true',
    ];

    return $this->sendRequest($this->getUrl('initiate'), Json::encode($body));

  }

  /**
   * Executes PayPal transaction.
   *
   * @var integer $order_id
   *   The order ID.
   * @var string $payer_id
   *   The payer ID generated by PayPal.
   * @var integer $amount
   *   The total amount of the payment.
   */
  public function executeTransation($order_id, $payer_id, $amount) {
    $body = [
      'merchantCode' => $this->configuration['merchant_id'],
      'ip' => $this->requestStack->getCurrentRequest()->getClientIp(),
      'amount' => $amount,
      'payerId' => $payer_id,
    ];

    return $this->sendRequest($this->getUrl('orders/' . $order_id . '/execute'), Json::encode($body));
  }

  /**
   * Gets the access Token.
   *
   * @return string
   *   The access token.
   */
  public function getAccessToken() {
    $access_token = $this->oauth2Client->getAccessToken('securepay');

    if (!$access_token || $access_token->hasExpired()) {
      $this->oauth2Client->clearAccessToken('securepay');
      $access_token = $this->oauth2Client->getAccessToken('securepay');
    }

    return $access_token->getToken();
  }

  /**
   * Get base URL for requests.
   *
   * @return string
   *   The URL with optional path.
   */
  public function getUrl($path = '') {
    if ($this->configuration['mode'] === 'live') {
      return 'https://payments.auspost.net.au/v2/wallets/paypal/payments/' . $path;
    }
    else {
      return 'https://payments-stest.npe.auspost.zone/v2/wallets/paypal/payments/' . $path;
    }
  }

  /**
   * Adds the OAuth2 client service.
   *
   * @param Drupal\oauth2_client\Service\Oauth2ClientServiceInterface $oauth2_client
   *   The OAuth2 client service.
   */
  public function setOauth2Client(Oauth2ClientServiceInterface $oauth2_client) {
    $this->oauth2Client = $oauth2_client;
  }

}
