<?php
if (!trait_exists('GeideaController', false)) {
    include_once 'Geidea/GeideaController.php';
}

class ControllerExtensionPaymentGeideaBankCard extends Controller
{
    /**
     * @var array
     */
    private $error = [];

    use GeideaController;
    /**
     * Gateway fields
     * @var array
     */
    // protected $fields = [
    //     'payment_geidea_bank_card_merchant_id',
    //     'payment_geidea_bank_card_secret_key',
    //     'payment_geidea_bank_card_public_key',
    //     'payment_geidea_bank_card_status',
    //     'payment_geidea_bank_card_method_name',
    //     'payment_geidea_bank_card_allow_debug',
    //     'payment_geidea_bank_card_expire_at'
    // ];

    protected $fields = [
        'payment_geidea_bank_card_title',
        'payment_geidea_bank_card_api_password_sandbox',
        'payment_geidea_bank_card_api_password_live',
        'payment_geidea_bank_card_public_key_sandbox',
        'payment_geidea_bank_card_public_key_live',
        'payment_geidea_bank_card_status',
        'payment_geidea_bank_card_sandbox'
    ];
}
