<?php

/**
 * ot_avatax order-total module
 *
 * @package orderTotal
 * @copyright Copyright 2014 Avalara, Inc.
 * @version 1.15.R 2014-01-27 16:00:00Z ajeh $
 */

/**
 * Zen Cart Connector for AvaTax
 *
 */

function zc_avatax_commit_tax($order, $products, $oID) {
  global $db, $messageStack;

  $doc_type = 'SalesInvoice';
  
  $dateTime = new DateTime();

  // Set this to true if completing an order as committed  
  $commit_zc = TRUE;

  // Select taxable address
  if (MODULE_ORDER_TOTAL_AVATAX_ADDRESS == 'Shipping') {
    $street_address = $order->delivery['street_address'];
    $suburb = $order->delivery['suburb'];
    $city = $order->delivery['city'];
    $state = $order->delivery['state'];
    $country = $order->delivery['country'];
    $zip = $order->delivery['postcode'];
  }
  else {
    $street_address = $order->billing['street_address'];
    $suburb = $order->billing['suburb'];
    $city = $order->billing['city'];
    $state = $order->billing['state'];
    $country = $order->billing['country'];
    $zip = $order->billing['postcode'];
  }

  $request_body = array(
    'Client' => 'ZenCart-AvalaraInc,1.51',
    'CompanyCode' => MODULE_ORDER_TOTAL_AVATAX_CODE,
    'DetailLevel' => 'Tax',
    'Commit' => $commit_zc,
    'DocType' => $doc_type,
    'DocCode' => ('zc-' . $oID . ''),
    'DocDate' => date_format($dateTime,"Y-m-d"),
    'CustomerCode' => $order->customer['id'],
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

  foreach ($order->totals as $key => $item) {
    if (in_array($item['class'], array('ot_shipping'))) {
      $lines[] = array(
        'LineNo' => $i,
        'ItemCode' => 'Shipping',
        'Description' => 'Public Carrier',
        'TaxCode' => MODULE_ORDER_TOTAL_AVATAX_SHIPCODE,
        'Qty' => 1,
        'Amount' => $item['value'],
        'Discounted' => 'false',
        'Ref1' => '',
        'Ref2' => '',
        'CustomerUsageType' => '',
        'OriginCode' => 0,
        'DestinationCode' => 1,
      );
      $i++;
    }
	elseif (in_array($item['class'], array('ot_coupon'))) {
      $lines[] = array(
        'LineNo' => $i,
        'ItemCode' => 'Coupon',
        'Description' => 'Coupon Discount',
        'TaxCode' => 'OD010000',
        'Qty' => 1,
        'Amount' => $item['value']*-1,
        'Discounted' => 'false',
        'Ref1' => '',
        'Ref2' => '',
        'CustomerUsageType' => '',
        'OriginCode' => 0,
        'DestinationCode' => 1,
      );
      $i++;
    }
  }

  $request_body['Lines'] = $lines;
  $response = zc_tax_avatax_post('/tax/get', $request_body);

  if (is_array($response) && $response['body']) {
    $result = $response['body'];
    if ($result['ResultCode'] == 'Success') {
      $messageStack->add_session('AvaTax message: Transaction committed', 'success');
      return;
    }
    else {
      foreach ($result['Messages'] as $msg) {
	    $messageStack->add_session('AvaTax message: ' . $msg['Severity'] . ' - ' . $msg['Source'] . ' - ' . $msg['Summary'] . '', 'error');
      }
      return;
    }
  }
  else {
    $messageStack->add('AvaTax error: AvaTax did not get a response.', 'error');
    return;
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
	$messageStack->add('AvaTax request failed. This may be an out of date SSL certificates on your server - ' . curl_error($curl) . '', 'error');
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
	$messageStack->add('AvaTax request failed. This may be an out of date SSL certificates on your server - ' . curl_error($curl) . '', 'error');
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

/**
 * COMMIT AvaTax transaction for $oID.
 */
function zc_avatax_commit_transaction($oID) {
  _zc_avatax_update($oID, 'commit');
}

/**
 * DELETE AvaTax transaction for $oID.
 */
function zc_avatax_cancel_transaction($oID) {
  _zc_avatax_update($oID, 'cancel');
}

/**
 * Send Commit/Cancel operation to AvaTax.
 */
function _zc_avatax_update($oID, $type = 'commit') {
  require_once(DIR_WS_CLASSES . 'order.php');
  global $db, $messageStack;

  // Get Company code and Company Use Mode.
  $company_code = MODULE_ORDER_TOTAL_AVATAX_CODE;
  $use_mode = MODULE_ORDER_TOTAL_AVATAX_MODE;
  $product_version = MODULE_ORDER_TOTAL_AVATAX_VERSION;
  $account_no = MODULE_ORDER_TOTAL_AVATAX_ACCOUNT;
  $license_key = MODULE_ORDER_TOTAL_AVATAX_LICENSE;

  if ($use_mode == UC_AVATAX_DEVELOPMENT_MODE) {
    $base_url = 'https://development.avalara.net/1.0';
  }
  elseif ($use_mode == UC_AVATAX_PRODUCTION_MODE) {
    $base_url = 'https://rest.avalara.net/1.0';
  }

  switch ($type) {
    case 'commit':
      // load the original order
      $order = new order($oID);
	  zc_avatax_commit_tax($order, $order->products, $oID);
      break;

    case 'cancel':
 	  $doc_code_prefix = 'zc';
      $body = array(
        'Client' => 'ZenCart-AvalaraInc,1.51',
        'DocCode' => $doc_code_prefix . '-' . $oID,
        'CompanyCode' => $company_code,
        'DocType' => 'SalesInvoice',
        'CancelCode' => 'DocVoided',
      );
      $response = zc_tax_avatax_post('/tax/cancel', $body);

      if (is_array($response) && $response['body']) {
        $result = $response['body'];
        if (isset($result['CancelTaxResult']['ResultCode']) && $result['CancelTaxResult']['ResultCode'] != 'Success') {
          foreach ($result['CancelTaxResult']['Messages'] as $msg) {
            $messageStack->add_session('AvaTax message: '. $msg['Severity'] . ' - ' . $msg['Source'] . ' - ' . $msg['Summary'] . '', 'error');
          }
          return;  
        }
        else {
          $messageStack->add_session('AvaTax message: AvaTax transaction voided.', 'success');
          return;  
        }
      }

      if (!$response) {
        $messageStack->add_session('AvaTax error: AvaTax did not get a response.', 'error');
      }
      break;
  }
}
