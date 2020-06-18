<?php

namespace Drupal\commerce_securepayau\Plugin\Oauth2Client;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OAuth2 Client to authenticate with SecurePay.
 *
 * @Oauth2Client(
 *   id = "securepay",
 *   name = @Translation("SecuredPay"),
 *   grant_type = "client_credentials",
 *   resource_owner_uri = "",
 *   scopes = {
 *    "https://api.payments.auspost.com.au/payhive/payments/read",
 *    "https://api.payments.auspost.com.au/payhive/payments/write",
 *   },
 *   scope_separator = " ",
 *   collaborators = {
 *     "optionProvider" = "\League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider",
 *   }
 * )
 */
class SecurePay extends Oauth2ClientPluginBase {

  /**
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->config = $container->get('config.factory')->get('commerce_payment.commerce_payment_gateway.securepay_paypal');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId() {
    return $this->config->get('configuration')['client_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret() {
    return $this->config->get('configuration')['client_secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUri() {
    $this->checkKeyDefined('redirect_uri');

    return $this->pluginDefinition['redirect_uri'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUri() {
    return $this->getTokenUri();
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUri() {
    if ($this->config->get('configuration')['mode'] === 'live') {
      return 'https://hello.auspost.com.au/oauth2/ausrkwxtmx9Jtwp4s356/v1/token';
    }

    return 'https://hello.sandbox.auspost.com.au/oauth2/ausujjr7T0v0TTilk3l5/v1/token';
  }

}
