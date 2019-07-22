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
    print "<pre>";
    var_dump($var);
    print "</pre>";
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
            $data = json_encode($params);
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
    $data = file_get_contents($url, false, $context);
    return $data;
}


function getActionData($action, $actor, $token, $cursor="") {
    $json = getJSONCached($action, $actor, $token, $cursor);
    $previous_hash = md5($json);
    $data = json_decode($json,true);
    $transactions = $data['transactions'];
    if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
        $cursor = $data['cursor'];
        $keep_fetching = true;
        while($keep_fetching) {
            $more_json = getJSONCached($action, $actor, $token, $cursor);
            $hash = md5($more_json);
            $more_data = json_decode($more_json,true);
            if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
                if ($cursor == $more_data['cursor'] && $hash == $previous_hash) {
                    $keep_fetching = false;
                } else {
                    $transactions = array_merge($transactions,$more_data['transactions']);
                }
                $cursor = $more_data['cursor'];
                $previous_hash = $hash;
            } else {
                $keep_fetching = false;
            }
        }
    }
    return $transactions;
}

function getJSONCached($action, $actor, $token, $cursor="") {
    $filename = __DIR__ . '/../cache/_' . $action . '_' . $actor . '_' . $cursor . '.json';
    //print "Filename: $filename";
    $json = @file_get_contents($filename);
    if ($json) {
        return $json;
    }
    $json = getJSON($action, $actor, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function getJSON($action, $actor, $token, $cursor="") {
    print "...Updating Cache: " . $action . " " . $actor . " " . $cursor . "...<br />";
    $account = 'prospectorsc';
    if ($action == 'transfer') {
        $account = 'prospectorsg';
    }
    $json = searchTransactions('account:' . $account . ' action:' . $action .' auth:' . $actor, $token, $cursor);
    return $json;
}

function getActionDataByKey($action, $key, $token, $cursor="") {
    $json = getJSONByKeyCached($action, $key, $token, $cursor);
    $previous_hash = md5($json);
    $data = json_decode($json,true);
    $transactions = $data['transactions'];
    if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
        $cursor = $data['cursor'];
        $keep_fetching = true;
        while($keep_fetching) {
            $more_json = getJSONByKeyCached($action, $key, $token, $cursor);
            $hash = md5($more_json);
            $more_data = json_decode($more_json,true);
            if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
                if ($cursor == $more_data['cursor'] && $hash == $previous_hash) {
                    $keep_fetching = false;
                } else {
                    $transactions = array_merge($transactions,$more_data['transactions']);
                }
                $cursor = $more_data['cursor'];
                $previous_hash = $hash;
            } else {
                $keep_fetching = false;
            }
        }
    }
    return $transactions;
}

function getJSONByKeyCached($action, $key, $token, $cursor="") {
    $filename = __DIR__ . '/../cache/_' . $action . '_' . str_replace('/', '-', $key) . '_' . $cursor . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
        return $json;
    }
    $json = getJSONByKey($action, $key, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function getJSONByKey($action, $key, $token, $cursor="") {
    print "...Updating Cache: " . $action . " " . $key . " " . $cursor . "...<br />";
    $q = 'account:prospectorsc db.key:' . $key;
    if ($action != '') {
        $q .= ' action:' . $action;
    }
    $json = searchTransactions($q, $token, $cursor);
    return $json;
}

function searchTransactions($q, $token, $cursor="") {
    $params = array('q' => $q);
    if ($cursor != "") {
        $params['cursor'] = $cursor;
    }
    $json = searchTransactionsInternal($params, $token);
    return $json;
}

function checkPayment($account, $required_amount, $owner, $token) {
    $paid = false;
    $payment_date = '';
    $payment_amount = 0;
    $q = 'account:eosio.token action:transfer data.to:' . $owner . ' data.from:' . $account . ' auth:' . $account;
    // I'd prefer this, but it takes too long to run:
    //$params = array('q' => $q, 'sort' => 'desc', 'limit' => 1);
    $params = array('q' => $q, 'start_block' => '29000000');

    $json = searchTransactionsInternal($params, $token);
    if ($json) {
        $data = json_decode($json, true);
        if (count($data['transactions'])) {
            $transaction = array_pop($data['transactions']);
            $payment_date = $transaction['lifecycle']['execution_trace']['block_time'];
            foreach ($transaction['lifecycle']['execution_trace']['action_traces'] as $action_trace) {
                $payment_with_symbol = $action_trace['act']['data']['quantity'];
                if (substr($payment_with_symbol, -4) == " EOS") {
                    $payment_amount = str_replace(" EOS", "", $payment_with_symbol);
                    if ($payment_amount >= $required_amount) {
                        $now = new DateTime();
                        $block_time_string_format = "Y-m-d H:i:s";
                        $block_time_string = $payment_date;
                        $block_time_string = str_replace("T", " ", $block_time_string);
                        $block_time_string = str_replace(".5", "", $block_time_string);
                        $paymentTime = DateTime::createFromFormat($block_time_string_format, $block_time_string);
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

function searchTransactionsInternal($params, $token) {
    $url = 'https://mainnet.eos.dfuse.io/v0/search/transactions';
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("GET", $url, $header, $params);
    return $json;
}

function binToJSONCached($hex_rows_to_process, $table, $token) {
    $filename = __DIR__ . '/../cache/_bin_to_json_' . $table . '_' . md5(serialize($hex_rows_to_process)) . '.json';
    $json = binToJSON($hex_rows_to_process, $table, $token);
    file_put_contents($filename,$json);
    return $json;
}

function binToJSON($hex_rows_to_process, $table, $token) {
    $url = 'https://mainnet.eos.dfuse.io/v0/state/abi/bin_to_json';
    $params = array('account' => 'prospectorsc', 'table' => $table, 'hex_rows' => $hex_rows_to_process);
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("POST", $url, $header, $params);
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
    print "...Updating Cache: " . $table . " " . $cursor . "...<br />";
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

function addDBOPSData($transactions, $table, $token, $key_filter = '') {
    global $types;
    $transactions_with_deltas = array();
    $hex_rows_to_process = array();
    foreach ($transactions as $transaction_index => $transaction) {
        foreach ($transaction['lifecycle']['dbops'] as $dbop) {
            if ($dbop['table'] == $table) {
                $include = true;
                if ($key_filter != "" && $key_filter != $dbop['key']) {
                    // removing this for now since we need all db operations to find referrals
                    //$include = false;
                }
                if ($include) {
                    if (count($dbop['old'])) {
                        $hex_rows_to_process[] = $dbop['old']['hex'];
                    }
                    if (count($dbop['new'])) {
                        $hex_rows_to_process[] = $dbop['new']['hex'];
                    }
                }
            }
        }
        $transactions_with_deltas[] = $transaction;
    }
    $json = binToJSONCached($hex_rows_to_process, $table, $token);
    $data = json_decode($json, true);
    $response_index = -1;
    foreach ($transactions_with_deltas as $transaction_index => $transaction) {
        $dbops_with_data = array();
        $other_dbops_with_data = array();
        foreach ($transaction['lifecycle']['dbops'] as $dbop_index => $dbop) {
            if ($dbop['table'] == $table) {
                $dbop_with_data = $dbop;
                if (count($dbop['old'])) {
                    $response_index++;
                    $dbop_with_data['old']['data'] = $data['rows'][$response_index];
                }
                if (count($dbop['new'])) {
                    $response_index++;
                    $dbop_with_data['new']['data'] = $data['rows'][$response_index];
                }
                $include = true;
                if ($key_filter != "" && $key_filter != $dbop['key']) {
                    $include = false;
                }
                if ($include) {
                    $dbops_with_data[] = $dbop_with_data;
                } else {
                    $other_dbops_with_data[] = $dbop_with_data;
                }
            }
        }
        if (count($dbops_with_data)) {
            $transactions_with_deltas[$transaction_index]['lifecycle']['dbops'] = $dbops_with_data;
        }
        if (count($other_dbops_with_data)) {
            $transactions_with_deltas[$transaction_index]['lifecycle']['other_dbops'] = $other_dbops_with_data;
        }
    }
    return $transactions_with_deltas;
}

function getAccountBalanceChanges($account, $token) {
    $prospectors_account = 'prospectorsc';
    $account_actions = array();
    $deposits = getActionData('transfer', $account, $token);
    if ($deposits) {
        $deposits_data = getTransactionData($deposits);
        foreach ($deposits as $key => $deposit) {
            $include = false;
            if ($deposits_data[$key]['data']['to'] == $prospectors_account && $deposits_data[$key]['data']['memo'] != 'stake') {
                $include = true;
            }
            if ($include) {
                $amount = (str_replace(' PGL', '', $deposits_data[$key]['data']['quantity']) * 1000);
                $account_action = array('block_time' => $deposit["lifecycle"]['execution_trace']['block_time'], 'id' => $deposit["lifecycle"]["id"]);
                $account_action['activity'] = 'deposit';
                $account_action['amount'] = $amount;
                $account_action['details'] = '';
                $account_actions[] = $account_action;
            }
        }
    }
    $everything = getActionDataByKey('', 'account/prospectorsc/' . $account, $token);
    $everything_with_deltas = array();
    if (count($everything)) {
        $everything_with_deltas = addDBOPSData($everything, 'account', $token, $account);
        foreach ($everything_with_deltas as $key => $transaction) {
            $dbops_index = 0;
            foreach ($transaction['lifecycle']['execution_trace']['action_traces'] as $action_trace_index => $action_trace) {
                $json = json_encode($action_trace['act']['data']);
                $old_balance = 0;
                if (isset($transaction['lifecycle']['dbops'][$dbops_index]['old']['data']['balance'])) {
                    $old_balance = $transaction['lifecycle']['dbops'][$dbops_index]['old']['data']['balance'];
                }
                $new_balance = $transaction['lifecycle']['dbops'][$dbops_index]['new']['data']['balance'];
                $change = abs($new_balance - $old_balance);
                if ($new_balance < $old_balance) {
                    $change = (0 - $change);
                }
                $amount = $change;
                $account_action = array('block_time' => $transaction["lifecycle"]['execution_trace']['block_time'], 'id' => $transaction["lifecycle"]["id"]);
                $account_action['activity'] = $action_trace['act']['name'];
                $account_action['amount'] = $amount;
                $account_action['details'] = $json;
                if ($change != 0) {
                    $account_actions[] = $account_action;
                }
                $dbops_index++;
            }
        }
    }
    usort($account_actions, 'block_time_compare');
    $return = array('account_actions' => $account_actions, 'raw_data' => $everything_with_deltas);
    return $return;
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
                $json = file_get_contents(__DIR__ . '/../cache/' . $filename);
                $data = json_decode($json, true);
                if ($data && array_key_exists('cursor', $data) && $data['cursor'] == '') {
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

function authenticateDFuse() {
    $filename = __DIR__ . '/../.api_credentials.json';
    $json = file_get_contents($filename) or die("<br/><br/><strong>Authentication file .api_credentials.json not found.</strong>");
    $api_credentials = json_decode($json, true);
    if (time() > $api_credentials['expires_at']) {
        print "<br />...Updating DFuse Authentication Token...<br />";
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

function getTransactionData($data, $name = '') {
    $transaction_data = array();
    foreach ($data as $transaction_index => $transaction) {
        foreach ($transaction['lifecycle']['execution_trace']['action_traces'] as $action_trace_index => $action_trace) {
            $add_trace = true;
            if ($name != '' && $action_trace['act']['name'] != $name) {
                $add_trace = false;
            }
            if ($add_trace) {
                $response = array('name' => $action_trace['act']['name'], 'data' => $action_trace['act']['data']);
                $transaction_data[] = $response;
            }
        }
    }
    return $transaction_data;
}