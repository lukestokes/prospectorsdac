<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Prospectors.io: DAC Factory</title>

<style>
table {
  font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

table td, table th {
  border: 1px solid #ddd;
  padding: 8px;
}

table tr:nth-child(even){background-color: #f2f2f2;}

table tr:hover {background-color: #ddd;}

table th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #4CAF50;
  color: white;
}
</style>

  </head>
  <body>

<center>
	<img src="https://prospectors.io/assets/logo-c4a2df66ead25380fa80adac0b5f4c9bd8876c35f941a3dcbfe6b3a344ad6540.png">
	<h1>Welcome to the DAC Factory Prospectors Camp at 12/-19</h1>
</center>

If you haven't signed up yet, please use this referral link: <a href="https://play.prospectors.io?ref=1lukestokes1">https://play.prospectors.io?ref=1lukestokes1</a><br />

This page currently tracks accounts who were given resources from the DACFactory camp at 12/-19. Those accounts may have also contributed resources to the camp. Things I'd still like to add or improve on:

<ul>
	<li>Include the health of items transferred into and out of storage as part of a value calculation.</li>
	<li>Factor in the time required to craft items and the value of those items transferred.</li>
	<li>Keep track of how the community-crafted tools were used such as if the resources obtained with them were put back in storage or if the worker got paid (and how much) by the DAC for completing a job order.</li>
	<li>Using the EOS, PGL, and USD valuations at the time of a transaction, keep track of capital investment per user as well as time investment.</li>
	<li>Eventually create a DAC for those involved, contributing tokens based on involvement. The token holders will then vote on what the DAC does with the pool of resources (reimburse investors, build a building, expand to include more plots, etc).</li>
	<li>Expand these tools for others unions/DACs within Prospectors (and/or consider joining an existing group)</li>
</ul>

At this point, I make no guarutnees about anything regarding how centralized or decentralized this plot will be. For now, I'm just playing a game with my kids and others are free to join me, but I am also using it as an excuse to explore DAC technology in a gamified environment with a montetary component. I also can't vouch for the accuracy of this data. I'm still learning, so please point something out if it looks incorrect.<br />

<?php
include 'functions.php';

$api_credentials = authenticateDFuse();

$last_cache_update = @file_get_contents('cache/last_cache_update.txt');
$update_cache = false;
if ($last_cache_update) {
	if (isset($_GET['refresh']) && $_GET['refresh'] == 1) {
		if ((time() - $last_cache_update) > 3600) {
			$update_cache = true;
			print "<br /><strong>Cache updated.</strong><br />";
		} else {
			print "<br /><strong>Error: You can only update the cache every hour.</strong><br />";
		}
	}
} else {
	$update_cache = true;
}
if ($update_cache) {
	clearEmptyCacheFiles();
	$last_cache_update = time();
	file_put_contents('cache/last_cache_update.txt',$last_cache_update);	
}

$CachedDate = new DateTime();
$CachedDate->setTimestamp($last_cache_update);
print "Data last updated: <strong>" . $CachedDate->format('Y-m-d H:i:s T') . "</strong><br /><br />";

$dac_loc_id = 851949;
$loc_owner = '1lukestokes1';
$dac_workers = array();

$json = getTableDataCached("account", $api_credentials['token']);
$all_players = json_decode($json,true);

$json = getJSONCached('mvstorewrk', $loc_owner, $api_credentials['token']);
$data = json_decode($json,true);
$transactions = $data['transactions'];
if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
	$keep_fetching = true;
	while($keep_fetching) {
		$more_json = getJSONCached('mvstorewrk', $loc_owner, $api_credentials['token'], $data['cursor']);
		$more_data = json_decode($more_json,true);
		if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
			$transactions = array_merge($transactions,$more_data['transactions']);
			if ($data['cursor'] == $more_data['cursor']) {
				$keep_fetching = false;
			}
			$data['cursor'] = $more_data['cursor'];
		} else {
			$keep_fetching = false;
		}
	}
}

$transfers_out = array();
$transaction_data = getTransactionData($transactions);
foreach ($transaction_data as $data) {
	if ($data['loc_id'] == $dac_loc_id) {
		$worker_id = $data['worker_id'];
		if (!array_key_exists($worker_id, $transfers_out)) {
			$transfers_out[$worker_id] = array();
			$transfers_out[$worker_id]['types'] = array();
		}
		$type = $types[$data['stuff']['type_id']];
		if (!array_key_exists($type, $transfers_out[$worker_id]['types'])) {
			$transfers_out[$worker_id]['types'][$type] = 0;
		}
		$transfers_out[$worker_id]['types'][$type] += $data['stuff']['amount'];
	}
}

//var_dump($transfers_out);

$transfers_out_by_player = array();

foreach ($all_players['rows'] as $index => $player) {
	$transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"0");
	$transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"1");
	$transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"2");
}

//var_dump($transfers_out_by_player);

$transfers_in = array();

foreach (array_keys($transfers_out_by_player) as $index => $player_name) {
	$json = getJSONCached('mvwrkstore', $player_name, $api_credentials['token']);
	$data = json_decode($json,true);
	if ($data) {
		$transactions = $data['transactions'];
		if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
			$keep_fetching = true;
			while($keep_fetching) {
				$more_json = getJSONCached('mvwrkstore', $player_name, $api_credentials['token'], $data['cursor']);
				$more_data=json_decode($more_json,true);
				if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
					$transactions = array_merge($transactions,$more_data['transactions']);
					if ($data['cursor'] == $more_data['cursor']) {
						$keep_fetching = false;
					}
					$data['cursor'] = $more_data['cursor'];
				} else {
					$keep_fetching = false;
				}
			}
		}
		if ($transactions) {
			$transaction_data = getTransactionData($transactions);
			foreach ($transaction_data as $data) {
				if ($data['loc_id'] == $dac_loc_id) {
					$worker_id = $data['worker_id'];
					if (!array_key_exists($worker_id, $transfers_in)) {
						$transfers_in[$worker_id] = array();
						$transfers_in[$worker_id]['types'] = array();
					}
					$type = $types[$data['stuff']['type_id']];
					if (!array_key_exists($type, $transfers_in[$worker_id]['types'])) {
						$transfers_in[$worker_id]['types'][$type] = 0;
					}
					$transfers_in[$worker_id]['types'][$type] += $data['stuff']['amount'];			
				}
			}
		} else {
			print "<strong>Error:</strong> No data returned.<br />";
		}
	} else {
		print "<strong>Error:</strong> No data returned.<br />";
	}
}

//var_dump($transfers_in);

$transfers_in_by_player = array();

foreach ($all_players['rows'] as $index => $player) {
	$transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"0");
	$transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"1");
	$transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"2");
}

//var_dump($transfers_in_by_player);

//print "<pre>";
//var_dump($transfers_out_by_player);
//print "</pre>";

$transfers_out_by_player_to_display = array();
foreach ($transfers_out_by_player as $player => $data) {
	foreach ($data['types'] as $type => $amount) {
		if ($amount != 0) {
			if (!in_array($type, $transfers_out_by_player_to_display)) {
				$transfers_out_by_player_to_display[] = $type;
			}
		}
	}
}

$transfers_in_by_player_to_display = array();
foreach ($transfers_in_by_player as $player => $data) {
	foreach ($data['types'] as $type => $amount) {
		if ($amount != 0) {
			if (!in_array($type, $transfers_in_by_player_to_display)) {
				$transfers_in_by_player_to_display[] = $type;
			}
		}
	}
}


?>

<h1>Resources Removed From Storage</h1>
<table>
	<tr>
		<th>Player</th>
		<?php
		foreach ($types as $key => $type) {
			if (in_array($type, $transfers_out_by_player_to_display)) {
				print '<th>' . $type . '</th>';
			}
		}
		?>
	</tr>
	<?php
		foreach ($transfers_out_by_player as $player => $data) {
			print "<tr>";
			print "<td>" . $player . "</td>";
			foreach ($types as $key => $type) {
				if (in_array($type, $transfers_out_by_player_to_display)) {
					print "<td>";
					if (array_key_exists($type, $data['types'])) {
						$amount = formatAmount($data['types'][$type],$type);
						print $amount;
					} else {
						print "0";
					}
					print "</td>";
				}
			}
			print "</tr>";
		}
	?>
</table>

<h1>Resources Added To Storage</h1>
<table>
	<tr>
		<th>Player</th>
		<?php
		foreach ($types as $key => $type) {
			if (in_array($type, $transfers_in_by_player_to_display)) {
				print '<th>' . $type . '</th>';
			}
		}
		?>
	</tr>
	<?php
		foreach ($transfers_in_by_player as $player => $data) {
			print "<tr>";
			print "<td>" . $player . "</td>";
			foreach ($types as $key => $type) {
				if (in_array($type, $transfers_in_by_player_to_display)) {				
					print "<td>";
					if (array_key_exists($type, $data['types'])) {
						$amount = formatAmount($data['types'][$type],$type);
						print $amount;
					} else {
						print "0";
					}
					print "</td>";
				}
			}
			print "</tr>";
		}
	?>
</table>

<?php

$net_transfers_by_player = array();

foreach ($transfers_in_by_player as $player => $data) {
	if (!array_key_exists($player, $net_transfers_by_player)) {
		$net_transfers_by_player[$player] = array();
		$net_transfers_by_player[$player]['types'] = array();
	}
	foreach ($data['types'] as $type => $amount) {
		if (!array_key_exists($type, $net_transfers_by_player[$player]['types'])) {
			$net_transfers_by_player[$player]['types'][$type] = 0;
		}
		/*
		print "<pre>";
		var_dump($data);
		var_dump($amount);
		var_dump($net_transfers_by_player[$player]['types'][$type]);
		print "</pre>";
		*/
		$net_transfers_by_player[$player]['types'][$type] += $amount;
	}
}

foreach ($transfers_out_by_player as $player => $data) {
	if (!array_key_exists($player, $net_transfers_by_player)) {
		$net_transfers_by_player[$player] = array();
		$net_transfers_by_player[$player]['types'] = array();
	}
	foreach ($data['types'] as $type => $amount) {
		if (!array_key_exists($type, $net_transfers_by_player[$player]['types'])) {
			$net_transfers_by_player[$player]['types'][$type] = 0;
		}
		$net_transfers_by_player[$player]['types'][$type] -= $amount;
	}
}

$net_resources_to_display = array();
foreach ($net_transfers_by_player as $player => $data) {
	foreach ($data['types'] as $type => $amount) {
		if ($amount != 0) {
			if (!in_array($type, $net_resources_to_display)) {
				$net_resources_to_display[] = $type;
			}
		}
	}
}

?>

<h1>Net Resource Contribution By Player</h1>
<table>
	<tr>
		<th>Player</th>
		<?php
		foreach ($types as $key => $type) {
			if (in_array($type, $net_resources_to_display)) {
				print '<th>' . $type . '</th>';
			}
		}
		?>
	</tr>
	<?php
		foreach ($net_transfers_by_player as $player => $data) {
			print "<tr>";
			print "<td>" . $player . "</td>";
			foreach ($types as $key => $type) {
				if (in_array($type, $net_resources_to_display)) {				
					print "<td>";
					if (array_key_exists($type, $data['types'])) {
						$amount = formatAmount($data['types'][$type],$type);
						print $amount;
					} else {
						print "0";
					}
					print "</td>";
				}
			}
			print "</tr>";
		}
	?>
</table>

<?php
/*

	$json = getJSONCached('mkmineord', $loc_owner, $api_credentials['token']);
	$data = json_decode($json,true);
	$transaction_data = getTransactionData($data['transactions']);


	foreach ($transaction_data as $data) {

		print "<pre>";
		var_dump($data);
		print "</pre>";

	}
*/

$json = getJSONCached('mkpurchase', $loc_owner, $api_credentials['token']);
$data = json_decode($json,true);
$transactions = $data['transactions'];

if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
	$keep_fetching = true;
	while($keep_fetching) {
		$more_json = getJSONCached('mkpurchase', $loc_owner, $api_credentials['token'], $data['cursor']);
		$more_data=json_decode($more_json,true);
		if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
			$transactions = array_merge($transactions,$more_data['transactions']);
			if ($data['cursor'] == $more_data['cursor']) {
				$keep_fetching = false;
			}
			$data['cursor'] = $more_data['cursor'];
		} else {
			$keep_fetching = false;
		}
	}
}

$market_actions = array();
foreach ($transactions as $transaction_index => $transaction) {
    foreach ($transaction['lifecycle']['dbops'] as $dbop) {
		if ($dbop['table'] == 'market') {
			$market_action = array();
			$market_action['old'] = $dbop['old']['hex'];
			if (count($dbop['new'])) {
				$market_action['new'] = $dbop['new']['hex'];
			} else {
				$market_action['new'] = '';
			}
			$market_actions[] = $market_action;
		}
    }
}

$hex_rows_to_process = array();
foreach ($market_actions as $key => $market_action) {
	$hex_rows_to_process[] = $market_action['old'];
	if ($market_action['new'] != "") {
		$hex_rows_to_process[] = $market_action['new'];
	}
}

$url = 'https://mainnet.eos.dfuse.io/v0/state/abi/bin_to_json';
$params = array('account' => 'prospectorsc', 'table' => 'market', 'hex_rows' => $hex_rows_to_process);
$header = array('Content-Type' => 'application/json');
$header['Authorization'] = 'Bearer ' . $api_credentials['token'];
$json = request("POST", $url, $header, $params);
$data = json_decode($json, true);

$purchases = array();
$response_index = 0;
foreach ($market_actions as $key => $market_action) {
	$purchase = array();
	$old_values = $data['rows'][$response_index];
	//print "Old Values: ";
	//print $old_values['stuff']['amount'] . " " . $types[$old_values['stuff']['type_id']] . " at " . $old_values['price'] . " each (total: " . $old_values['price'] * $old_values['stuff']['amount'] . ")<br />";
	$purchase['type'] = $types[$old_values['stuff']['type_id']];
	$purchase['amount'] = $old_values['stuff']['amount'];
	$purchase['price'] = $old_values['price'];
	if ($market_action["new"] != "") {
		$response_index++;
		$new_values = $data['rows'][$response_index];
		$purchase['amount'] -= $new_values['stuff']['amount'];
	}
	$response_index++;
	$purchases[] = $purchase;
}

?>

<h1>Purchases By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
	<tr>
		<th>Type</th>
		<th>Amount</th>
		<th>Price</th>
		<th>Total</th>
	</tr>
	<?php
		$total = 0;
		foreach ($purchases as $purchase) {
			$amount = formatAmount($purchase['amount'],0,$purchase['type']);
			$total += $purchase['price']*$amount;
			print "<tr>";
			print "<td>" . $purchase['type'] . "</td>";
			print "<td>" . $amount . "</td>";
			print "<td>" . $purchase['price'] . "</td>";
			print "<td>" . $purchase['price']*$amount . "</td>";
			print "</tr>";
		}
	?>
	<tr>
		<td colspan="3" align="right">Total:</td>
		<td><?php print (0-$total); ?></td>
	</tr>
</table>


<?php
/*
$json = getJSONByKeyCached('rentloc', 'account/prospectorsc/1lukestokes1', $api_credentials['token']);
$data = json_decode($json,true);
$transactions = $data['transactions'];
$transaction_data = getTransactionData($data['transactions']);

var_dump($transaction_data);
*/

// account:prospectorsc db.key:account/prospectorsc/1lukestokes1 action:mkpurchase

//var_dump($api_credentials['token']);

$json = getJSONByKeyCached('mkpurchase', 'account/prospectorsc/1lukestokes1', $api_credentials['token']);
$data = json_decode($json,true);
$transactions = $data['transactions'];

/*
if (array_key_exists('cursor', $data) && $data['cursor'] != "") {
	$keep_fetching = true;
	while($keep_fetching) {
		$more_json = getJSONByKeyCached('mkpurchase', 'account/prospectorsc/1lukestokes1', $api_credentials['token'], $data['cursor']);
		$more_data=json_decode($more_json,true);
		if (array_key_exists('cursor', $more_data)  && $more_data['cursor'] != "") {
			$transactions = array_merge($transactions,$more_data['transactions']);
			if ($data['cursor'] == $more_data['cursor']) {
				$keep_fetching = false;
			}
			$data['cursor'] = $more_data['cursor'];
		} else {
			$keep_fetching = false;
		}
	}
}
*/

$transaction_data = getTransactionData($data['transactions']);

//print "<pre>";
//var_dump($transaction_data);
//print "</pre>";


$market_actions = array();
foreach ($transactions as $transaction_index => $transaction) {
    foreach ($transaction['lifecycle']['dbops'] as $dbop) {
		if ($dbop['table'] == 'market') {
			$market_action = array();
			$market_action['old'] = $dbop['old']['hex'];
			if (count($dbop['new'])) {
				$market_action['new'] = $dbop['new']['hex'];
			} else {
				$market_action['new'] = '';
			}
			$market_actions[] = $market_action;
		}
    }
}

$hex_rows_to_process = array();
foreach ($market_actions as $key => $market_action) {
	$hex_rows_to_process[] = $market_action['old'];
	if ($market_action['new'] != "") {
		$hex_rows_to_process[] = $market_action['new'];
	}
}

$url = 'https://mainnet.eos.dfuse.io/v0/state/abi/bin_to_json';
$params = array('account' => 'prospectorsc', 'table' => 'market', 'hex_rows' => $hex_rows_to_process);
$header = array('Content-Type' => 'application/json');
$header['Authorization'] = 'Bearer ' . $api_credentials['token'];
$json = request("POST", $url, $header, $params);
$data = json_decode($json, true);


//print "<pre>";
//var_dump($data);
//print "</pre>";


$purchases = array();
$response_index = 0;
foreach ($market_actions as $key => $market_action) {
	$purchase = array();
	$old_values = $data['rows'][$response_index];
	//print "Old Values: ";
	//print $old_values['stuff']['amount'] . " " . $types[$old_values['stuff']['type_id']] . " at " . $old_values['price'] . " each (total: " . $old_values['price'] * $old_values['stuff']['amount'] . ")<br />";
	$purchase['type'] = $types[$old_values['stuff']['type_id']];
	$purchase['amount'] = $old_values['stuff']['amount'];
	$purchase['price'] = $old_values['price'];
	if ($market_action["new"] != "") {
		$response_index++;
		$new_values = $data['rows'][$response_index];
		$purchase['amount'] -= $new_values['stuff']['amount'];
	}
	$response_index++;

	$purchase['amount'] = formatAmount($purchase['amount'],0,$purchase['type']);
	$purchase['total'] = $purchase['amount'] * $purchase['price'];

	if ($old_values['owner'] == $loc_owner || $old_values['owner'] == $loc_owner) {
		$purchases[] = $purchase;
	}
}

//print "<pre>";
//var_dump($purchases);
//print "</pre>";

$grouped_purchases = array();
foreach ($purchases as $purchase) {
	if (!array_key_exists($purchase['type'], $grouped_purchases)) {
		$grouped_purchases[$purchase['type']] = array();
	}
	$grouped_purchases[$purchase['type']][] = $purchase;
}

$sales_details = array();
foreach ($grouped_purchases as $type => $purchases) {
	$number_of_sales = count($purchases);
	$total_amount_of_sales = array_sum(array_column($purchases, 'total'));
	$amount_sold = array_sum(array_column($purchases, 'amount'));
	$average_amount_sold = $amount_sold/$number_of_sales;
	$average_sale_price = array_sum(array_column($purchases, 'price'))/$number_of_sales;
	$sales_details[$type] = array(
		'number_of_sales' => $number_of_sales,
		'total_amount_of_sales' => $total_amount_of_sales,
		'amount_sold' => $amount_sold,
		'average_amount_sold' => $average_amount_sold,
		'average_sale_price' => $average_sale_price
	);
}



?>
<!--
<h1>Sales By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
	<tr>
		<th>Type</th>
		<th>amount</th>
		<th>price</th>
		<th>total</th>
	</tr>
	<?php
		$total = 0;
		foreach ($grouped_purchases as $type => $purchases) {
			foreach ($purchases as $purchase) {
				$total += $purchase['price']*$purchase['amount'];
				print "<tr>";
				print "<td>" . $purchase['type'] . "</td>";
				print "<td>" . $purchase['amount'] . "</td>";
				print "<td>" . $purchase['price'] . "</td>";
				print "<td>" . $purchase['price']*$purchase['amount'] . "</td>";
				print "</tr>";
			}
		}
	?>
	<tr>
		<td colspan="3" align="right">Total:</td>
		<td><?php print (0-$total); ?></td>
	</tr>
</table>
-->

<h1>Sales By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
	<tr>
		<th>Type</th>
		<th>number_of_sales</th>
		<th>average_sale_price</th>
		<th>average_amount_sold</th>
		<th>amount_sold</th>
		<th>total_amount_of_sales</th>
	</tr>
	<?php
		$total = 0;
		foreach ($sales_details as $type => $sale) {
			//$amount = formatAmount($sale['total_amount_of_sales'],0,$type);
			$total += $sale['total_amount_of_sales'];
			print "<tr>";
			print "<td>" . $type . "</td>";
			print "<td>" . $sale['number_of_sales'] . "</td>";
			print "<td>" . $sale['average_sale_price'] . "</td>";
			print "<td>" . $sale['average_amount_sold'] . "</td>";
			print "<td>" . $sale['amount_sold'] . "</td>";
			print "<td>" . $sale['total_amount_of_sales'] . "</td>";
			print "</tr>";
		}
	?>
	<tr>
		<td colspan="5" align="right">Total:</td>
		<td><?php print $total; ?></td>
	</tr>
</table>


  </body>
</html>