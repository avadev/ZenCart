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

function avatax_lookup_tax($order, $products) {
  global $db, $messageStack;

  // Calculate the next expected order id (adapted from code written by Eric Stamper - 01/30/2004 Released under GPL)
  $last_order_id = $db->Execute("select * from " . TABLE_ORDERS . " order by orders_id desc limit 1");
  $new_order_id = $last_order_id->fields['orders_id'];
  $new_order_id = ($new_order_id + 1);

  // Calculate the coupon discount amount for fixed value coupons only
   if ($_SESSION['cc_id']) {
    $coupon = $db->Execute("select * from " . TABLE_COUPONS . " where coupon_id = '" . (int)$_SESSION['cc_id'] . "'");
    if ($coupon->fields['coupon_type'] == 'F') {
      $coupon_value = $coupon->fields['coupon_amount'] * -1;
    }
  }
  
  // Implemention to create transaction at completion of order
  // if($_GET['main_page'] == FILENAME_CHECKOUT_PROCESS) {
  //   $doc_type = 'SalesInvoice';
  // }
  // else {
  //   $doc_type = 'SalesOrder';
  // }
  
  // Implementation to create transaction for all sales tax calculations
  $doc_type = 'SalesInvoice';
  
  $dateTime = new DateTime();

  // Set this to true if completing an order as committed  
  $commit_zc = FALSE;

  // Select taxable address
  if (MODULE_ORDER_TOTAL_AVATAX_ADDRESS == 'Shipping') {
    $street_address = $order->delivery['street_address'];
    $suburb = $order->delivery['suburb'];
    $city = $order->delivery['city'];
    $state = $order->delivery['state'];
    $region = $order->delivery['zone_id'];
    $country = $order->delivery['country']['iso_code_2'];
    $country_code = $order->delivery['country']['id'];
    $zip = $order->delivery['postcode'];
  }
  else {
    $street_address = $order->billing['street_address'];
    $suburb = $order->billing['suburb'];
    $city = $order->billing['city'];
    $state = $order->billing['state'];
    $region = $order->billing['zone_id'];
    $country = $order->billing['country']['iso_code_2'];
    $country_code = $order->delivery['country']['id'];
    $zip = $order->billing['postcode'];
  }

  $state_code = zen_get_zone_code($country_code, $region, $state) ;

  // Exit if delivery state is not an AvaTax state.
  $avatax_states = array();
  $avatax_states_str = MODULE_ORDER_TOTAL_AVATAX_STATES;
  if ($avatax_states_str) {
    $avatax_states = explode(", ", $avatax_states_str);
    if (!in_array($state_code, $avatax_states)) {
      return FALSE;
    }
  }

  $request_body = array(
    'Client' => 'ZenCart-AvalaraInc,1.51',
    'CompanyCode' => MODULE_ORDER_TOTAL_AVATAX_CODE,
    'DetailLevel' => 'Tax',
    'Commit' => $commit_zc,
    'DocType' => $doc_type,
    'DocCode' => ('zc-' . $new_order_id . ''),
    'DocDate' => date_format($dateTime,"Y-m-d"),
    'CustomerCode' => $_SESSION['customer_id'],
    'Addresses' => array(
      // Origin.
      array(
        'AddressCode' => 0,
        'Line1' => MODULE_ORDER_TOTAL_AVATAX_STREET,
        'City' => MODULE_ORDER_TOTAL_AVATAX_CITY,
        'Region' => MODULE_ORDER_TOTAL_AVATAX_STATE,
        'PostalCode' => MODULE_ORDER_TOTAL_AVATAX_ZIPCODE,
      ),
      // Destination.
      array(
        'AddressCode' => 1,
        'Line1' => $street_address,
        'Line2' => $suburb,
        'City' => $city,
        'Region' => $state,
        'Country' => $country,
        'PostalCode' => $zip,
      ),
    ),
  );

  $prod_total = 0;
  $i = 1;
  foreach ($products as $k => $product) {
    $tax_code = '';
    if (MODULE_ORDER_TOTAL_AVATAX_VERSION == "Pro") {
      // $tax_code = look up sales tax code from $product - field for sales tax
    }
    $lines[] = array(
      'LineNo' => $i,
      'ItemCode' => $product['model'],
      'Description' => $product['name'],
      'TaxCode' => $tax_code,
      'Qty' => $product['qty'],
      'Amount' => $product['final_price'] * $product['qty'],
      'Discounted' => 'false',
      'Ref1' => '',
      'Ref2' => '',
      'CustomerUsageType' => '',
      'OriginCode' => 0,
      'DestinationCode' => 1,
      );
    $i++;
  }
  $lines[] = array(
    'LineNo' => $i,
    'ItemCode' => 'Shipping',
    'Description' => 'Public Carrier',
    'TaxCode' => MODULE_ORDER_TOTAL_AVATAX_SHIPCODE,
    'Qty' => 1,
    'Amount' => $order->info['shipping_cost'],
    'Discounted' => 'false',
    'Ref1' => '',
    'Ref2' => '',
    'CustomerUsageType' => '',
    'OriginCode' => 0,
    'DestinationCode' => 1,
  );
  $i++;

  if ($coupon->fields['coupon_type'] == 'F') {
    $lines[] = array(
      'LineNo' => $i,
      'ItemCode' => 'Coupon',
      'Description' => 'Coupon Discount',
      'TaxCode' => 'OD010000',
      'Qty' => 1,
      'Amount' => $coupon_value,
      'Discounted' => 'false',
      'Ref1' => '',
      'Ref2' => '',
      'CustomerUsageType' => '',
      'OriginCode' => 0,
      'DestinationCode' => 1,
    );
  $i++;
  }

  if ($coupon->fields['coupon_type'] == 'P') {
    $lines[] = array(
      'LineNo' => $i,
      'ItemCode' => 'Coupon',
      'Description' => 'Coupon Discount',
      'TaxCode' => 'OD010000',
      'Qty' => 1,
      'Amount' => (int)$coupon->fields['coupon_amount'] * -1 * $prod_total /100,
      'Discounted' => 'false',
      'Ref1' => '',
      'Ref2' => '',
      'CustomerUsageType' => '',
      'OriginCode' => 0,
      'DestinationCode' => 1,
    );
  $i++;
  }

  $request_body['Lines'] = $lines;
  $response = zc_tax_avatax_post('/tax/get', $request_body);

  if (is_array($response) && $response['body']) {
    $result = $response['body'];
    if ($result['ResultCode'] == 'Success') {
      $tax_data = array(
        'tax_amount' => $result['TotalTax'],
        'taxable_amount' => $result['TotalTaxable'],
        'total_amount' => $result['TotalAmount'],
      );
      return $tax_data;
    }
    else {
      foreach ($result['Messages'] as $msg) {
	    $messageStack->add('checkout_payment', 'AvaTax message: ' . $msg['Severity'] . ' - ' . $msg['Source'] . ' - ' . $msg['Summary'] . '', 'error');
      }
      return FALSE;
    }
  }
  else {
    $messageStack->add('checkout_payment', 'AvaTax error: AvaTax did not get a response.', 'error');
    return FALSE;
  }
}

/**
 * Sends HTTP GET request to endpoint.
 * @return array
 *   Returns an associative array containing 'meta' and 'body' elements.
 */
function zc_tax_avatax_get($endpoint, $parameters, $base_url = '', $account = '', $license = '') {
  $querystring = '';
  if (is_array($parameters)) {
    $querystring = http_build_query($parameters);
  }
  $curl_opts = array(
    // Return result instead of echoing.
    CURLOPT_RETURNTRANSFER => TRUE,
    // Stop cURL from verifying the peer's certificate.
    CURLOPT_FOLLOWLOCATION => FALSE,
    // But dont redirect more than 10 times.
    CURLOPT_MAXREDIRS => 10,
    // Abort if network connection takes more than 5 seconds.
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => TRUE,
  );

  list($mode, $version, $account, $license, $base_url) = zc_tax_avatax_get_config($account, $license, $base_url);
  $curl_opts[CURLOPT_HTTPHEADER] = array(
    'Content-Type: text/json',
    'Authorization: Basic ' . base64_encode("$account:$license"),
    'Date: ' . date(DATE_RFC1123, time()),
  );

  $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
  if ($querystring) {
    $url .= '?' . $querystring;
  }
  $curl = curl_init($url);
  foreach ($curl_opts as $opt => $val) {
    curl_setopt($curl, $opt, $val);
  }

  $body = curl_exec($curl);
  if ($body === FALSE) {
	$messageStack->add('checkout_payment', 'AvaTax request failed. This may be an out of date SSL certificates on your server - ' . curl_error($curl) . '', 'error');
  }
  $meta = curl_getinfo($curl);
  curl_close($curl);

  if ($body) {
    $body_parsed = json_decode($body, TRUE);
    return array(
      'body' => $body_parsed,
      'meta' => $meta,
    );
  }
  else {
  return array(
      'body' => '',
      'meta' => $meta,
    );
  }
}

/**
 * Sends HTTP POST request to endpoint.
 * @return array
 *   Returns an associative array containing 'meta' and 'body' elements.
 */
function zc_tax_avatax_post($endpoint, $data, $base_url = '', $account = '', $license = '') {
  $curl_opts = array(
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_FOLLOWLOCATION => FALSE,
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_SSL_VERIFYPEER => TRUE,
  );

  list($mode, $version, $account, $license, $base_url) = zc_tax_avatax_get_config($account, $license, $base_url);

  if (is_array($data)) {
    $data = json_encode($data);
  }
  $curl_opts[CURLOPT_POSTFIELDS] = $data;

  $curl_opts[CURLOPT_HTTPHEADER] = array(
    'Content-Length: ' . strlen($data),
    'Content-Type: text/json',
    'Authorization: Basic ' . base64_encode("$account:$license"),
    'Date: ' . date(DATE_RFC1123, time()),
  );

  $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
  $curl = curl_init($url);
  foreach ($curl_opts as $opt => $val) {
    curl_setopt($curl, $opt, $val);
  }

  $body = curl_exec($curl);
  if ($body === FALSE) {
	$messageStack->add('checkout_payment', 'AvaTax request failed. This may be an out of date SSL certificates on your server - ' . curl_error($curl) . '', 'error');
  }
  $meta = curl_getinfo($curl);
  curl_close($curl);

  if ($body) {
    $body_parsed = json_decode($body, TRUE);
    return array(
      'body' => $body_parsed,
      'meta' => $meta,
    );
  }
  else {
    return array(
      'body' => '',
      'meta' => $meta,
    );
  }
}

/**
 * Returns AvaTax request configurations.
 */
function zc_tax_avatax_get_config($account = '', $license = '', $base_url = '') {
  $mode = MODULE_ORDER_TOTAL_AVATAX_MODE;
  $version = MODULE_ORDER_TOTAL_AVATAX_VERSION;
  if (!$account) {
    $account = MODULE_ORDER_TOTAL_AVATAX_ACCOUNT;
  }
  if (!$license) {
    $license = MODULE_ORDER_TOTAL_AVATAX_LICENSE;
  }
  if (!$base_url) {
    if ($mode == 'Development') {
      $base_url = 'https://development.avalara.net/1.0';
    }
    elseif ($mode == 'Production') {
      $base_url = 'https://rest.avalara.net/1.0';
    }
  }
  return array($mode, $version, $account, $license, $base_url);
}
