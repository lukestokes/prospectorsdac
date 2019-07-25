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

At this point, I make no guarantees about anything regarding how centralized or decentralized this plot will be. For now, I'm just playing a game with my kids and others are free to join me, but I am also using it as an excuse to explore DAC technology in a gamified environment with a monetary component. I also can't vouch for the accuracy of this data. I'm still learning, so please point something out if it looks incorrect.<br />

<br />
If you'd like to view your account balance history, go here: <a href="/history/">https://prospectorsdac.lukestokes.info/history/</a><br />


<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

include 'includes/functions.php';

//************ CONFIG ******************//

$dac_loc_id = 851949;
$loc_owner = '1lukestokes1';

//*************************************//

$api_credentials = authenticateDFuse();

$cache_file_name = __DIR__ . '/cache/last_cache_update.txt';
$last_cache_update = @file_get_contents($cache_file_name);
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
    clearEmptyCacheFiles($loc_owner);
    $last_cache_update = time();
    file_put_contents($cache_file_name,$last_cache_update);
}

$CachedDate = new DateTime();
$CachedDate->setTimestamp($last_cache_update);
print "Data last updated: <strong>" . $CachedDate->format('Y-m-d H:i:s T') . "</strong><br /><br />";

$dac_loc_id = 851949;
$loc_owner = '1lukestokes1';

$json = getTableDataCached("account", $api_credentials['token']);
$all_players = json_decode($json,true);

$q = 'account:prospectorsc action:mvstorewrk notif:false auth:' . $loc_owner;
$results = getGraphQLResults($q, '', $api_credentials['token']);

$transfers_out = array();
foreach ($results as $result) {
    foreach ($result['trace']['matchingActions'] as $matchingAction) {
        if ($matchingAction['data']['loc_id'] == $dac_loc_id) {
            $worker_id = $matchingAction['data']['worker_id'];
            if (!array_key_exists($worker_id, $transfers_out)) {
                $transfers_out[$worker_id] = array();
                $transfers_out[$worker_id]['types'] = array();
            }
            $type = $types[$matchingAction['data']['stuff']['type_id']];
            if (!array_key_exists($type, $transfers_out[$worker_id]['types'])) {
                $transfers_out[$worker_id]['types'][$type] = 0;
            }
            $transfers_out[$worker_id]['types'][$type] += $matchingAction['data']['stuff']['amount'];
        }
    }
}
$transfers_out_by_player = array();
foreach ($all_players['rows'] as $index => $player) {
    $transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"0");
    $transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"1");
    $transfers_out_by_player = addTransferData($transfers_out,$transfers_out_by_player,$player,"2");
}

$transfers_in = array();
foreach (array_keys($transfers_out_by_player) as $index => $player_name) {
    $q = 'account:prospectorsc action:mvwrkstore notif:false auth:' . $player_name;
    $results = getGraphQLResults($q, '', $api_credentials['token']);
    foreach ($results as $result) {
        foreach ($result['trace']['matchingActions'] as $matchingAction) {
            if ($matchingAction['data']['loc_id'] == $dac_loc_id) {
                $worker_id = $matchingAction['data']['worker_id'];
                if (!array_key_exists($worker_id, $transfers_in)) {
                    $transfers_in[$worker_id] = array();
                    $transfers_in[$worker_id]['types'] = array();
                }
                $type = $types[$matchingAction['data']['stuff']['type_id']];
                if (!array_key_exists($type, $transfers_in[$worker_id]['types'])) {
                    $transfers_in[$worker_id]['types'][$type] = 0;
                }
                $transfers_in[$worker_id]['types'][$type] += $matchingAction['data']['stuff']['amount'];
            }
        }
    }
}
$transfers_in_by_player = array();
foreach ($all_players['rows'] as $index => $player) {
    $transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"0");
    $transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"1");
    $transfers_in_by_player = addTransferData($transfers_in,$transfers_in_by_player,$player,"2");
}

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
                        $amount = formatAmount($data['types'][$type],0,$type);
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
                        $amount = formatAmount($data['types'][$type],0,$type);
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
                        $amount = formatAmount($data['types'][$type],0,$type);
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

$purchases = array();
$q = 'account:prospectorsc action:mkpurchase notif:false auth:' . $loc_owner;
$results = getGraphQLResults($q, '', $api_credentials['token']);
foreach ($results as $result) {
    foreach ($result['trace']['matchingActions'] as $matchingAction) {
        foreach ($matchingAction['dbOps'] as $dbOp) {
            if (isset($dbOp['oldJSON']['object']['stuff'])) {
                $purchase = array();
                $purchase['type'] = $types[$dbOp['oldJSON']['object']['stuff']['type_id']];
                $purchase['amount'] = $dbOp['oldJSON']['object']['stuff']['amount'];
                $purchase['price'] = $dbOp['oldJSON']['object']['price'];
                if (isset($dbOp['newJSON']['object']['stuff']['amount'])) {
                    $purchase['amount'] -= $dbOp['newJSON']['object']['stuff']['amount'];
                }
                $purchase['amount'] = formatAmount($purchase['amount'],0,$purchase['type']);
                $purchase['total'] = $purchase['amount'] * $purchase['price'];
                $purchases[] = $purchase;
            }
        }
    }
}

$grouped_purchases = groupMarketTransactions($purchases);
$purchase_details = getMarketTransactionDetails($grouped_purchases);

?>

<h1>Purchases By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
    <tr>
        <th>Type</th>
        <th>Number of Purchases</th>
        <th>Average Price</th>
        <th>Average Quantity Purchased</th>
        <th>Quantity Purchased</th>
        <th>Purchase Total</th>
    </tr>
    <?php
        $total = 0;
        foreach ($purchase_details as $type => $purchase) {
            //$amount = formatAmount($sale['total_amount_of_transactions'],0,$type);
            $total += $purchase['total_amount_of_transactions'];
            print "<tr>";
            print "<td>" . $type . "</td>";
            print "<td>" . $purchase['number_of_transactions'] . "</td>";
            print "<td>" . $purchase['average_transaction_price'] . "</td>";
            print "<td>" . $purchase['average_amount_transacted'] . "</td>";
            print "<td>" . $purchase['amount_transacted'] . "</td>";
            print "<td>" . $purchase['total_amount_of_transactions'] . "</td>";
            print "</tr>";
        }
    ?>
    <tr>
        <td colspan="5" align="right">Total:</td>
        <td><?php print (0-$total); ?></td>
    </tr>
</table>

<h1>Detailed Purchases By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
    <tr>
        <th>Type</th>
        <th>Quantity</th>
        <th>Price</th>
        <th>Total</th>
    </tr>
    <?php
        $total = 0;
        foreach ($purchases as $purchase) {
            $total += $purchase['total'];
            print "<tr>";
            print "<td>" . $purchase['type'] . "</td>";
            print "<td>" . $purchase['amount'] . "</td>";
            print "<td>" . $purchase['price'] . "</td>";
            print "<td>" . $purchase['total'] . "</td>";
            print "</tr>";
        }
    ?>
    <tr>
        <td colspan="3" align="right">Total:</td>
        <td><?php print (0-$total); ?></td>
    </tr>
</table>


<?php

$sales_transactions = array();
$q = 'account:prospectorsc db.key:account/prospectorsc/' . $loc_owner . ' action:mkpurchase notif:false';
$results = getGraphQLResults($q, '', $api_credentials['token']);

foreach ($results as $result) {
    $include = true;
    foreach ($result['trace']['matchingActions'] as $matchingAction) {
        // exclude purchases
        if ($matchingAction['authorization'][0]['actor'] == $loc_owner) {
            $include = false;
        }
        foreach ($matchingAction['dbOps'] as $dbOp) {
            // exclude referrals sales
            if (isset($dbOp['oldJSON']['object']['owner']) && $dbOp['oldJSON']['object']['owner'] != $loc_owner) {
                $include = false;
            }
        }
    }
    if ($include) {
        $sales_transactions[] = $result;
    }
}

$sales = array();
foreach ($sales_transactions as $transaction) {
    $sale = array();
    foreach ($transaction['trace']['matchingActions'] as $matchingAction) {
        foreach ($matchingAction['dbOps'] as $dbOp) {
            if (isset($dbOp['oldJSON']['object']['stuff'])) {
                $sale['type'] = $types[$dbOp['oldJSON']['object']['stuff']['type_id']];
                $sale['amount'] = $dbOp['oldJSON']['object']['stuff']['amount'];
                $sale['price'] = $dbOp['oldJSON']['object']['price'];
                if (isset($dbOp['newJSON']['object']['stuff']['amount'])) {
                    $sale['amount'] -= $dbOp['newJSON']['object']['stuff']['amount'];
                } else {
                    //pdump($transaction);
                }
                $sale['amount'] = formatAmount($sale['amount'],0,$sale['type']);
                $sale['total'] = $sale['amount'] * $sale['price'];
                $sales[] = $sale;
            }
        }
    }
}
$grouped_sales = groupMarketTransactions($sales);
$sales_details = getMarketTransactionDetails($grouped_sales);

?>

<h1>Sales By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
    <tr>
        <th>Type</th>
        <th>Number of Sales</th>
        <th>Average Price</th>
        <th>Average Quantity Sold</th>
        <th>Quantity Sold</th>
        <th>Sales Total</th>
    </tr>
    <?php
        $total = 0;
        foreach ($sales_details as $type => $sale) {
            $total += $sale['total_amount_of_transactions'];
            print "<tr>";
            print "<td>" . $type . "</td>";
            print "<td>" . $sale['number_of_transactions'] . "</td>";
            print "<td>" . $sale['average_transaction_price'] . "</td>";
            print "<td>" . $sale['average_amount_transacted'] . "</td>";
            print "<td>" . $sale['amount_transacted'] . "</td>";
            print "<td>" . $sale['total_amount_of_transactions'] . "</td>";
            print "</tr>";
        }
    ?>
    <tr>
        <td colspan="5" align="right">Total:</td>
        <td><?php print $total; ?></td>
    </tr>
</table>

<h1>Detailed Sales By Plot Owner (<?php print $loc_owner; ?>)</h1>
<table>
    <tr>
        <th>Type</th>
        <th>Quantity</th>
        <th>Price</th>
        <th>Total</th>
    </tr>
    <?php
        $total = 0;
        foreach ($sales as $sale) {
            $total += $sale['total'];
            print "<tr>";
            print "<td>" . $sale['type'] . "</td>";
            print "<td>" . $sale['amount'] . "</td>";
            print "<td>" . $sale['price'] . "</td>";
            print "<td>" . $sale['total'] . "</td>";
            print "</tr>";
        }
    ?>
    <tr>
        <td colspan="3" align="right">Total:</td>
        <td><?php print $total; ?></td>
    </tr>
</table>
    <br /><br /><a href="/history/">Click here to view your account history balance</a><br /><br /><br />
  </body>
</html>