<?php

class pm_beezyycashier
{
    public $id = __CLASS__;
    public $name = 'Beezyycashier';
    public $description = 'Beezyycashier payment gateway';
    public $author = 'BEEZYY CASHIER SYSTEM LTD';
    public $version = '1.0';
    public $website = 'https://beezyycashier.com/';
    public $priority = 0;

    public function options($items, $subtotal, $tax, $currency_code, $customer)
    {
        if (empty($this->settings['status'])) {
            return;
        }
        return array(
            'title' => language::translate(__CLASS__ . ':title', 'Beezyycashier'),
            'options' => array(
                array(
                    'id' => 'Beezyycashier',
                    'icon' => $this->settings['icon'],
                    'name' => language::translate(__CLASS__ . ':name', 'Beezyycashier'),
                    'description' => language::translate(__CLASS__ . ':description', 'Beezyycashier'),
                    'fields' => '',
                    'cost' => 0,
                    'tax_class_id' => 0,
                    'confirm' => language::translate(__CLASS__ . ':title_confirm_order', 'Confirm Order'),
                ),
            ),
        );
    }

    public function pre_check($order)
    {
    }

    public function transfer($order)
    {
        $payment_method = explode(":", $this->settings['payment_method']);
        $payment_method = $payment_method[0];
        $order->save();
        $requestData = [];
        $requestData['reference'] = strval($order->data['id']);
        $requestData['amount'] = $order->data['payment_due'];
        $requestData['currency'] = $order->data['currency_code'];
        $requestData['customer']['email'] = $order->data['customer']['email'];
        $requestData['customer']['name'] = $order->data['customer']['firstname'] . ' ' . $order->data['customer']['lastname'];
        $requestData['customer']['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $requestData['urls']['success'] = document::ilink('order_process');
        $requestData['urls']['fail'] = document::ilink('checkout');
        $requestData['urls']['notification'] = document::link('ext/beezyycashier/callback.php');
        $requestData['payment_method'] = $payment_method;

        $invoice = $this->createInvoice($requestData);


        $fields = array();
        if (isset($invoice['data']['fields'])) {
            $fields = $invoice['data']['fields'];
        }
        $order->data['payment_transaction_id'] = $invoice['data']['payment_id'];
        $order->save();

        return array(
            'method' => $invoice['data']['method'],
            'action' => $invoice['data']['url'],
            'fields' => $fields
        );
    }

    public function verify($order)
    {
    }

    public function after_process($order)
    {
        $checkIPN = $this->checkIPN($order->data['payment_transaction_id']);

        if ($order->data['payment_transaction_id'] != $checkIPN['payment_id']) {
            die('Payment Transaction Incorrect');
        }

        if ($checkIPN['status'] == 'success') {
            if ($order->data['order_status_id'] == $this->settings['order_status_id']) {
                die('Nothing update');
            } else {
                $order->data['order_status_id'] = $this->settings['order_status_id'];
                $order->save();
            }
        }

        if ($checkIPN['status'] == 'fail') {
            if ($order->data['order_status_id'] == $this->settings['fail_status_id']) {
                die('Nothing update');
            } else {
                $order->data['order_status_id'] = $this->settings['fail_status_id'];
                $order->save();
            }
        }
    }

    public function receipt($order)
    {
    }

    public function settings()
    {
        return array(
            array(
                'key' => 'status',
                'default_value' => '0',
                'title' => 'Status',
                'description' => 'Enables or disables the module.',
                'function' => 'toggle("e/d")',
            ),
            array(
                'key' => 'icon',
                'default_value' => 'images/payment/' . __CLASS__ . '.png',
                'title' => 'Icon',
                'description' => 'Web path of the icon to be displayed.',
                'function' => 'input()',
            ),
            array(
                'key' => 'title',
                'default_value' => 'Beezyycashier',
                'title' => 'Title',
                'description' => 'Title to be displayed to the users',
                'function' => 'input()',
            ),
            array(
                'key' => 'name',
                'default_value' => 'Beezyycashier',
                'title' => 'Name',
                'description' => 'Name of the payment gateway',
                'function' => 'input()',
            ),
            array(
                'key' => 'description',
                'default_value' => 'Beezyycashier payment gateway',
                'title' => 'Description',
                'description' => 'Description to be displayed to the users',
                'function' => 'input()',
            ),
            array(
                'key' => 'order_status_id',
                'default_value' => '0',
                'title' => 'Order Status:',
                'description' => 'Give successful orders made with this payment module the following order status.',
                'function' => 'order_status()',
            ),
            array(
                'key' => 'fail_status_id',
                'default_value' => '0',
                'title' => 'Fail Status:',
                'description' => 'Give fail orders made with this payment module the following order status.',
                'function' => 'order_status()',
            ),
            array(
                'key' => 'priority',
                'default_value' => '0',
                'title' => 'Priority',
                'description' => 'Process this module in the given priority order.',
                'function' => 'int()',
            ),
            array(
                'key' => 'secret_key',
                'default_value' => '',
                'title' => 'Secret Key',
                'description' => 'Beezyycashier Secret_key',
                'function' => 'input()',
            ),
            array(
                'key' => 'payment_method',
                'default_value' => '',
                'title' => 'Payment Method',
                'description' => 'The list of methods will appear after you enter the correct secret key and save the settings!!!',
                'function' => 'select(' . $this->getPaymentMethods() . ')'
            )
        );

    }

    public function install()
    {
    }

    public function uninstall()
    {
    }

    public function callback($order)
    {
        if (!isset($order['payment_transaction_id'])) {
            die('No Payment Transaction');
        }

        $checkIPN = $this->checkIPN($order['payment_transaction_id']);

        if (empty($checkIPN)) {
            die('No transaction found!');
        }

        if ($order['payment_transaction_id'] != $checkIPN['payment_id']) {
            die('Payment Transaction Incorrect');
        }

        if ($checkIPN['status'] == 'success') {
            if ($order['order_status_id'] == $this->settings['order_status_id']) {
                die('Nothing update');
            } else {
                database::query(
                    "UPDATE " . DB_TABLE_ORDERS . " SET `order_status_id` = '" . $this->settings['order_status_id'] . "' WHERE `id` = " . $order['id'] . "");
            }
        }

        if ($checkIPN['status'] == 'fail') {
            if ($order['order_status_id'] == $this->settings['fail_status_id']) {
                die('Nothing update');
            } else {
                database::query(
                    "UPDATE " . DB_TABLE_ORDERS . " SET `order_status_id` = '" . $this->settings['fail_status_id'] . "' WHERE `id` = " . $order['id'] . "");
            }
        }
    }

    private function createInvoice($data)
    {
        $payload = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.beezyycashier.com/v1/payment/create");
        $this->request($ch);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $result = curl_exec($ch);
        return json_decode($result, true);
    }

    private function request($ch)
    {
        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Content-Type: application/json";
        $headers[] = "Cache-Control: no-cache";
        $headers[] = "Authorization: Bearer " . $this->settings['secret_key'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    }

    private function checkIPN($payment_id)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.beezyycashier.com/v1/payment/$payment_id");
        $this->request($ch);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        return $result['data'];
    }

    private function getPaymentMethods()
    {
        $module_query = database::query(
            "SELECT * from " . DB_TABLE_MODULES . " WHERE module_id = 'pm_beezyycashier'");
        $module_settings = database::fetch($module_query);
        $module_settings = json_decode($module_settings['settings'], true);
        $secret_key = $module_settings['secret_key'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.beezyycashier.com/v1/payment-method/list");
        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Content-Type: application/json";
        $headers[] = "Cache-Control: no-cache";
        $headers[] = "Authorization: Bearer " . $secret_key;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        $result = $result['data'];
        $list = '';
        $i = 0;
        $len = count($result);
        foreach ($result as $pm) {
            if ($i == 0) {
                $list = $list . '' . $pm["id"] . ':' . $pm["title"] . '", ';
            } else if ($i == $len - 1) {
                $list = $list . '"' . $pm["id"] . ':' . $pm["title"] . '';
            }
            $i++;
        }
        return $list;
    }
}
