<?php
class ControllerExtensionStatic extends Controller
{
    public function index()
    {
        $this->load->model('checkout/order');

        if (!isset($this->session->data['order_id'])) {
            return false;
        }

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $sandbox = $this->config->get('payment_geidea_bank_card_sandbox');
        $publicKeySandbox = $this->config->get('payment_geidea_bank_card_public_key_sandbox');
        $publicKeyLive = $this->config->get('payment_geidea_bank_card_public_key_live');
        $publicKey = $sandbox != null && $sandbox == 1 ? $publicKeySandbox : $publicKeyLive;


        $viewData = [
            'paymentObject' => $this->getPaymentObject(
                $this->session->data['order_id'],
                $this->session->data['currency'],
                $order_info['total'],
                $publicKey
            )
        ];

        $this->response->setOutput($this->load->view('extension/static', $viewData));

    }



    public function getPaymentObject($orderId, $currency, $orderPrice, $publicKey)
    {
        $order_str = 'OC-' . date('Ymd', time());
        $orderId = $order_str . '-' . $orderId;

        $cancelUrl = $this->url->link('checkout/checkout', '', true);
        $returnUrl = $this->url->link('checkout/success');
        $callbackUrl = $this->url->link('extension/payment/geidea_bank_card/callback');
        $lang = $this->language->get('code') ?? 'en';
        if(strpos($lang , 'ar'))  $lang = 'ar';
        else $lang= 'en';

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
}
