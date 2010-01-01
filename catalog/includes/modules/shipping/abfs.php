<?php
/*
  $Id: abfs.php,v 0.7 2009/05/27 Brian Burton brian@dynamoeffects.com Exp $
*/

  class abfs {
    var $code, $title, $description, $icon, $enabled;

    function abfs() {
      global $order;

      $this->code = 'abfs';
      $this->title = MODULE_SHIPPING_ABFS_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_ABFS_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_ABFS_SORT_ORDER;
      $this->icon = '';
      $this->tax_class = MODULE_SHIPPING_ABFS_TAX_CLASS;
     

      if (MODULE_SHIPPING_ABFS_STATUS == 'True') {
        $this->enabled = true;
      } else {
        $this->enabled = false;
      }

      if ( ($this->enabled == true) && ((int)MODULE_SHIPPING_ABFS_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("SELECT zone_id " . 
																		"FROM " . TABLE_ZONES_TO_GEO_ZONES . " " . 
																		"WHERE geo_zone_id = '" . MODULE_SHIPPING_ABFS_ZONE . "' " . 
																		"  AND zone_country_id = '" . $order->delivery['country']['id'] . "' " . 
																		"ORDER BY zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function quote($method = '') {
      global $order, $cart, $customer_id, $currencies;
      
      $error_msg = '';
      $sml_error_msg = '';
      
      $dest_zip = $order->delivery['postcode'];
      $dest_country = $order->delivery['country']['iso_code_2'];
      
      if ($dest_country == 'US') {
          $dest_zip = substr($dest_zip, 0, 5);
      } elseif ($dest_country == 'CA') {
          $dest_zip = substr($dest_zip, 0, 6);
      } else {
        $error_msg = '<br>' . MODULE_SHIPPING_ABFS_TEXT_ERROR_DESCRIPTION;
      }
	  
      if ($error_msg == '') {
        //Get the shopping cart contents
        $products = $cart->get_products('F');
        $n = sizeof($products);

        $today = getdate();
        $days_in_advance = 7;
        switch ($today['weekday']) {
          case 'Sunday':
            $days_in_advance = 5;
            break;
          case 'Saturday':
            $days_in_advance = 6;
            break;
        }
        $ship_date = array();
        $ship_date['month'] = date("n", mktime(0, 0, 0, date("n"), date("j")+$days_in_advance, date("Y")));
        $ship_date['day'] = date("j", mktime(0, 0, 0, date("n"), date("j")+$days_in_advance, date("Y")));
        $ship_date['year'] = date("Y", mktime(0, 0, 0, date("n"), date("j")+$days_in_advance, date("Y")));
        
        $ship_country = 'US';
        
        $country_query = tep_db_query("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)MODULE_SHIPPING_ABFS_SHIP_COUNTRY . " LIMIT 1");
        if (tep_db_num_rows($country_query)) {
          $country = tep_db_fetch_array($country_query);
          $ship_country = $country['countries_iso_code_2'];
        }
        
        $state_query = tep_db_query("SELECT zone_code FROM zones WHERE zone_id = '" . (int)$order->delivery['zone_id'] . "'");
        $state_result = tep_db_fetch_array($state_query);
        $delivery_state = $state_result['zone_code'];
        
        $query_types = array('Shipped');

        $total_shipping_price = array();
        foreach($query_types as $qType) {
          $x = 1;
          $abfs_queries = array();
          $post_string = '';


          $post_string  = 'DL=2';
          $post_string .= '&AcctNum=' . urlencode(MODULE_SHIPPING_ABFS_USERNAME);
          $post_string .= '&Password=' . urlencode(MODULE_SHIPPING_ABFS_PASSWORD);
          $post_string .= '&ShipCity=' . strtoupper(urlencode(MODULE_SHIPPING_ABFS_SHIP_CITY));
          $post_string .= '&ShipState=' . strtoupper(MODULE_SHIPPING_ABFS_SHIP_STATE);
          $post_string .= '&ShipZip=' . strtoupper(urlencode(MODULE_SHIPPING_ABFS_SHIP_ZIP));
          $post_string .= '&ShipCountry=' . strtoupper($ship_country);
          $post_string .= '&ShipPay=Y'; //Shipper pays
          $post_string .= '&ShipAff=Y'; //This store is responsible for shipping
          $post_string .= '&Acc_PALLET=Y'; //Palletized
          $post_string .= '&ConsCity=' . strtoupper(urlencode($order->delivery['city']));
          $post_string .= '&ConsState=' . $delivery_state;
          $post_string .= '&ConsZip=' . urlencode($order->delivery['postcode']);
          $post_string .= '&ConsCountry=' . $order->delivery['country']['iso_code_2'];
          for ($i=0; $i<$n; $i++) {
            $prod_query = tep_db_query("SELECT products_abfs_length, products_abfs_width, products_abfs_height, products_abfs_class, products_abfs_nmfc FROM " . TABLE_PRODUCTS . " WHERE products_id = '".$products[$i]['id']."'");
            $prod_info = tep_db_fetch_array($prod_query);
            $post_string .= '&Wgt' . ($i + 1) . '=' . ($products[$i]['quantity'] * (int)$products[$i]['weight']);
            $post_string .= '&Class' . ($i + 1) . '=' . $prod_info['products_abfs_class'];
            if (trim($prod_info['products_abfs_nmfc']) != '') {
              $post_string .= '&nmfcitem1=' . $prod_info['products_abfs_nmfc'] . '&nmfcsub1=00';
            }
          }
          $post_string .= '&ShipMonth=' . $ship_date['month'];
          $post_string .= '&ShipDay=' . $ship_date['day'];
          $post_string .= '&ShipYear=' . $ship_date['year'];
          
          //Optional fields, so they're commented out for now
          //$post_string .= '&FrtLng=' . $prod_info['products_abfs_length'];
          //$post_string .= '&FrtWdth=' . $prod_info['products_abfs_width'];
          //$post_string .= '&FrtHght=' . $prod_info['products_abfs_height'];
          $post_string .= '&Acc_RDEL=Y'; //Residential delivery
          
          $abfs_queries[] = $post_string;
          $post_string = '';


          $total_shipping_price[$qType] = array();
          $total_shipping_price[$qType]['rate'] = 0;
          $total_shipping_price[$qType]['shipmentID'] = '';
          foreach ($abfs_queries as $abfs) {
            $ship_price = $this->queryRates($abfs);

            if ($ship_price[0] == 'error') { 
              if ($error_msg == '') $error_msg .= '<br>' . MODULE_SHIPPING_ABFS_TEXT_ERROR_DESCRIPTION . '<br>' . $ship_price[1];
              break;
            } else {
              $total_shipping_price[$qType]['rate'] += $ship_price['rate'];
              $total_shipping_price[$qType]['shipmentID'] = $ship_price['shipmentID'];
            }
          }
          //Add price modifier
          if (MODULE_SHIPPING_ABFS_PRICE_MODIFIER > 0) {
            $total_shipping_price[$qType]['rate'] = $total_shipping_price[$qType]['rate'] * MODULE_SHIPPING_ABFS_PRICE_MODIFIER;
          }
          
          //Add handling charges
          $total_shipping_price[$qType]['rate'] += MODULE_SHIPPING_ABFS_HANDLING * $n;
        }
      }
      if (!$error_msg) {
        $shipping_options = array();
        foreach ($query_types as $qType) {
          $shipping_options[] = array('id' => $qType . '_' . $total_shipping_price[$qType]['shipmentID'],
                                      'title' => MODULE_SHIPPING_ABFS_TEXT_WAY . ' ' . $qType . ' - <i>Quote ID:' . $total_shipping_price[$qType]['shipmentID'] . '</i>',
                                      'cost' => $total_shipping_price[$qType]['rate'],
                                      'tfrc_quote_id' => $total_shipping_price[$qType]['shipmentID']);
        }
        $this->quotes = array('id' => $this->code,
                              'module' => MODULE_SHIPPING_ABFS_TEXT_DESCRIPTION,
                              'methods' => $shipping_options);            
  
        if ($this->tax_class > 0) {
          $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
  
        if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
      } else {
       
        $this->quotes = array('module' => $this->title,
                              'error' => $error_msg);
      }
      return $this->quotes;
    }
    
    function queryRates($data_string) {
      $url = "http://www.abfs.com/xml/aquotexml.asp";
      $url_parsed = parse_url($url);
      
      $host = $url_parsed["host"];
      $path = $url_parsed["path"] . '?' . $data_string;

      $head = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";

      if ($fp = @fsockopen($host, 80)) {
        fputs($fp, $head);
        while(!feof($fp)) {
           $result .= fgets($fp, 128);
        }
        fclose($fp);
   
        if (strpos($result, '<CHARGE>') !== false) {
          return array('rate' => preg_replace('/[^0-9\.]/', '', $this->get_string($result, '<CHARGE>', '</CHARGE>')), 'shipmentID' => $this->get_string($result, '<QUOTEID>', '</QUOTEID>'));
        }
      }
      $error = '';
      
      if ($this->get_string($result, '<NUMERRORS>', '</NUMERRORS>') > 0) {
        $error = $this->get_string($result, '<ERRORMESSAGE>', '</ERRORMESSAGE>') . ' (' . $this->get_string($result, '<ERRORCODE>', '</ERRORCODE>') . ')';
      } else {
        $error = 'Unknown Server Error';
      }
      
      if ($error != '' && MODULE_SHIPPING_ABFS_DEBUG == 'True') {
        $error .= $url . '?' . $data_string;
      }
      return array('error', $error);
    }
    
    function get_string($string, $start, $end) {
      $string = " " . $string;
      $ini = strpos($string, $start);
      if ($ini == 0) return "";
      $ini += strlen($start);   
      $len = strpos($string, $end, $ini) - $ini;
      return substr($string, $ini, $len);
    }
  
    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_ABFS_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }
    
    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable ABFS Shipping Module', 'MODULE_SHIPPING_ABFS_STATUS', 'True', 'Do you want to offer ABFS shipping?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable ABFS Debug Mode', 'MODULE_SHIPPING_ABFS_DEBUG', 'False', 'Enable debug mode?  This will dump the data string to the user\'s screen when an error occurs.  Should not be turned on unless there are problems.', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Username', 'MODULE_SHIPPING_ABFS_USERNAME', '', 'Enter your ABFS API Username', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_SHIPPING_ABFS_PASSWORD', '', 'Enter your ABFS API Password', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Location: Zip Code', 'MODULE_SHIPPING_ABFS_SHIP_ZIP', '', 'What zip code will you be shipping from?', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Location: City', 'MODULE_SHIPPING_ABFS_SHIP_CITY', '', 'What city will you be shipping from?', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Location: State', 'MODULE_SHIPPING_ABFS_SHIP_STATE', '', 'What state will you be shipping from? (2 letter abbreviation)', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Location: Country', 'MODULE_SHIPPING_ABFS_SHIP_COUNTRY', '223', 'What country will you be shipping from?', '6', '0', 'tep_get_country_name', 'tep_draw_pull_down_menu(\'configuration[MODULE_SHIPPING_ABFS_SHIP_COUNTRY]\',tep_get_countries(),', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Price Modifier', 'MODULE_SHIPPING_ABFS_PRICE_MODIFIER', '1', 'This number will be multiplied by the total shipping rate.  If you\'d like to increase or decrease the price returned, modify this field.  Setting it to 1 will display the price as returned by ABFS.', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee', 'MODULE_SHIPPING_ABFS_HANDLING', '0', 'Handling fee for this shipping method (per item).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_ABFS_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_ABFS_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_ABFS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      
      $col_query = tep_db_query("SHOW COLUMNS FROM " . TABLE_PRODUCTS);
      $found = array('products_abfs_class' => array('type' => "VARCHAR( 6 ) DEFAULT '50' NOT NULL"),
                     'products_abfs_length' => array('type' => "INT DEFAULT '0' NOT NULL"),
                     'products_abfs_width' => array('type' => "INT DEFAULT '0' NOT NULL"),
                     'products_abfs_height' => array('type' => "INT DEFAULT '0' NOT NULL"),
                     'products_abfs_nmfc' => array('type' => "VARCHAR(32) NULL"));
      
      while ($col = tep_db_fetch_array($col_query)) {
        $columns[] = $col['Field'];
      }

      foreach ($found as $col => $info) {
        if (!in_array($col, $columns)) {
          tep_db_query("ALTER TABLE " . TABLE_PRODUCTS . " ADD `" . $col . "` " . $info['type']);
        }
      }
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_SHIPPING_ABFS_STATUS', 'MODULE_SHIPPING_ABFS_DEBUG', 'MODULE_SHIPPING_ABFS_USERNAME', 'MODULE_SHIPPING_ABFS_PASSWORD', 'MODULE_SHIPPING_ABFS_SHIP_ZIP', 'MODULE_SHIPPING_ABFS_SHIP_CITY', 'MODULE_SHIPPING_ABFS_SHIP_STATE', 'MODULE_SHIPPING_ABFS_SHIP_COUNTRY', 'MODULE_SHIPPING_ABFS_PRICE_MODIFIER', 'MODULE_SHIPPING_ABFS_HANDLING', 'MODULE_SHIPPING_ABFS_TAX_CLASS', 'MODULE_SHIPPING_ABFS_ZONE', 'MODULE_SHIPPING_ABFS_SORT_ORDER');
    }
  }
?>