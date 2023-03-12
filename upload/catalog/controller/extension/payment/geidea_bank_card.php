<?php
class ControllerExtensionPaymentGeideaBankCard extends Controller
{
    /**
     * @return mixed
     */
    public function index()
    {
        $this->load->model('checkout/order');
        if (!isset($this->session->data['order_id'])) {
            return false;
        }
        return $this->load->view('extension/payment/geidea_bank_card');
    }

    /**
     * @return mixed
     */
    function callback()
    {
        $this->load->model('checkout/order');

        //log
        $this->log->write('geidea notify:' . file_get_contents('php://input'));


        $data = json_decode(file_get_contents('php://input'), true);

        if(empty($data)) {
            return false;
        }

        if (isset($data['order']['status'])
        && isset($data['order']['merchantReferenceId'])
        && isset($data['order']['merchantPublicKey'])
        && isset($data['order']['amount'])
        && isset($data['order']['currency'])
        && isset($data['order']['orderId'])
        && isset($data['signature'])) {

            $orderId = $data['order']['orderId'];
            $merchantReferenceId = $data['order']['merchantReferenceId'];
            $status = $data['order']['status'];

            $merchantPublicKey = $data['order']['merchantPublicKey'];
            $amount = number_format($data['order']['amount']  , '2' , '.' , '');
            $currency = $data['order']['currency'];

            $sandbox = $this->config->get('payment_geidea_bank_card_sandbox');
            $publicKeySandbox = $this->config->get('payment_geidea_bank_card_public_key_sandbox');
            $publicKeyLive = $this->config->get('payment_geidea_bank_card_public_key_live');
            $publicKey = $sandbox != null && $sandbox == 1 ? $publicKeySandbox : $publicKeyLive;

            $apiPasswordSandbox = $this->config->get('payment_geidea_bank_card_api_password_sandbox');
            $apiPasswordLive = $this->config->get('payment_geidea_bank_card_api_password_live');
            $merchantApiPassword = $sandbox != null && $sandbox == 1 ? $apiPasswordSandbox : $apiPasswordLive;
            if($publicKey == $merchantPublicKey)
            {
                $received_signature = $data['signature'];
                $sig_string = $merchantPublicKey.$amount.$currency.$orderId.$status.$merchantReferenceId;

                $computed_signature = hash_hmac('sha256', $sig_string, $merchantApiPassword, true);
                $computed_signature = base64_encode($computed_signature);

                $this->log->write('\n\ncomputed_signature:' . $computed_signature);
                $this->log->write('\n\received_signature:' . $received_signature);
                if($computed_signature == $received_signature)
                {
                    try{

                        $reference_arr = explode('-', $merchantReferenceId);
                        $order_id = $reference_arr[2];

                        $order_info = $this->model_checkout_order->getOrder($order_id);

                        if (!$order_info) {
                            throw new Exception('Unknow Order (id:' . $order_id . ')');
                        }

                        $paymentStatus = strtoupper($status);

                        if(isset($data['order']['transactions'])){
                            $processing_result='';
                            if(isset($data['order']['transactions'][0]))
                                $processing_result = $data['order']['transactions'][0]['codes']['detailedResponseMessage'];
                            if(isset($data['order']['transactions'][1]))
                                $processing_result .= '---' . $data['order']['transactions'][1]['codes']['detailedResponseMessage'];
                        } else {
                            $processing_result = 'Unknown';
                        }
                        //return
                        if ($paymentStatus == 'PAID' || $paymentStatus == 'SUCCESS') {
                            $this->model_checkout_order->addOrderHistory($order_id, 5, 'Payment Successful for Geidea Order: ' . $orderId);
                            $this->model_checkout_order->addOrderHistory($order_id, 5, 'Merchant reference Id: ' . $merchantReferenceId);
                            //FAIL CLOSE
                        }elseif ($paymentStatus == 'CANCELLED' || $paymentStatus == 'EXPIRED') {
                            $this->model_checkout_order->addOrderHistory($order_id, 16, 'Payment cancelled for Geidea Order Id: ' . $orderId);
                            $this->model_checkout_order->addOrderHistory($order_id, 16, 'Merchant reference Id: ' . $merchantReferenceId);
                            $this->model_checkout_order->addOrderHistory($order_id, 16, 'Cancellation Reason: ' . $processing_result);
                        } elseif ($paymentStatus == 'FAILED') {
                            $this->model_checkout_order->addOrderHistory($order_id, 10, 'Payment Failed for Geidea Order Id: ' . $orderId);
                            $this->model_checkout_order->addOrderHistory($order_id, 10, 'Merchant reference Id: ' . $merchantReferenceId);
                            $this->model_checkout_order->addOrderHistory($order_id, 10, 'Failure Reason: ' . $processing_result);
                        }
                    }catch(Exception $e){
                        $params = array(
                            'action'=>'fail',
                            'err_code'=>$e->getCode(),
                            'err_msg'=>$e->getMessage()
                        );
                        ob_clean();
                        print json_encode($params);
                        exit;
                    }
                }
            }
        }
    }
}
