<?php

namespace Opencart\Catalog\Controller\Extension\Geidea\Payment;

use Exception;

class Geidea extends \Opencart\System\Engine\Controller
{

  public function index(): string
  {
    $this->load->language('extension/geidea/payment/geidea');
    $this->load->model('extension/geidea/payment/geidea');
    $this->load->model('localisation/country');
    $data['order_id'] = $this->session->data['order_id'];
    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
    $country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));
    $this->load->model('checkout/order');
    if (!isset($this->session->data['order_id'])) {
      return false;
    }

    $sessionData = $this->createsession($order_info);
    $viewData = [
      'paymentObject' => [
        'sessionData' => $sessionData,
        'returnUrl' => $this->url->link('checkout/success'),
        'cancelUrl' => $this->url->link('checkout/checkout', '', true),
      ]
    ];
    $viewData['payment_geidea_environment'] = $this->config->get('payment_geidea_environment');
    return $this->load->view('extension/geidea/payment/geidea', $viewData);
  }

  public function callback()
  {
    $this->load->model('checkout/order');

    //log
    $this->log->write('geidea notify:' . file_get_contents('php://input'));


    $data = json_decode(file_get_contents('php://input'), true);
    // error_log(json_encode($data));

    if (empty($data)) {
      $errors['code'] = 500;
      $errors['message'] = "No data provided";
      print json_encode($errors);
      return false;
    }

    if (
      isset($data['order']['status'])
      && isset($data['order']['merchantReferenceId'])
      && isset($data['order']['merchantPublicKey'])
      && isset($data['order']['amount'])
      && isset($data['order']['currency'])
      && isset($data['order']['orderId'])
      && isset($data['signature'])
    ) {

      $orderId = $data['order']['orderId'];
      $merchantReferenceId = $data['order']['merchantReferenceId'];
      $status = $data['order']['status'];

      $merchantPublicKey = $data['order']['merchantPublicKey'];
      $amount = number_format($data['order']['amount'], '2', '.', '');
      $currency = $data['order']['currency'];
      $timeStamp = $data['timeStamp'];


      $publicKeyLive = $this->config->get('payment_geidea_public_key_live');
      $apiPasswordLive = $this->config->get('payment_geidea_api_password_live');
      if ($publicKeyLive == $merchantPublicKey) {
        $received_signature = $data['signature'];
        $sig_string = $merchantPublicKey . $amount . $currency . $orderId . $status . $merchantReferenceId . $timeStamp;

        $computed_signature = hash_hmac('sha256', $sig_string, $apiPasswordLive, true);
        $computed_signature = base64_encode($computed_signature);

        $this->log->write('\n\ncomputed_signature:' . $computed_signature);
        $this->log->write('\n\received_signature:' . $received_signature);
        if ($computed_signature == $received_signature) {
          try {
            $order_info = $this->model_checkout_order->getOrder($merchantReferenceId);

            if (!$order_info) {
              throw new Exception('Unknow Order (id:' . $merchantReferenceId . ')');
            }
            $paymentStatus = strtoupper($status);
            if (isset($data['order']['transactions'])) {
              $processing_result = '';
              if (isset($data['order']['transactions'][0]))
                $processing_result = $data['order']['transactions'][0]['codes']['detailedResponseMessage'];
              if (isset($data['order']['transactions'][1]))
                $processing_result .= '---' . $data['order']['transactions'][1]['codes']['detailedResponseMessage'];
            } else {
              $processing_result = 'Unknown';
            }
            //return
            if ($paymentStatus == 'PAID' || $paymentStatus == 'SUCCESS') {
              error_log("yoo yeah -- sussesss --");
              $this->model_checkout_order->addHistory($merchantReferenceId, 5, 'Payment Successful for Geidea Order: ' . $orderId);
              $this->model_checkout_order->addHistory($merchantReferenceId, 5, 'Merchant reference Id: ' . $merchantReferenceId);
              //FAIL CLOSE
            } elseif ($paymentStatus == 'CANCELLED' || $paymentStatus == 'EXPIRED') {
              $this->model_checkout_order->addHistory($merchantReferenceId, 16, 'Payment cancelled for Geidea Order Id: ' . $orderId);
              $this->model_checkout_order->addHistory($merchantReferenceId, 16, 'Merchant reference Id: ' . $merchantReferenceId);
              $this->model_checkout_order->addHistory($merchantReferenceId, 16, 'Cancellation Reason: ' . $processing_result);
            } elseif ($paymentStatus == 'FAILED') {
              $this->model_checkout_order->addHistory($merchantReferenceId, 10, 'Payment Failed for Geidea Order Id: ' . $orderId);
              $this->model_checkout_order->addHistory($merchantReferenceId, 10, 'Merchant reference Id: ' . $merchantReferenceId);
              $this->model_checkout_order->addHistory($merchantReferenceId, 10, 'Failure Reason: ' . $processing_result);
            }
          } catch (Exception $e) {
            $params = array(
              'action' => 'fail',
              'err_code' => $e->getCode(),
              'err_msg' => $e->getMessage()
            );
            ob_clean();
            print json_encode($params);
            exit;
          }
        }
      }
    }
  }

  // old callback in general form
  protected function updateOrder($post_data, $get_data)
  {
    if (isset($get_data['sid'])) {
      $this->session->start($get_data['sid']);
    }
    $this->load->language('extension/geidea/payment/geidea');
    $this->load->model('extension/geidea/payment/geidea');
    $this->load->model('checkout/order');

    // $order_id is never used
    if (isset($this->session->data['order_id'])) {
      $order_id = $this->session->data['order_id'];
    } else {
      $order_id = 0;
    }

    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
    $order_amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) * 100;

    if ($order_info) {
      $data = array_merge($post_data, $get_data);
      //payment was made successfully
      if ((isset($data['status']) && $data['status'] == 'paid') || (isset($get_data['status']) && $get_data['status'] == 'paid')) {
        if ($this->verifyAmount($get_data['id'], $order_amount)) {
          $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_geidea_order_status_id'), 'Payment is successfull');
        } else {
          $this->log->write('Geidea payment is successful but amount does not match paid, possible tampering.');
          $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'), $this->language->get('text_price_manipulated'));
        }
      } else {
        $error = $this->language->get('text_unable');
      }
    } else {
      $error = $this->language->get('text_unable');
    }

    if (isset($error)) {
      if (isset($get_data['message'])) {
        $data['message'] = $get_data['message'];
      } else {
        $data['message'] = 'Payment failed';
      }
      $this->session->data['error'] = $this->language->get('text_unable') . ' ' . $data['message'];
      $this->log->write('Geidea payment failed: #' . $this->session->data['order_id'] . ' ' . $data['message']);
      $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_geidea_failed_order_status_id'), 'Payment Failed: ' . $data['message']);

      return false;
    }

    return true;
  }

  public function registerInitiatedOrder()
  {
    $this->log->write($this->request);
    $this->addOrder($this->request->get['id'], $this->request->post['payment_id']);
  }

  public function verifyAmount($payment_id, $order_amount)
  {
    $header = [
      'Authorization: Basic ' . base64_encode($this->config->get('payment_geidea_api_secret_key') . ':')
    ];
    // TODO: change this url
    $curl = curl_init('https://api.geidea.net/v1/payments/' . $payment_id);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    $response = json_decode(curl_exec($curl), true);
    if (isset($response['message'])) $this->log->write('Geidea Payment Verification Failed: ' . $response['message']);

    if (isset($response['amount']) && $response['amount'] == $order_amount) return true;

    else return false;
  }

  public function getPaymentObject($orderId, $currency, $orderPrice, $publicKey)
  {
    $order_str = 'OC-' . date('Ymd', time());
    $orderId = $order_str . '-' . $orderId;

    $cancelUrl = $this->url->link('checkout/checkout', '', true);
    $returnUrl = $this->url->link('checkout/success');
    $callbackUrl = $this->url->link('extension/geidea/payment/geidea%7Ccallback');
    $lang = $this->language->get('code') ?? 'en';
    if (strpos($lang, 'ar'))  $lang = 'ar';
    else $lang = 'en';
    //$this->model_checkout_order->addHistory($orderId, 1, 'Pending Payment');

    //Order info
    $price = round((float)$orderPrice, 2);
    //building body
    $paymentObject = [
      "amount" => $price,
      "currency" => $currency,
      "merchantReferenceId" => $orderId,
      'returnUrl' => $returnUrl,
      'callbackUrl' => $callbackUrl,
      'cancelUrl' => $cancelUrl,
      'merchantKey' => $publicKey,
      'language' => $lang,
    ];

    return $paymentObject;
  }

  public function createsession($order)
  {
    $merchant_logo_url = null;
    if (!empty($this->config->get('payment_geidea_merchant_logo'))) {
      $baseURL = $this->config->get('config_url');
      $merchant_logo_url = $baseURL . 'image/' . $this->config->get('payment_geidea_merchant_logo');
    }
    $lang = $this->language->get('code') ?? 'en';
    if (strpos($lang, 'ar')) $lang = 'ar';
    else $lang = 'en';
    $timestamp =  date("n/d/Y g:i:s A");
    $signature = $this->generateSignature($this->config->get('payment_geidea_public_key_live'), number_format(round($order['total'], 2), 2), $order['currency_code'], (string) $order['order_id'], $this->config->get('payment_geidea_api_password_live'), $timestamp);
    $sessionRequestPayload = array(
      'merchantPublicKey' => $this->config->get('payment_geidea_public_key_live'),
      'apiPassword' =>  $this->config->get('payment_geidea_api_password_live'),
      'callbackUrl' => $this->url->link('extension/geidea/payment/geidea%7Ccallback'),
      'amount' => number_format(round($order['total'], 2), 2),
      'currency' => $order['currency_code'],
      'language' => $lang,
      'timestamp' => $timestamp,
      'merchantReferenceId' =>  (string) $order['order_id'],
      'paymentIntentId' => null,
      'paymentOperation' => "Pay",
      'initiatedBy' => 'Internet',
      "cardOnFile" => false,
      "tokenId" => null,
      'customer' => array(
        'create' => false,
        'setDefaultMethod' => false,
        'email' => !empty($order['email']) ? $order['email'] : null,
        'phoneNumber' => !empty($order['telephone']) ? $order['telephone'] : null,
        'address' => array(
          'billing' => array(
            'country' => !empty($order['payment_iso_code_3']) ? $order['payment_iso_code_3'] : null,
            'street' => !empty($order['payment_address_1']) ? $order['payment_address_1'] : null,
            'city' => !empty($order['payment_city']) ? $order['payment_city'] : null,
            'postalCode' =>  !empty($order['payment_postcode']) ? $order['payment_postcode'] : null,
          ),
          'shipping' => array(
            'country' => !empty($order['shipping_iso_code_3']) ? $order['shipping_iso_code_3'] : null,
            'street' => !empty($order['shipping_address_1']) ? $order['shipping_address_1'] : null,
            'city' => !empty($order['shipping_city']) ? $order['shipping_city'] : null,
            'postalCode' =>  !empty($order['shipping_postcode']) ? $order['shipping_postcode'] : null,
          ),
        ),
      ),
      'appearance' => array(
        'merchant' => array(
          'logoUrl' => $merchant_logo_url,
        ),
        'showAddress' => ($this->config->get('payment_geidea_address_enabled') == 'yes') ? true : false,
        'showEmail' => ($this->config->get('payment_geidea_email_enabled') == 'yes') ? true : false,
        'showPhone' => ($this->config->get('payment_geidea_phone_enabled') == 'yes') ? true : false,
        'receiptPage' => ($this->config->get('payment_geidea_receipt_enabled') == 'yes') ? true : false,
        'styles' => array(
          'hideGeideaLogo' => false,
          'headerColor' => ($this->config->get('payment_geidea_header_colour') === '') ? null : $this->config->get('payment_geidea_header_colour'),
          'hppProfile' => $this->config->get('payment_geidea_hpp'),
        ),
        'uiMode' => 'modal',
      ),
      'order' => array(
        'integrationType' => 'Plugin',
      ),
      'platform' => array(
        'name' => "Opencart",
        'version' => "3.1.0",
        'pluginVersion' => "3.1.0",
        'partnerId' => "222",
      ),
      'signature' => $signature,
    );

    // Environment Configure
    if ($this->config->get('payment_geidea_environment') === 'EGY-PROD') {
      $createSessionUrl = 'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session';
    } elseif ($this->config->get('payment_geidea_environment') === 'KSA-PROD') {
      $createSessionUrl = 'https://api.ksamerchant.geidea.net/payment-intent/api/v2/direct/session';
    } elseif ($this->config->get('payment_geidea_environment') === 'UAE-PROD') {
      $createSessionUrl = 'https://api.merchant.geidea.ae/payment-intent/api/v2/direct/session';
    }

    $response = $this->sendGiRequest(
      $createSessionUrl,
      $this->config->get('payment_geidea_public_key_live'),
      $this->config->get('payment_geidea_api_password_live'),
      json_encode($sessionRequestPayload)
    );
    return $response;
  }


  function sendGiRequest($gatewayUrl, $merchantKey, $password, $values, $method = 'POST')
  {
    $origString = $merchantKey . ":" . $password;
    $authKey = base64_encode($origString);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $gatewayUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_POSTFIELDS => $values,
      CURLOPT_HTTPHEADER => array(
        'accept: application/json',
        'Authorization: Basic ' . $authKey,
        'content-type: application/json'
      ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
  }

  private function generateSignature($merchantPublicKey, $amountStr, $orderCurrency, $orderMerchantReferenceId, $apiPassword, $timestamp)
  {
    $data = "{$merchantPublicKey}{$amountStr}{$orderCurrency}{$orderMerchantReferenceId}{$timestamp}";
    $hash = hash_hmac('sha256', $data, $apiPassword, true);
    return base64_encode($hash);
  }
}
