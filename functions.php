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

    $context = stream_context_create($opts);
    $data = file_get_contents($url, false, $context);
    return $data;
}

function getJSONCached($last_cache_update, $action, $actor, $token, $cursor="") {
    $filename = 'cache/_' . $action . '_' . $actor . '_' . $last_cache_update . '_' . $cursor . '.json';
    $data = @file_get_contents($filename);
    if ($data) {
        return $data;
    }
    $data = getJSON($action, $actor, $token, $cursor);
    file_put_contents($filename,$data);
    return $data;
}

function getJSON($action, $actor, $token, $cursor="") {
    print "...Updating Cache: " . $action . " " . $actor . " " . $cursor . "...<br />";
    $url = 'https://mainnet.eos.dfuse.io/v0/search/transactions';
    $params = array('q' => 'account:prospectorsc action:' . $action .' auth:' . $actor);
    if ($cursor != "") {
        $params['cursor'] = $cursor;
    }
    $header = array('Content-Type' => 'application/json');
    $header['Authorization'] = 'Bearer ' . $token;
    $json = request("GET", $url, $header, $params);
    return $json;
}

function getTableDataCached($last_cache_update, $table, $token, $cursor="") {
    $filename = 'cache/_' . $table . '_' . $last_cache_update . '_' . $cursor . '.json';
    $data = @file_get_contents($filename);
    if ($data) {
        return $data;
    }
    $data = getTableData($table, $token, $cursor);
    file_put_contents($filename,$data);
    return $data;
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
