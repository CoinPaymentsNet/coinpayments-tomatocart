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

/**
 * The administration side of the CoinPayments.NET payment module
 */
class osC_Payment_coinpayments extends osC_Payment_Admin
{

    /**
     * The administrative title of the payment module
     *
     * @var string
     * @access private
     */
    var $_title;

    /**
     * The code of the payment module
     *
     * @var string
     * @access private
     */

    var $_code = 'coinpayments';

    /**
     * The developers name
     *
     * @var string
     * @access private
     */

    var $_author_name = 'CoinPayments.net and tomatocart';

    /**
     * The developers address
     *
     * @var string
     * @access private
     */

    var $_author_www = 'https://api.coinpayments.net';

    /**
     * The status of the module
     *
     * @var boolean
     * @access private
     */

    var $_status = false;

    /**
     * Constructor
     */

    function osC_Payment_coinpayments()
    {
        global $osC_Language;

        $this->_title = $osC_Language->get('payment_coinpayments_title');
        $this->_description = $osC_Language->get('payment_coinpayments_description');
        $this->_method_title = $osC_Language->get('payment_coinpayments_method_title');
        $this->_status = (defined('MODULE_PAYMENT_COINPAYMENTS_STATUS') && (MODULE_PAYMENT_COINPAYMENTS_STATUS == '1') ? true : false);
        $this->_sort_order = (defined('MODULE_PAYMENT_COINPAYMENTS_SORT_ORDER') ? MODULE_PAYMENT_COINPAYMENTS_SORT_ORDER : null);

        $this->check_webhook();
    }

    function check_webhook()
    {
        $coinApiFile = sprintf('%s%s', DIR_FS_CATALOG, 'ext/coinpayments/CoinpaymentsApi.php');
        if (file_exists($coinApiFile)) {

            require_once $coinApiFile;

            if ($this->isInstalled() && defined('MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS') && !empty(MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS)) {
                if (
                    defined('MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID') && !empty(MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID) &&
                    defined('MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET') && !empty(MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET)
                ) {
                    $api = new CoinpaymentsApi();
                    try {
                        $webhooks_list = $api->getWebhooksList(MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID, MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET);
                        if (!empty($webhooks_list)) {

                            $webhooks_urls_list = array();
                            if (!empty($webhooks_list['items'])) {
                                $webhooks_urls_list = array_map(function ($webHook) {
                                    return $webHook['notificationsUrl'];
                                }, $webhooks_list['items']);
                            }

                            if (!in_array($api->getNotificationUrl($this->_code), $webhooks_urls_list)) {
                                $api->createWebHook(MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID, MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET, $api->getNotificationUrl($this->_code));
                            }
                        }
                    } catch (Exception $e) {
                    }

                }

            }
        }
    }

    /**
     * Checks to see if the module has been installed
     *
     * @access public
     * @return boolean
     */

    function isInstalled()
    {
        return (bool)defined('MODULE_PAYMENT_COINPAYMENTS_STATUS');
    }

    /**
     * Installs the module
     *
     * @access public
     * @see osC_Payment_Admin::install()
     */

    function install()
    {
        global $osC_Database;

        parent::install();

        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable CoinPayments.net Payments', 'MODULE_PAYMENT_COINPAYMENTS_STATUS', '-1', 'Do you want to accept CoinPayments.net payments?', '6', '0', 'osc_cfg_set_boolean_value(array(1, -1))', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Client ID', 'MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID', '', 'Your CoinPayments.net Client ID', '6', '0', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Webhooks', 'MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS', '-1', 'Do you want to accept CoinPayments.net payment notification?', '6', '0', 'osc_cfg_set_boolean_value(array(1, -1))', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Client Secret', 'MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET', '', 'Your CoinPayments.net Client Secret', '6', '0', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_COINPAYMENTS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_COINPAYMENTS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '0', 'osc_cfg_use_get_zone_class_title', 'osc_cfg_set_zone_classes_pull_down_menu', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Processing Order Status', 'MODULE_PAYMENT_COINPAYMENTS_PROCESSING_ORDER_STATUS_ID', '" . ORDERS_STATUS_PROCESSING . "', 'When the customer is returned to the Checkout Complete page from CoinPayments, this order status should be used', '6', '0', 'osc_cfg_set_order_statuses_pull_down_menu', 'osc_cfg_use_get_order_status_title', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Completed Payment Order Status', 'MODULE_PAYMENT_COINPAYMENTS_COMPLETED_ORDER_STATUS_ID', '" . ORDERS_STATUS_PAID . "', 'When the CoinPayments payment is successfully made, this order status should be used', '6', '0', 'osc_cfg_set_order_statuses_pull_down_menu', 'osc_cfg_use_get_order_status_title', now())");
        $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Cancelled Payment Order Status', 'MODULE_PAYMENT_COINPAYMENTS_CANCELLED_ORDER_STATUS_ID', '" . ORDERS_STATUS_CANCELLED . "', 'When the CoinPayments payment is cancelled, this order status should be used', '6', '0', 'osc_cfg_set_order_statuses_pull_down_menu', 'osc_cfg_use_get_order_status_title', now())");
    }

    /**
     * Return the configuration parameter keys in an array
     *
     * @access public
     * @return array
     */

    function getKeys()
    {
        if (!isset($this->_keys)) {
            $this->_keys = array('MODULE_PAYMENT_COINPAYMENTS_STATUS',
                'MODULE_PAYMENT_COINPAYMENTS_CLIENT_ID',
                'MODULE_PAYMENT_COINPAYMENTS_WEBHOOKS',
                'MODULE_PAYMENT_COINPAYMENTS_CLIENT_SECRET',
                'MODULE_PAYMENT_COINPAYMENTS_ZONE',
                'MODULE_PAYMENT_COINPAYMENTS_PROCESSING_ORDER_STATUS_ID',
                'MODULE_PAYMENT_COINPAYMENTS_COMPLETED_ORDER_STATUS_ID',
                'MODULE_PAYMENT_COINPAYMENTS_CANCELLED_ORDER_STATUS_ID',
                'MODULE_PAYMENT_COINPAYMENTS_SORT_ORDER');
        }

        return $this->_keys;
    }
}
