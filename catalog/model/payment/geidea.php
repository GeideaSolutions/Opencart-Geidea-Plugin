<?php

namespace Opencart\Catalog\Model\Extension\Geidea\Payment;

class Geidea extends \Opencart\System\Engine\Model
{


    public function getMethods(array $address): array
    {
        $this->load->language('extension/geidea/payment/geidea');

        if (!$this->isEnabled()) {

            return [];
        }

        $method_data = [
            'name'  => 'Geidea Payments',
            'code'  => 'geidea',
            'option' => [
                'geidea' => [
                    'code'  => 'geidea.geidea',
                    'name' => 'Geidea pay'
                ],
            ],
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('payment_geidea_sort_order')
        ];

        return $method_data;
    }
    public function isEnabled()
    {
        $api_key = $this->config->get('payment_geidea_public_key_live');
        $enabled = $this->config->get('payment_geidea_status');

        return !empty($api_key) && $enabled == 1;
    }

    /**
     * @param  int $country_id
     * @return array
     */
    public function getCountry($country_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "country WHERE country_id = '" .  (int)$country_id . "'");
        return $query->row;
    }

    /**
     * @param  bool $is_debug
     * @return string
     */
    public static function getUrl($is_debug)
    {
        if ($is_debug) {
            return 'https://sandboxapi.geideacheckout.com/api/v1/international/cashier/create';
        } else {
            return 'https://api.geideacheckout.com/api/v1/international/cashier/create';
        }
    }


    /**
     * @param  $publicKey
     * @param  $merchantID
     * @return array
     */
    public static function httpHeader($publicKey, $merchantID)
    {
        return [
            'Authorization:Bearer ' . $publicKey,
            'MerchantId:' . $merchantID,
            'content-type:application/json',
            'ClientSource:OPENCART'
        ];
    }

    /**
     * @param  $url
     * @param array $header
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public static function httpPost($url, $header, $data)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('php not found curl', 500);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $response = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($httpStatusCode != 200) {
            print_r("invalid HttpStatus:{$httpStatusCode} ,
            response:$response,
            detail_error:" . $error, $httpStatusCode);
        }
        return $response;
    }

    /**
     * @param string $data
     * @param string $secretKey
     * @return mixed
     */
    public function authJson($data, $secretKey)
    {
        return hash_hmac("sha3-512", $data, $secretKey);
    }
}
