## Commerce SecurePay AU

Commerce SecurePay is a payment gateway module for Drupal Commerce 2 that allows
you to process credit card payments on your site using SecurePay payment service
. 

Please note that at the moment only once-off payments are supported with the
**SecurePay XML API** and **PayPal REST API**.

PayPal payments require the [OAuth2 Client][1] module.

All other tasks like refunds and deletion should be performed on
Securepay.com.au merchant account facility.

## Installation

Requirements:
 - Commerce 
 - Commerce Payment
 - You will require a merchant account with securepay.com.au to accept payments

Run following composer command to download the module:

    composer require drupal/commerce_securepayau

## 
Enable module with drush or administration UI.

[1]: https://www.drupal.org/project/oauth2_client
