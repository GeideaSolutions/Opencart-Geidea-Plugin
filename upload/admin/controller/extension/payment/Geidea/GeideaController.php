<?php
/**
 * Shared functions, fields between all gateways
 * Version 1.1.1
 */
trait GeideaController
{
    public function index()
    {
        $this->document->setTitle("GeideaPay");
        $this->load->model('setting/setting');
        $this->preSettings();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->user->hasPermission('modify', "extension/payment/geidea_bank_card")) {
            $this->model_setting_setting->editSetting("payment_geidea_bank_card", $this->request->post);

            $this->session->data['success'] = "Success: you updated your GeideaPay settings";

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        foreach ($this->fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        $this->load->model('localisation/order_status');
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');
        $data['action']      = $this->url->link(
            "extension/payment/geidea_bank_card",
            'user_token=' . $this->session->data['user_token'],
            true
        );
        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true);
        $data['callback'] = HTTP_CATALOG . "index.php?route=extension/payment/geidea_bank_card/callback";

        $this->response->setOutput($this->load->view("extension/payment/geidea_bank_card", $data));
    }

    public function preSettings()
    {
        if ($this->config->get('payment_geidea_bank_card_title') == null) {
            $this->config->set('payment_geidea_bank_card_title', 'Pay using credit card with Geidea');
        }

        if ($this->config->get('payment_geidea_bank_card_sandbox') == null) {
            $this->config->set('payment_geidea_bank_card_sandbox', 1);
        }

    }
}
