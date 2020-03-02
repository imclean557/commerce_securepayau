<?php

namespace Drupal\commerce_securepayau\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\Config\Definition\Exception\Exception;
use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Class SecurePayXML.
 */
class SecurePayXML extends ControllerBase {
  /**
   * API Details from Payment method form.
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
   * Payment Details from Payment Form.
   *
   * @var array
   */
  private $paymentDetails;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment interface.
   * @param array $payment_details
   *   Payment details.
   */
  public function __construct(array $configuration, PaymentInterface $payment, array $payment_details) {
    $this->configuration = $configuration;
    $this->payment = $payment;
    $this->paymentDetails = $payment_details;
  }

  /**
   * Sends an XML API Request to securepay.com.au.
   *
   * @return SimpleXMLElement
   *   Response from Securepay.
   */
  public function sendXmlRequest() {
    /* @var mixed $xml */
    $xml = $this->createXmlRequestString();

    if ($this->configuration['mode'] == 'live') {
      $post_url = $this->configuration['gateway_urls']['live'];
    }
    else {
      $post_url = $this->configuration['gateway_urls']['test'];
    }

    $client = \Drupal::httpClient();

    $response = $client->request('POST', $post_url, [
      'headers' => [
        'Content-Type' => 'text/xml; charset=UTF8',
      ],
      'body' => $xml,
    ]);

    return simplexml_load_string((string) $response->getBody()->getContents());
  }

  /**
   * Creates an XML request string for SecurePay.
   *
   * Wraps XML API request child elements in the request element and includes
   * the merchant authentication information.
   */
  public function createXmlRequestString() {

    $payment_details = $this->paymentDetails;
    if (!$payment_details) {
      throw new Exception("Please enter payment details to continue.");
    }

    $message_id = $this->getUniqueMessageId(15, 25, 'abcdef0123456789');
    $timeout = 60;

    $timestamp = date('YmdHis000+600');
    $api_version = 'xml-4.2';
    $payment_request_type = "Payment";
    $xml_merchant_info = [
      'MessageInfo' => [
        'messageID' => $message_id,
        'messageTimestamp' => $timestamp,
        'timeoutValue' => $timeout,
        'apiVersion' => $api_version,
      ],
      'MerchantInfo' => [
        'merchantID' => $this->configuration['merchant_id'],
        'password' => $this->configuration['password'],
      ],
      'RequestType' => $payment_request_type,
    ];

    $order = $this->payment->getOrder();
    $price = round($order->getTotalPrice()->getNumber() * 100);
    $cc = $payment_details['number'];
    $exp = $payment_details['expiration']['month'] . '/' .
               substr($payment_details['expiration']['year'], -2);
    $ccv = $payment_details['security_code'];
    $xml_payment_info = [
      'Payment' => [
        'TxnList count="1"' => [
          'Txn ID="1"' => [
            'txnType' => '0',
            'txnSource' => '23',
            'amount' => $price,
            'currency' => $order->getTotalPrice()->getCurrencyCode(),
            'purchaseOrderNo' => $order->id(),
            'CreditCardInfo' => [
              'cardNumber' => $cc,
              'cvv' => $ccv,
              'expiryDate' => $exp,
            ],
          ],
        ],
      ],
    ];

    $xml_merchant_info['Payment'] = $xml_payment_info['Payment'];

    $xml_request = "<?xml version='1.0' encoding='UTF-8'?>\n<SecurePayMessage>\n"
      . $this->arrayToXml($xml_merchant_info)
      . '</SecurePayMessage>';

    return $xml_request;

  }

  /**
   * Generates a random text string (used for creating a unique message ID)
   */
  public function getUniqueMessageId($min = 10, $max = 20, $randtext = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890') {
    $min = $min < 1 ? 1 : $min;
    $varlen = rand($min, $max);
    $randtextlen = strlen($randtext);
    $text = '';

    for ($i = 0; $i < $varlen; $i++) {
      $text .= substr($randtext, rand(1, $randtextlen), 1);
    }
    return $text;
  }

  /**
   * Converts a hierarchical array of elements into an XML string.
   *
   * @param array $data
   *   Array of data to convert into xml string.
   * @param int $depth
   *   The depth of the elements.
   *
   * @return string
   *   xml string
   */
  public function arrayToXml(array $data, $depth = 0) {
    $xml = '';

    $padding = '  ';
    for ($i = 0; $i < $depth; $i++) {
      $padding .= '  ';
    }

    // Loop through the elements in the data array.
    foreach ($data as $element => $contents) {
      if (is_array($contents)) {
        // Render the element with its child elements.
        $xml .= "{$padding}<{$element}>\n" . $this->arrayToXml($contents, $depth + 1) . "{$padding}</" . strtok($element, ' ') . ">\n";
      }
      else {
        // Render the element with its contents.
        $xml .= "{$padding}<{$element}>{$contents}</{$element}>\n";
      }
    }

    return $xml;
  }

}
