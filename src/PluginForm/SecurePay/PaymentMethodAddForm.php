<?php

namespace Drupal\commerce_securepayau\PluginForm\SecurePay;

use Drupal\profile\Entity\Profile;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;

/**
 * PaymentMethodAddFform for Securepay.com.au.
 *
 * @inheritDoc
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $payment_method = $this->entity;

    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['#tree'] = TRUE;
    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle(),
    ];

    $form['payment_details'] = $this->buildCreditCardForm($form['payment_details'], $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;
    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = $payment_method->getBillingProfile();
    if (!$billing_profile) {
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = Profile::create([
        'type' => 'customer',
        'uid' => $payment_method->getOwnerId(),
      ]);
    }

    if ($order = \Drupal::routeMatch()->getParameter('commerce_order')) {
      $store = $order->getStore();
    }
    else {
      /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
      $store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
      $store = $store_storage->loadDefault();
    }

    if ($this->entity->getPaymentGateway()->get('configuration')['collect_billing_information']) {
      $form['billing_information'] = [
        '#parents' => array_merge($form['#parents'], ['billing_information']),
        '#type' => 'commerce_profile_select',
        '#default_value' => $billing_profile,
        '#default_country' => $store ? $store->getAddress()->getCountryCode() : 'AU',
        '#available_countries' => $store ? $store->getBillingCountries() : [],
      ];
    }

    return $form;
  }

}
