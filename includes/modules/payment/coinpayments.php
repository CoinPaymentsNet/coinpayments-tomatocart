<?php
/*
 $Id: coinpayments.php $
TomatoCart Open Source Shopping Cart Solutions
http://www.tomatocart.com

Copyright (c) 2020 CoinPayments.NET;

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License v2 (1991)
as published by the Free Software Foundation.
*/

class osC_Payment_coinpayments extends osC_Payment
{
    var $_title,
        $_code = 'coinpayments',
        $_status = false,
        $_sort_order,
        $_order_id,
        $_ignore_order_totals = array('sub_total', 'tax', 'total'),
        $_transaction_response,
        $_api,
        $_method_title;

    // class constructor
    function osC_Payment_coinpayments()
    {
        global $osC_Database, $osC_Language, $osC_ShoppingCart;

        $coinpayments_link = sprintf(
            '<a href="%s" target="_blank" style="text-decoration: underline; font-weight: bold;" title="CoinPayments.net">CoinPayments.net</a>',
            'https://alpha.coinpayments.net/'
        );
        $description = sprintf(' via <br/>%s', $coinpayments_link);
        $this->_title = $osC_Language->get('payment_coinpayments_title');
        $this->_method_title = $osC_Language->get('payment_coinpayments_method_title') . $description;
        $this->_status = ((MODULE_PAYMENT_COINPAYMENTS_STATUS == '1') ? true : false);
        $this->_sort_order = MODULE_PAYMENT_COINPAYMENTS_SORT_ORDER;

        if ($this->_status === true) {

            $this->order_status = MODULE_PAYMENT_COINPAYMENTS_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_COINPAYMENTS_ORDER_STATUS_ID : (int)ORDERS_STATUS_PAID;

            if ((int)MODULE_PAYMENT_COINPAYMENTS_ZONE > 0) {
                $check_flag = false;

                $Qcheck = $osC_Database->query('select zone_id from :table_zones_to_geo_zones where geo_zone_id = :geo_zone_id and zone_country_id = :zone_country_id order by zone_id');
                $Qcheck->bindTable(':table_zones_to_geo_zones', TABLE_ZONES_TO_GEO_ZONES);
                $Qcheck->bindInt(':geo_zone_id', MODULE_PAYMENT_COINPAYMENTS_ZONE);
                $Qcheck->bindInt(':zone_country_id', $osC_ShoppingCart->getBillingAddress('country_id'));
                $Qcheck->execute();

                while ($Qcheck->next()) {
                    if ($Qcheck->valueInt('zone_id') < 1) {
                        $check_flag = true;
                        break;
                    } elseif ($Qcheck->valueInt('zone_id') == $osC_ShoppingCart->getBillingAddress('zone_id')) {
                        $check_flag = true;
                        break;
                    }
                }

                if ($check_flag == false) {
                    $this->_status = false;
                }
            }

            $coinApiFile = sprintf('%s%s', DIR_FS_CATALOG, 'ext/coinpayments/CoinpaymentsApi.php');
            if (file_exists($coinApiFile)) {
                require_once $coinApiFile;
                $this->_api = new CoinpaymentsApi();
            }
        }
    }

    function selection()
    {
        return array('id' => $this->_code,
            'module' => $this->_method_title);
    }

    function confirmation()
    {
        $this->_order_id = osC_Order::insert(ORDERS_STATUS_PREPARING);
    }

    function process_button()
    {
        try {
//            $invoice = $this->createInvoice();
            $invoice = array(
                'id' => '85XY1bbXky2r3YCGcpUFgR',
                'link' => 'https://api.coinpayments.net/api/v1/merchant/invoices/85XY1bbXky2r3YCGcpUFgR',
            );

            $_SESSION['coin-invoice'] = $invoice;
        } catch (Exception $e) {
        }

        return '';
    }

    function getActionURL()
    {
        return true;
    }

    function process()
    {
        global $osC_ShoppingCart, $osC_Database;

        if (isset($_SESSION['coin-invoice'])) {

            $invoice = $_SESSION['coin-invoice'];

            $params = array(
                'invoice-id' => $invoice['id'],
                'success-url' => osc_href_link(FILENAME_CHECKOUT, 'process', 'SSL', null, null, true),
                'cancel-url' => osc_href_link(FILENAME_CHECKOUT, 'checkout', 'SSL', null, null, true),
            );

            $redirect = sprintf('%s/%s/?%s', CoinpaymentsApi::CHECKOUT_URL, CoinpaymentsApi::API_CHECKOUT_ACTION, http_build_query($params));
            unset($_SESSION['coin-invoice']);
            osc_redirect($redirect);
        } else {

            $prep = explode('-', $_SESSION['prepOrderID']);
            if ($prep[0] == $osC_ShoppingCart->getCartID()) {
                $Qcheck = $osC_Database->query('select orders_status_id from :table_orders_status_history where orders_id = :orders_id');
                $Qcheck->bindTable(':table_orders_status_history', TABLE_ORDERS_STATUS_HISTORY);
                $Qcheck->bindInt(':orders_id', $prep[1]);
                $Qcheck->execute();

                $paid = false;
                if ($Qcheck->numberOfRows() > 0) {
                    while ($Qcheck->next()) {
                        if ($Qcheck->valueInt('orders_status_id') == $this->order_status) {
                            $paid = true;
                        }
                    }
                }

                if ($paid === false) {
                    if (osc_not_null(MODULE_PAYMENT_COINPAYMENTS_PROCESSING_ORDER_STATUS_ID)) {
                        osC_Order::process($_POST['invoice'], MODULE_PAYMENT_COINPAYMENTS_PROCESSING_ORDER_STATUS_ID, 'CoinPayments Processing Transaction');
                    }
                }
            }

            unset($_SESSION['prepOrderID']);
        }
    }

    /**
     * @return bool|mixed|null
     * @throws Exception
     */
    public function createInvoice()
    {

        global $osC_Currencies, $osC_ShoppingCart;

        $invoice = null;

        $client_id = MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID;
        $client_secret = MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET;

        $invoice_id = sprintf('%s|%s', md5(HTTPS_SERVER), $this->_order_id);
        $coin_currency = $this->_api->getCoinCurrency($osC_Currencies->getCode());

        $amount = number_format($osC_ShoppingCart->getTotal(), $coin_currency['decimalPlaces'], '', '');
        $display_value = $osC_ShoppingCart->getTotal();

        if (MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS) {
            $resp = $this->_api->createMerchantInvoice($client_id, $client_secret, $coin_currency['id'], $invoice_id, $amount, $display_value);
            $invoice = array_shift($resp['invoices']);
        } else {
            $invoice = $this->_api->createSimpleInvoice($client_id, $coin_currency['id'], $invoice_id, $amount, $display_value);
        }

        return $invoice;
    }

    function callback()
    {
        global $osC_Database, $osC_Currencies;


        $content = file_get_contents('php://input');

        if (MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS && !empty($_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'])) {

            $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'];
            $request_data = json_decode($content, true);

            if ($this->checkDataSignature($signature, $content) && isset($request_data['invoice']['invoiceId'])) {

                $invoice_str = $request_data['invoice']['invoiceId'];
                $invoice_str = explode('|', $invoice_str);
                $host_hash = array_shift($invoice_str);
                $invoice_id = array_shift($invoice_str);

                if ($host_hash == md5(HTTPS_SERVER) && $invoice_id) {

                    $Qcheck = $osC_Database->query('select orders_status, currency, currency_value from :table_orders where orders_id = :orders_id ');
                    $Qcheck->bindTable(':table_orders', TABLE_ORDERS);
                    $Qcheck->bindInt(':orders_id', $invoice_id);
                    $Qcheck->execute();

                    if ($Qcheck->numberOfRows() > 0) {
                        $order = $Qcheck->toArray();

                        $status = $request_data['invoice']['status'];
                        if ($order['orders_status'] < ORDERS_STATUS_PAID) {
                            if ($status == 'Completed') {
                                $order_status = MODULE_PAYMENT_COINPAYMENTS_COMPLETED_ORDER_STATUS_ID;
                            } elseif ($status == 'Cancelled') {
                                $order_status = MODULE_PAYMENT_COINPAYMENTS_CANCELLED_ORDER_STATUS_ID;
                            }

                            osC_Order::process($invoice_id, $order_status);

                            $Qtransaction = $osC_Database->query('insert into :table_orders_transactions_history (orders_id, transaction_code, transaction_return_value, transaction_return_status, date_added) values (:orders_id, :transaction_code, :transaction_return_value, :transaction_return_status, now())');
                            $Qtransaction->bindTable(':table_orders_transactions_history', TABLE_ORDERS_TRANSACTIONS_HISTORY);
                            $Qtransaction->bindInt(':orders_id', $invoice_id);
                            $Qtransaction->bindInt(':transaction_code', 1);
                            $Qtransaction->bindValue(':transaction_return_value', $status);
                            $Qtransaction->bindInt(':transaction_return_status', 1);
                            $Qtransaction->execute();

                            $Qtransaction->freeResult();
                        }
                    }
                }
            }
        }
    }

    function checkDataSignature($signature, $content)
    {

        $request_url = $this->_api->getNotificationUrl($this->_code);
        $client_secret = MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET;
        $signature_string = sprintf('%s%s', $request_url, $content);
        $encoded_pure = $this->_api->encodeSignatureString($signature_string, $client_secret);
        return $signature == $encoded_pure;
    }
}
