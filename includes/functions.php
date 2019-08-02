<?php

$types = array(
1 => 'gold',
2 => 'wood',
3 => 'stone',
4 => 'coal',
5 => 'clay',
6 => 'ore',
7 => 'stoneblock',
8 => 'stoneplate',
9 => 'beam',
10 => 'board',
11 => 'woodgirder',
12 => 'brick',
13 => 'tile',
14 => 'steelblock',
15 => 'steelplate',
16 => 'nail',
17 => 'shovel',
18 => 'spade',
19 => 'pickaxe',
20 => 'hammer',
21 => 'sledge',
22 => 'axe',
23 => 'saw',
24 => 'goldpan',
25 => 'cart',
26 => 'cartbody',
27 => 'cartwheel',
28 => 'wagon',
29 => 'wagonbody',
30 => 'wagonwheel'
);

function getActivityDescription($activity, $amount) {
    $activities = array(
        'doorder' => 'Completed an Order',
        'mkpurchase_buy' => 'Bought Stuff',
        'mkpurchase_sell' => 'Sold Stuff',
        'rentloc' => 'Paid Rent',
        'deposit' => 'Deposited PGL',
        'withdraw' => 'Withdrew PGL',
        'mkmineord' => 'Created a Mining Order',
        'rmorder' => 'Removed an Order',
        'mkbuyord' => 'Created a Buy Order',
        'mkpurchord' => 'Created a Purchase Order',
        'mkbuildord' => 'Created a Build Order',
        'mktransord' =>  'Created a Transfer Order',
        'mvstorgold' => 'Converted Mined Gold',
        'mkmakeord' => 'Created a Make Order',
    );
    $activity_key = $activity;
    if ($activity == 'mkpurchase') {
        if ($amount > 0) {
            $activity_key .= '_sell';
        } else {
            $activity_key .= '_buy';
        }
    }
    if (array_key_exists($activity_key, $activities)) {
        return $activities[$activity_key];
    }
    return $activity;
}

function formatAmount($amount, $type = 0, $type_text = '') {
    global $types;
    $formatted_amount = $amount;
    if ($type_text) {
        $type = array_search($type_text, $types);
    }
    if ($type >= 2 && $type <= 6) {
        $formatted_amount = $amount / 1000;
    }
    return $formatted_amount;
}

function block_time_compare($a, $b)
{
    $t1 = strtotime($a['block_time']);
    $t2 = strtotime($b['block_time']);
    return $t1 - $t2;
}

function pdump($var) {
    print "<textarea rows=\"10\" cols=\"100\">";
    var_dump($var);
    print "</textarea>";
}

function console_log($string) {
    print "<script>console.log(\"" . $string . "\");</script>";
}

// method should be "GET", "PUT", etc..
function request($method, $url, $header, $params) {
    $opts = array(
        'http' => array(
            'method' => $method,
        ),
    );

    // serialize the params if there are any
    if (!empty($params)) {
        if ($method == "GET") {
            $params_array = array();
            foreach ($params as $key => $value) {
                $params_array[] = "$key=".urlencode($value);
            }
            $url .= '?'.implode('&', $params_array);            
        }
        if ($method == "POST") {
            if (is_array($params)) {
                $data = json_encode($params);
            } else {
                $data = $params;
            }
            $opts['http']['content'] = $data;
            $header['Content-Length'] = strlen($data);
        }
    }

    // serialize the header if needed
    if (!empty($header)) {
        $header_str = '';
        foreach ($header as $key => $value) {
            $header_str .= "$key: $value\r\n";
        }
        $header_str .= "\r\n";
        $opts['http']['header'] = $header_str;
    }
    //var_dump($opts);
    //var_dump($url);
    $context = stream_context_create($opts);
    $data = @file_get_contents($url, false, $context);
    return $data;
}

function getDateTimeFromBlockTime($block_time_string) {
    $block_time_string_format = "Y-m-d H:i:s";
    $block_time_string = str_replace("T", " ", $block_time_string);
    $block_time_string = str_replace(".5", "", $block_time_string);
    $block_time_string = str_replace("Z", "", $block_time_string);
    $BlockTime = DateTime::createFromFormat($block_time_string_format, $block_time_string);
    return $BlockTime;
}

function checkPayment($account, $required_amount, $owner, $token) {
    $paid = false;
    $payment_date = '';
    $payment_amount = 0;
    $q = 'account:eosio.token action:transfer data.to:' . $owner . ' data.from:' . $account . ' auth:' . $account;
    $json = searchGraphQL($q, '', $token, '', false);
    if ($json) {
        $data = json_decode($json, true);
        $results = $data['data']['searchTransactionsBackward']['results'];
        if ($results) {
            $result = $results[0];
            $payment_date = $result['trace']['block']['timestamp'];
            foreach ($result['trace']['matchingActions'] as $matchingAction) {
                $payment_with_symbol = $matchingAction['data']['quantity'];
                if (substr($payment_with_symbol, -4) == " EOS") {
                    $payment_amount = str_replace(" EOS", "", $payment_with_symbol);
                    if ($payment_amount >= $required_amount) {
                        $now = new DateTime();
                        $paymentTime = getDateTimeFromBlockTime($payment_date);
                        $interval = $now->diff($paymentTime);
                        if ($interval->days <= 30) {
                            $paid = true;
                        }
                    }
                }
            }
        }
    }
    $result = array('paid' => $paid, 'payment_amount' => $payment_amount, 'payment_date' => $payment_date);
    return $result;
}

function getGraphQLResults($q, $table, $token) {
    $json = searchGraphQLCached($q, $table, $token);
    $data = json_decode($json,true);
    $results = $data['data']['searchTransactionsForward']['results'];
    if (count($results)) {
        $cursor = $results[count($results)-1]['cursor'];
        $keep_fetching = true;
        while($keep_fetching) {
            $more_json = searchGraphQLCached($q, $table, $token, $cursor);
            $more_data = json_decode($more_json,true);
            $more_results = $more_data['data']['searchTransactionsForward']['results'];
            $keep_fetching = false;
            if (count($more_results)) {
                $keep_fetching = true;
                $results = array_merge($results,$more_results);
                $cursor = $more_results[count($more_results)-1]['cursor'];
            }
        }
    }
    return $results;
}

function searchGraphQLCached($q, $table, $token, $cursor = '') {
    console_log("searchGraphQLCached: Checking Cache... " . $q . " " . $table . " " . $cursor);
    $filename = __DIR__ . '/../cache/_' . str_replace(array(' ','/'),'-',$q) . '_' . $table . '_' . $cursor . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
        console_log("searchGraphQLCached: Cache Hit!");
        return $json;
    }
    console_log("searchGraphQLCached: Searching...");
    $json = searchGraphQL($q, $table, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function searchGraphQL($q, $table, $token, $cursor = '', $forward = true) {
    $search_type = 'Forward';
    if (!$forward) {
        $search_type = 'Backward';
    }
    $url = 'https://mainnet.eos.dfuse.io/graphql';
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $cursor_string = '';
    if ($cursor) {
        $cursor_string = ', cursor: \"' . $cursor . '\"';
    }
    $query = '{"query": "{ searchTransactions' . $search_type . '(query: \"' . $q . ' notif:false\"' . $cursor_string . ') {
        results {
          cursor
          trace {
            id
            block {
              timestamp
            }
            matchingActions {
              account
              name
              data
              authorization {
                actor
              }
              dbOps(table:\"' . $table . '\") {
                oldJSON {
                  object
                }
                newJSON {
                  object
                }
              }
            }
          }
        }
      }
    }"
}';
    $query = str_replace("\n", "", $query);
    $json = request("POST", $url, $header, $query);
    return $json;
}

function getTableDataCached($table, $token, $cursor="") {
    $filename = __DIR__ . '/../cache/_table_data_' . $table . '_' . $cursor . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
        return $json;
    }
    $json = getTableData($table, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function getTableData($table, $token, $cursor="") {
    console_log("...Updating Cache: " . $table . " " . $cursor . "...");
    $url = 'https://mainnet.eos.dfuse.io/v0/state/table';
    $params = array('account' => 'prospectorsc', 'scope' => 'prospectorsc', 'table' => $table, 'json' => true);
    if ($cursor != "") {
        $params['cursor'] = $cursor;
    }
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("GET", $url, $header, $params);
    return $json;
}

function groupMarketTransactions($market_transactions) {
    $grouped_market_transactions = array();
    foreach ($market_transactions as $market_transaction) {
        if (!array_key_exists($market_transaction['type'], $grouped_market_transactions)) {
            $grouped_market_transactions[$market_transaction['type']] = array();
        }
        $grouped_market_transactions[$market_transaction['type']][] = $market_transaction;
    }
    return $grouped_market_transactions;
}
function getMarketTransactionDetails($grouped_market_transactions) {
    $market_transaction_details = array();
    foreach ($grouped_market_transactions as $type => $market_transactions) {
        $number_of_transactions = count($market_transactions);
        $total_amount_of_transactions = array_sum(array_column($market_transactions, 'total'));
        $amount_transacted = array_sum(array_column($market_transactions, 'amount'));
        $average_amount_transacted = $amount_transacted/$number_of_transactions;
        $average_transaction_price = array_sum(array_column($market_transactions, 'price'))/$number_of_transactions;
        $market_transaction_details[$type] = array(
            'number_of_transactions' => $number_of_transactions,
            'total_amount_of_transactions' => $total_amount_of_transactions,
            'amount_transacted' => $amount_transacted,
            'average_amount_transacted' => $average_amount_transacted,
            'average_transaction_price' => $average_transaction_price
        );
    }
    return $market_transaction_details;
}

function getAccountBalanceChanges($account, $token) {
    $prospectors_account = 'prospectorsc';
    $account_actions = array();
    $q = 'account:prospectorsg action:transfer notif:false data.to:prospectorsc data.from:' . $account . ' auth:' . $account;
    $deposits = getGraphQLResults($q, "", $token);
    foreach ($deposits as $result) {
        foreach ($result['trace']['matchingActions'] as $matchingAction) {
            $amount = (str_replace(' PGL', '', $matchingAction['data']['quantity']) * 1000);
            $account_action = array('block_time' => $result['trace']['block']['timestamp'], 'id' => $result['trace']["id"]);
            $account_action['activity'] = 'deposit';
            $account_action['amount'] = $amount;
            $account_action['details'] = '';
            $account_action['auth'] = '';
            $account_actions[] = $account_action;
        }
    }

    $q = 'account:prospectorsc notif:false db.key:account/prospectorsc/' . $account;
    $everything = getGraphQLResults($q, "account", $token);
    foreach ($everything as $result) {
        foreach ($result['trace']['matchingActions'] as $matchingAction) {
            foreach ($matchingAction['dbOps'] as $dbOp) {
                $old_balance = 0;
                $new_balance = 0;
                if (isset($dbOp['oldJSON']['object']['name']) && $dbOp['oldJSON']['object']['name'] == $account) {
                    $old_balance = $dbOp['oldJSON']['object']['balance'];
                    $new_balance = $dbOp['newJSON']['object']['balance'];
                }
                $change = abs($new_balance - $old_balance);
                if ($new_balance < $old_balance) {
                    $change = (0 - $change);
                }
                $amount = $change;
                $account_action = array('block_time' => $result['trace']['block']['timestamp'], 'id' => $result['trace']["id"]);
                $account_action['activity'] = $matchingAction['name'];
                $account_action['amount'] = $amount;
                $account_action['auth'] = $matchingAction['authorization'][0]['actor']; // assume simple permissions here
                $account_action['details'] = $matchingAction['data'];
                if ($change != 0) {
                    $account_actions[] = $account_action;
                }
            }
        }
    }
    usort($account_actions, 'block_time_compare');
    return $account_actions;
}


function clearEmptyCacheFiles($account) {
    $files_to_delete = array(__DIR__ . '/../cache/_table_data_account_.json');
    $dir = new DirectoryIterator(__DIR__ . '/../cache');
    foreach ($dir as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'json' && substr($filename, 0, 1) == '_') {
            $checkfile = false;
            if (strpos($filename, $account) !== FALSE) {
                $checkfile = true;
            }
            if ($checkfile) {
                $delete_file = false;
                $json = file_get_contents(__DIR__ . '/../cache/' . $filename);
                $data = json_decode($json, true);
                if (isset($data['data']['searchTransactionsForward']['results']) && count($data['data']['searchTransactionsForward']['results'] == 0)) {
                    $delete_file = true;
                }
                if (isset($data['data']['searchTransactionsBackward']['results']) && count($data['data']['searchTransactionsBackward']['results'] == 0)) {
                    $delete_file = true;
                }
                if ($data && array_key_exists('cursor', $data) && $data['cursor'] == '') {
                    $delete_file = true;
                }
                if ($delete_file) {
                    $files_to_delete[] = __DIR__ . '/../cache/' . $filename;
                }
            }
        }
    }
    foreach ($files_to_delete as $file) {
        @unlink($file);
    }
}

function addTransferData($transfers,$transfers_by_player,$player,$worker_number) {
    $worker_index = 'worker'.$worker_number;
    if (array_key_exists($player['json'][$worker_index], $transfers)) {
        if (!array_key_exists($player['key'], $transfers_by_player)) {
            $transfers_by_player[$player['key']] = array();
            $transfers_by_player[$player['key']]['types'] = array();
        }
        foreach ($transfers[$player['json'][$worker_index]]['types'] as $key => $value) {
            if (!array_key_exists($key, $transfers_by_player[$player['key']]['types'])) {
                $transfers_by_player[$player['key']]['types'][$key] = 0;
            }
            $transfers_by_player[$player['key']]['types'][$key] += $value;
        }
    }
    return $transfers_by_player;
}

function getGoldToTokenValue($gold,$token_prices) {
    if ($token_prices['EOS'] && $token_prices['PGL']) {
        $PGL = ($gold/1000);
        $EOS = $PGL * $token_prices['PGL'];
        $USD = $EOS * $token_prices['EOS'];
        return " <sub>(PGL: " . $PGL . ", EOS: " . number_format($EOS,4) . ", USD: " . money_format("%n",$USD) . ")</sub>";
    }
    return "";
}

function getTokenPrices($marketcapone_access_key) {
    $filename = __DIR__ . '/../cache/_token_prices.json';
    $json = @file_get_contents($filename);
    $use_cached_data = false;
    if ($json) {
        $use_cached_data = true;
        if (time() - filemtime($filename) > (60*1)) { // only get fresh prices every minute
            $use_cached_data = false;
        }
    }
    if ($use_cached_data) {
        console_log("getTokenPrices: Use Cached: " . (time() - filemtime($filename)));
        $token_prices = json_decode($json, true);
        return $token_prices;
    }
    $token_prices = array('EOS' => 0, 'PGL' => 0);
    $header = array('MCO-AUTH' => $marketcapone_access_key);
    $url = 'https://marketcap.one/api/1.0/token/';
    $json = request('GET', $url . 'eosio.token', $header, array());
    if ($json) {
        $eos_token_data = json_decode($json, true);
        if (array_key_exists('status', $eos_token_data) && $eos_token_data['status'] == '200') {
            $token_prices['EOS'] = number_format($eos_token_data['data']['current_price'],4);
        }
    }
    $json = request('GET', $url . 'prospectorsg', $header, array());
    if ($json) {
        $pgl_token_data = json_decode($json, true);
        if (array_key_exists('status', $pgl_token_data) && $pgl_token_data['status'] == '200') {
            $token_prices['PGL'] = number_format($pgl_token_data['data']['current_price'],4);
        }
    }
    if ($token_prices['EOS'] && $token_prices['PGL']) {
        $json = json_encode($token_prices);
        file_put_contents($filename,$json);
    }

    return $token_prices;
}

function authenticateDFuse() {
    $filename = __DIR__ . '/../.api_credentials.json';
    $json = file_get_contents($filename) or die("<br/><br/><strong>Authentication file .api_credentials.json not found.</strong>");
    $api_credentials = json_decode($json, true);
    if (time() > $api_credentials['expires_at']) {
        console_log("...Updating DFuse Authentication Token...");
        $url = 'https://auth.dfuse.io/v1/auth/issue';
        $params = array('api_key' => $api_credentials['api_key']);
        $header = array('Content-Type' => 'application/json');
        $json = request("POST", $url, $header, $params);
        if ($json) {
            $new_token = json_decode($json, true);
            if (array_key_exists('token', $api_credentials)) {
                $api_credentials['token'] = $new_token['token'];
                $api_credentials['expires_at'] = $new_token['expires_at'];
                $data = json_encode($api_credentials);
                file_put_contents($filename,$data);
            }
        }
    }
    return $api_credentials;
}