<?php

namespace Opencart\Admin\Controller\Extension\Geidea\Payment;

class Geidea extends \Opencart\System\Engine\Controller
{
    private $error = [];
    private $route_extension = 'extension/geidea/payment/geidea';


    public function index(): void
    {
        $this->load->language($this->route_extension);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->preSettings();

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extensions'),
            'href' => $this->url->link('marketplace/opencart/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->route_extension, 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/geidea/payment/geidea|save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['text_applepay_options'] = $this->language->get('text_applepay_options');
        $data['help_api_key'] = $this->language->get('help_api_key');
        $data['help_order_status'] = $this->language->get('help_order_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entery_api_key'] = $this->language->get('entery_api_key');
        $data['entery_api_secret_key'] = $this->language->get('entery_api_secret_key');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_order_status_id'] = $this->language->get('entry_order_status_id');
        $data['entry_failed_order_status_id'] = $this->language->get('entry_failed_order_status_id');
        $data['entry_payment_type'] = $this->language->get('entry_payment_type');
        $data['entry_network_type'] = $this->language->get('entry_network_type');
        $data['entery_enable_mada'] = $this->language->get('entery_enable_mada');
        $data['entery_enable_visa'] = $this->language->get('entery_enable_visa');
        $data['entery_enable_amex'] = $this->language->get('entery_enable_amex');
        $data['entery_enable_mastercard'] = $this->language->get('entery_enable_mastercard');
        $data['entery_enable_creditcard'] = $this->language->get('entery_enable_creditcard');
        $data['entery_enable_stc_pay'] = $this->language->get('entery_enable_stc_pay');
        $data['entery_enable_apple_pay'] = $this->language->get('entery_enable_apple_pay');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        $_items = [
            'payment_geidea_title',
            'payment_geidea_api_password_live',
            'payment_geidea_public_key_live',
            'payment_geidea_status',
            'payment_geidea_environment',
            'payment_geidea_sort_order',
            'payment_geidea_hpp',
            'payment_geidea_header_colour',
            'payment_geidea_receipt_enabled',
            'payment_geidea_address_enabled',
            'payment_geidea_partner_id',
            'payment_geidea_phone_enabled',
            'payment_geidea_email_enabled',
            'payment_geidea_merchant_logo'
        ];

        for ($item = 0; $item <= count($_items) - 1; $item++) {
            if (isset($this->request->post[$_items[$item]])) {
                $data[$_items[$item]] = $this->request->post[$_items[$item]];
            } else {
                $data[$_items[$item]] = $this->config->get($_items[$item]);
            }
        };

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view($this->route_extension, $data));
    }

    public function save(): void
    {
        $this->load->language($this->route_extension);
        $json = [];

        if (!$this->user->hasPermission('modify', $this->route_extension)) {
            $json['warning'] = $this->language->get('error_permission');
        }

        $this->load->model('localisation/language');

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_geidea', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        if (isset($this->request->files['payment_geidea_merchant_logo']) && is_uploaded_file($this->request->files['payment_geidea_merchant_logo']['tmp_name'])) {
            $file = $this->request->files['payment_geidea_merchant_logo'];
            if ($this->validateImage($file)) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $uniqueTempFilename = uniqid() . '.' . $extension;
                $imagePath = DIR_IMAGE . $uniqueTempFilename;
                move_uploaded_file($file['tmp_name'], $imagePath);
                $this->load->model('setting/setting');
                $currentSettings = $this->model_setting_setting->getSetting('payment_geidea');
                $currentSettings['payment_geidea_merchant_logo'] = $uniqueTempFilename;
                $this->model_setting_setting->editSetting('payment_geidea', $currentSettings);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function preSettings()
    {
        if ($this->config->get('payment_geidea_title') == null) {
            $this->config->set('payment_geidea_title', 'Pay using credit card with Geidea');
        }

        if ($this->config->get('payment_geidea_sandbox') == null) {
            $this->config->set('payment_geidea_sandbox', 1);
        }
    }

    public function validateImage($file)
    {
        if ($file['error'] == UPLOAD_ERR_OK) {
            $mimeTypes = array('image/jpeg', 'image/png', 'image/svg');
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo && in_array($imageInfo['mime'], $mimeTypes)) {
                return true;
            }
        }
        return false;
    }
}
