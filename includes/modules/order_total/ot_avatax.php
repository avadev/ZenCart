<?php

/**
 * ot_avatax order-total module
 *
 * @package orderTotal
 * @copyright Copyright 2014 Avalara, Inc.
 * @version 1.15.R 2014-01-06 16:00:00Z ajeh $
 */

/**
 * Zen Cart Connector for AvaTax
 *
 */

class ot_avatax {
  var $title, $output;

  function ot_avatax() {
    $this->code = 'ot_avatax';
    $this->title = MODULE_ORDER_TOTAL_AVATAX_TITLE;
    $this->description = MODULE_ORDER_TOTAL_AVATAX_DESCRIPTION;
    $this->enabled = ((MODULE_ORDER_TOTAL_AVATAX_STATUS == 'true') ? true : false);
    $this->sort_order = MODULE_ORDER_TOTAL_AVATAX_SORT_ORDER;

    $this->output = array();
  }

  function process() {

  /**
   * Method used to calculate sales tax using AvaTax and produce the output<br>
   * shown on the checkout pages
   */

    // Calculate Tax
    require_once DIR_WS_MODULES . 'avatax/func.avatax.php';

    global $order, $currencies;

    $tax_data = avatax_lookup_tax($order, $order->products);
	
    $tax_amt = $tax_data['tax_amount'];
    $order->info['tax'] = $tax_amt;

    // Select taxable address
    if (MODULE_ORDER_TOTAL_AVATAX_ADDRESS == 'Shipping') {
      $city = $order->delivery['city'];
    }
    else {
      $city = $order->billing['city'];
    }

    $loccode = $city;
    $namesuf = '';
    if (MODULE_ORDER_TOTAL_AVATAX_LOCATION == 'true') {
      $namesuf = ' (' . $loccode . ') ';
    }
    $taxname = MODULE_ORDER_TOTAL_AVATAX_DESCRIPTION . $namesuf . '';

    $order->info['tax_groups'] = array ($taxname => $tax_amt);

    // Produce Sales Tax output for the checkout page
    reset($order->info['tax_groups']);
    $taxDescription = '';
    $taxValue = 0;

    while (list($key, $value) = each($order->info['tax_groups'])) {
      if ($value > 0 || ($value == 0 && STORE_TAX_DISPLAY_STATUS == 1)) {
        $taxDescription .= ((is_numeric($key) && $key == 0) ? TEXT_UNKNOWN_TAX_RATE :  $key) . ' + ';
        $taxValue += $value;

        $order->info['total'] += $taxValue;

        $this->output[] = array(
          'title' => substr($taxDescription, 0 , strlen($taxDescription)-3) . ':' ,
          'text' => $currencies->format($taxValue, true, $order->info['currency'], $order->info['currency_value']) ,
          'value' => $taxValue);
      }
    }
  }

  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_AVATAX_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }

  function keys() {
    return array('MODULE_ORDER_TOTAL_AVATAX_STATUS', 'MODULE_ORDER_TOTAL_AVATAX_SORT_ORDER', 'MODULE_ORDER_TOTAL_AVATAX_VERSION', 'MODULE_ORDER_TOTAL_AVATAX_MODE', 'MODULE_ORDER_TOTAL_AVATAX_CODE', 'MODULE_ORDER_TOTAL_AVATAX_STATES', 'MODULE_ORDER_TOTAL_AVATAX_ACCOUNT', 'MODULE_ORDER_TOTAL_AVATAX_LICENSE', 'MODULE_ORDER_TOTAL_AVATAX_ADDRESS', 'MODULE_ORDER_TOTAL_AVATAX_SHIPCODE', 'MODULE_ORDER_TOTAL_AVATAX_DESCRIPTION', 'MODULE_ORDER_TOTAL_AVATAX_LOCATION', 'MODULE_ORDER_TOTAL_AVATAX_STREET', 'MODULE_ORDER_TOTAL_AVATAX_CITY', 'MODULE_ORDER_TOTAL_AVATAX_COUNTY', 'MODULE_ORDER_TOTAL_AVATAX_STATE', 'MODULE_ORDER_TOTAL_AVATAX_ZIPCODE');
  }

  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display AvaTax', 'MODULE_ORDER_TOTAL_AVATAX_STATUS', 'true', 'Do you want this module to display?', '6', '1','zen_cfg_select_option(array(\'true\', \'false\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_AVATAX_SORT_ORDER', '970', 'Sort order of display', '6', '2', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('AvaTax Version', 'MODULE_ORDER_TOTAL_AVATAX_VERSION', 'Basic', 'Select Basic or Pro - refer AvaTax License', '6', '3','zen_cfg_select_option(array(\'Basic\', \'Pro\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('AvaTax Mode', 'MODULE_ORDER_TOTAL_AVATAX_MODE', 'Development', 'Only select Production if you have completed the GO LIVE process with Avalara,Inc.', '6', '4','zen_cfg_select_option(array(\'Development\', \'Production\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('AvaTax Company Code', 'MODULE_ORDER_TOTAL_AVATAX_CODE', '', 'Enter the Company Code', '6', '5', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('AvaTax Selected States', 'MODULE_ORDER_TOTAL_AVATAX_STATES', '', 'Leave blank for AvaTax in ALL states (usual) - else list States - e.g. CA, NV, WA', '6', '6', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('AvaTax Account Number', 'MODULE_ORDER_TOTAL_AVATAX_ACCOUNT', '', 'The AvaTax Account Number', '6', '7', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('AvaTax License Key', 'MODULE_ORDER_TOTAL_AVATAX_LICENSE', '', 'The AvaTax License Key', '6', '8', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Taxable Address', 'MODULE_ORDER_TOTAL_AVATAX_ADDRESS', 'Shipping', 'Select Destination Address to use for Sales Tax', '6', '9','zen_cfg_select_option(array(\'Shipping\', \'Billing\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Tax Code', 'MODULE_ORDER_TOTAL_AVATAX_SHIPCODE', 'FR020100', 'Refer to AvaTax training for selection of the correct Shipping Tax Code', '6', '10', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sales Tax Description', 'MODULE_ORDER_TOTAL_AVATAX_DESCRIPTION', 'Sales Tax', 'The Sales Tax description to be displayed', '6', '11', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Show Location Code', 'MODULE_ORDER_TOTAL_AVATAX_LOCATION', 'true', 'Include a City name in your Sales Tax description', '6', '12', 'zen_cfg_select_option(array(\'true\', \'false\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Street Address', 'MODULE_ORDER_TOTAL_AVATAX_STREET', '', 'The Street your business is located in', '6', '13', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Primary City', 'MODULE_ORDER_TOTAL_AVATAX_CITY', '', 'The City your business is located in. e.g. Mill Valley - NB - Must be a Valid City', '6', '14', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Primary County', 'MODULE_ORDER_TOTAL_AVATAX_COUNTY', '', 'The County your business is located in. e.g. Marin - NB - Must be a Valid County', '6', '15', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Primary State', 'MODULE_ORDER_TOTAL_AVATAX_STATE', '', 'The State your business is located in. e.g. CA for California - NB - Must be a Valid 2 character code', '6', '16', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Primary Zip Code', 'MODULE_ORDER_TOTAL_AVATAX_ZIPCODE', '', 'The Zip code your business is located in. e.g. 94941 - NB - Must be a Valid 5 digit zip', '6', '17', now())");
  }

  function remove() {
    global $db;
    $keys = '';
    $keys_array = $this->keys();
    $keys_size = sizeof($keys_array);
    for ($i=0; $i<$keys_size; $i++) {
      $keys .= "'" . $keys_array[$i] . "',";
    }
    $keys = substr($keys, 0, -1);
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")");
  }
}
