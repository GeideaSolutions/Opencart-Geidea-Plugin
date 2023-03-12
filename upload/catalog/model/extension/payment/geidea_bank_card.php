<?php
if (!trait_exists('GeideaModel', false)) {
    include_once 'Geidea/GeideaModel.php';
}

class ModelExtensionPaymentGeideaBankCard extends Model
{

    use GeideaModel;

    /**
     * @param  $address
     * @param  $total
     * @return mixed
     */
    public function getMethod($address, $total)
    {
        $method_data = [
            'code'       => 'geidea_bank_card',
            'title'      => ($this->config->get('payment_geidea_bank_card_title')) ? $this->config->get('payment_geidea_bank_card_title') : 'Geidea Bank Card',
            'terms'      => '',
            'sort_order' => '',
        ];

        return $method_data;
    }
}
