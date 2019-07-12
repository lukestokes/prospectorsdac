<?php

$types = array(
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
    //pdump($opts);
    //pdump($url);
    $context = stream_context_create($opts);
    $data = file_get_contents($url, false, $context);
    return $data;
}


function getActionData($last_cache_update, $action, $actor, $token, $cursor="") {
    $json = getJSONCached($last_cache_update, $action, $actor, $token, $cursor);
    $previous_hash = md5($json);
    $data = json_decode($json,true);
    $transactions = $data['transactions'];
    if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
        $cursor = $data['cursor'];
        $keep_fetching = true;
        while($keep_fetching) {
            $more_json = getJSONCached($last_cache_update, $action, $actor, $token, $cursor);
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

function getJSONCached($last_cache_update, $action, $actor, $token, $cursor="") {
    $filename = 'cache/_' . $action . '_' . $actor . '_' . $last_cache_update . '_' . $cursor . '.json';
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
    $json = searchTransactions('account:prospectorsc action:' . $action .' auth:' . $actor, $token, $cursor);
    return $json;
}

function getActionDataByKey($last_cache_update, $action, $key, $token, $cursor="") {
    $json = getJSONByKeyCached($last_cache_update, $action, $key, $token, $cursor);
    $previous_hash = md5($json);
    $data = json_decode($json,true);
    $transactions = $data['transactions'];
    if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
        $cursor = $data['cursor'];
        $keep_fetching = true;
        while($keep_fetching) {
            $more_json = getJSONByKeyCached($last_cache_update, $action, $key, $token, $cursor);
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

function getJSONByKeyCached($last_cache_update, $action, $key, $token, $cursor="") {
    $filename = 'cache/_' . $action . '_' . str_replace('/', '-', $key) . '_' . $last_cache_update . '_' . $cursor . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
        return $json;
    }
    $json = getJSONByKey($action, $key, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function getJSONByKey($action, $key, $token, $cursor="") {
    //print "...Updating Cache: " . $action . " " . $actor . " " . $cursor . "...<br />";
    $json = searchTransactions('account:prospectorsc action:' . $action .' db.key:' . $key, $token, $cursor);
    return $json;
}

function searchTransactions($q, $token, $cursor="") {
    $url = 'https://mainnet.eos.dfuse.io/v0/search/transactions';
    $params = array('q' => $q);
    if ($cursor != "") {
        $params['cursor'] = $cursor;
    }
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("GET", $url, $header, $params);
    return $json;
}

function getTableDataCached($last_cache_update, $table, $token, $cursor="") {
    $filename = 'cache/_table_data_' . $table . '_' . $last_cache_update . '_' . $cursor . '.json';
    $json = @file_get_contents($filename);
    if ($json) {
        return $json;
    }
    $json = getTableData($table, $token, $cursor);
    file_put_contents($filename,$json);
    return $json;
}

function getTableData($table, $token, $cursor="") {
    print "...Updating Cache: " . $table . " " . $actor . " " . $cursor . "...<br />";
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

function getTableDeltas($transactions, $table, $token, $owner_filter = '') {
    global $types;
    $actions = array();
    foreach ($transactions as $transaction_index => $transaction) {
        foreach ($transaction['lifecycle']['dbops'] as $dbop) {
            if ($dbop['table'] == $table) {
                $action = array();
                $action['old'] = $dbop['old']['hex'];
                if (count($dbop['new'])) {
                    $action['new'] = $dbop['new']['hex'];
                } else {
                    $action['new'] = '';
                }
                $actions[] = $action;
            }
        }
    }
    $hex_rows_to_process = array();
    foreach ($actions as $key => $action) {
        $hex_rows_to_process[] = $action['old'];
        if ($action['new'] != "") {
            $hex_rows_to_process[] = $action['new'];
        }
    }
    $url = 'https://mainnet.eos.dfuse.io/v0/state/abi/bin_to_json';
    $params = array('account' => 'prospectorsc', 'table' => 'market', 'hex_rows' => $hex_rows_to_process);
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("POST", $url, $header, $params);
    $data = json_decode($json, true);

    $action_datas = array();
    $response_index = 0;
    foreach ($actions as $key => $action) {
        $action_data = array();
        $old_values = $data['rows'][$response_index];
        if ($table == 'market') {
            $action_data['type'] = $types[$old_values['stuff']['type_id']];
            $action_data['amount'] = $old_values['stuff']['amount'];
            $action_data['price'] = $old_values['price'];
            if ($action["new"] != "") {
                $response_index++;
                $new_values = $data['rows'][$response_index];
                $action_data['amount'] -= $new_values['stuff']['amount'];
            }
        }
        $response_index++;

        $action_data['amount'] = formatAmount($action_data['amount'],0,$action_data['type']);
        $action_data['total'] = $action_data['amount'] * $action_data['price'];
        if ($owner_filter == "") {
            $action_datas[] = $action_data;
        } elseif ($old_values['owner'] == $owner_filter || $old_values['owner'] == $owner_filter) {
            $action_datas[] = $action_data;
        }
    }
    return $action_datas;
}

function clearEmptyCacheFiles() {
    $files_to_delete = array();
    $dir = new DirectoryIterator('./cache');
    foreach ($dir as $fileinfo) {
        if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'json' && substr($fileinfo->getFilename(), 0, 1) == '_') {
            $json = file_get_contents('./cache/' . $fileinfo->getFilename());
            $data = json_decode($json, true);
            if ($data && array_key_exists('cursor', $data) && $data['cursor'] == '') {
                $files_to_delete[] = './cache/' . $fileinfo->getFilename();
            }
        }
    }
    foreach ($files_to_delete as $file) {
        unlink($file);
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
    $filename = '.api_credentials.json';
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

function getTransactionData($data) {
    $transaction_data = array();
    foreach ($data as $transaction_index => $transaction) {
        foreach ($transaction['lifecycle']['execution_trace']['action_traces'] as $action_trace_index => $action_trace) {
            $transaction_data[] = $action_trace['act']['data'];
        }
    }
    return $transaction_data;
}