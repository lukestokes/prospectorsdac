<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
include '../includes/functions.php';
$account = '';
if (array_key_exists('account', $_GET)) {
    if (strlen(strip_tags($_GET['account'])) <= 12) {
        $account = strip_tags($_GET['account']);
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <title>Prospectors.io Account Balance History</title>
  </head>
  <body>
    <center>
        <img src="https://prospectors.io/assets/logo-c4a2df66ead25380fa80adac0b5f4c9bd8876c35f941a3dcbfe6b3a344ad6540.png">
        <h1>Account Balance History
        <?php
        if ($account != "") {
            print ' for ' . $account;
        }
        ?>
        </h1>
    </center>

<?php
$api_credentials = authenticateDFuse();

if ($account == '') {
?>
<div class="container">
    <form method="GET" action="">
        <div class="form-group">
                <label for="account">Prospectors (EOS) Account Name</label>
                <input name="account" type="text" class="form-control" id="account" placeholder="Enter your EOS account name">
        </div>
        <div class="form-group">
                <button type="submit" class="btn btn-primary">View Account Balance History</button>
        </div>
    </form>
    <p>
        This tool is (for now) free to try out. If you'd like to refresh the data again, please send 1 EOS to <code>lukeeosproxy</code>, after which you'll be able to refresh the data up to every hour for the next 30 days, at which point you'll have to pay again to continue using it. You can contact me in game as 1lukestokes1.
    </p>
    <p>
        Note: The first time you load your page, it may take aa few minutes or even time out.
    </p>
</div>
<?php
} else {

?>
<div class="container">
<?php

$required_payment_amount = 1; // amount required per month to refresh the data.
$owner = 'lukeeosproxy';

$cache_file_name = '../cache/_' . $account . '_last_cache_update.txt';
$last_cache_update = @file_get_contents($cache_file_name);
$update_cache = false;
if ($last_cache_update) {
    if (isset($_GET['refresh']) && $_GET['refresh'] == 1) {
        //if ((time() - $last_cache_update) > 3600) {
        if (true) {

//var_dump($api_credentials['token']);

            $payment_check = checkPayment($account, $required_payment_amount, $owner, $api_credentials['token']);
            if ($payment_check['paid']) {
                $update_cache = true;
                ?>
                <div class="alert alert-primary" role="alert">
                 Updating cache. Thank you for your payment of <?php print $payment_check['payment_amount']; ?> EOS on <?php print $payment_check['payment_date']; ?>
                </div>
                <?php
            } else {
                ?>
                <div class="alert alert-warning" role="alert">
                  We can't find a recent payment from your account. Please send <?php print $required_payment_amount; ?> EOS to <?php print $owner; ?> from <?php print $account; ?> in order to refresh this data. If you just made a payment, please wait a few minutes for it to be confirmed. Thank you.
                </div>
                <?php
                if ($payment_check['payment_date'] != '') {
                    ?>
                    <div class="alert alert-info" role="alert">
                        Your last payment was over 30 days ago on <?php print $payment_check['payment_date']; ?>
                    </div>
                    <?php
                }
            }
        } else {
            ?>
            <div class="alert alert-warning" role="alert">
              You can only update the cache every hour.
            </div>
            <?php
        }
    }
} else {
    $update_cache = true;
}

if ($update_cache) {
    clearEmptyCacheFiles();
    $last_cache_update = time();
    file_put_contents($cache_file_name,$last_cache_update);
}

?>
    <form method="GET" action="">
        <button type="submit" class="btn btn-primary">Reset</button>
    </form>
<?php

$my_workers = array();
$my_referrals = array();

$json = getTableDataCached("account", $api_credentials['token']);
$all_players = json_decode($json,true);
foreach ($all_players['rows'] as $index => $player) {
    if ($player['key'] == $account) {
        $my_workers[] = $player['json']['worker0'];
        $my_workers[] = $player['json']['worker1'];
        $my_workers[] = $player['json']['worker2'];
        //pdump($player);
    }
    if ($player['json']['referer'] == $account) {
        $my_referrals[] = $player['key'];
    }
}

$result = getAccountBalanceChanges($account, $api_credentials['token']);
$account_actions = $result['account_actions'];
$raw_data = $result['raw_data'];

//pdump($raw_data[288]);


foreach ($account_actions as $key => $account_action) {
    foreach ($raw_data as $transaction) {
        if ($transaction['lifecycle']['execution_trace']['id'] == $account_action['id']) {
            $account_actions[$key]['transaction_details'] = $transaction;
            break;
        }
    }
}


$net_profits = 0;
$balance = 0;
$referral_gains = 0;

$referral_totals = array();
?>

<p>
  <button class="btn btn-primary" type="button" data-toggle="collapse" data-target=".referrals">Toggle Referrals</button>
  <button class="btn btn-primary" type="button" data-toggle="collapse" data-target=".actions">Toggle Actions</button>
</p>

</div>

<div class="container">
<table class="table table-striped" id="balance_history">
    <tr>
        <th>Balance</th>
        <th>Change</th>
        <th>Activity</th>
        <th>Time</th>
        <th>Details</th>
    </tr>
<?php

$hide_referrals = true;

foreach ($account_actions as $key => $account_action) {
    // check if this was us or a referral
    $from_referrer = '';
    if (array_key_exists('transaction_details', $account_action)) {
        $check_other_dbops_for_referrer = true;
        foreach ($account_action['transaction_details']['lifecycle']['transaction']['actions'] as $action) {
            foreach ($action['authorization'] as $auth) {
                if (in_array($auth['actor'],$my_referrals)) {
                    $from_referrer = $auth['actor'];
                } elseif ($auth['actor'] == $account) {
                    $check_other_dbops_for_referrer = false;
                }
            }
        }
        if ($check_other_dbops_for_referrer && $from_referrer == "") {
            foreach ($account_action['transaction_details']['lifecycle']['other_dbops'] as $dbop) {
                if (in_array($dbop['key'],$my_referrals)) {
                    $from_referrer = $dbop['key'];
                }
            }
        }
/*
        if ($account_action['transaction_details']['lifecycle']['execution_trace']['id'] == 'dd1dc180a12748320da5a20c4eab9540bef71160319cf4aca661e96aee6ca154') {
            pdump($account_action);
        }
*/
    }

    $balance += $account_action['amount'];

    $row_class = "collapse show actions";
    if ($from_referrer != '') {
        $row_class = "collapse referrals";
    }

    if (in_array($account_action['activity'], array('deposit','withdraw'))) {
        $net_profits += $account_action['amount'];
        $row_class = "";
    }

    print "<tr class=\"" . $row_class . "\">";
    print "<td>" . $balance . "</td>";

    $amount_display = number_format($account_action['amount']);
    $font_class = 'text-success';
    if ($account_action['amount'] > 0) {
        $amount_display = "+" . number_format($account_action['amount']);
    } else {
        $font_class = 'text-danger';
    }
    print "<td class=\"" . $font_class . "\">";
    print $amount_display . "</td>";
    print "<td>";
    print $account_action['activity'];
    if ($from_referrer != '') {
        print " (referral: " . $from_referrer . ")";
        if (!array_key_exists($from_referrer, $referral_totals)) {
            $referral_totals[$from_referrer] = 0;
        }
        $referral_totals[$from_referrer] += $account_action['amount'];
        $referral_gains += $account_action['amount'];
    }
    print "</td>";
    print "<td><a href=\"https://eosq.app/tx/" . $account_action['id'] . "\">" . $account_action['block_time'] . "</a></td>";

?>
<td>
    <?php
        if ($account_action['details'] != '') {
    ?>

<p>
  <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#details_<?php print $key; ?>" aria-expanded="false" aria-controls="details_<?php print $key; ?>">
    Show Details
  </button>
</p>
<div class="collapse" id="details_<?php print $key; ?>">
  <div class="card card-body">
    <?php
        print "<table>";
        $details = json_decode($account_action['details'],true);
        foreach ($details as $key => $value) {
            print "<tr>";
            print "<td>" . $key . "</td>";
            print "<td>" . $value . "</td>";
            print "</tr>";
        }
        print "</table>";
    ?>
  </div>
</div>
</p>

    <?php
        }
    ?>

</td>
<?php
    print "</tr>";
}
print "</table>";

print "<h2>Net Profit/Loss: " . number_format((0-$net_profits)) . "</h2><br /><br />";


arsort($referral_totals);

if (count($referral_totals)) {
    print "<h2>Total Referral Gains: " . number_format($referral_gains) . "</h2>";

    print "<table class=\"table table-striped\">";
    foreach ($referral_totals as $referrer => $amount) {
        print "<tr>";
        print "<td>" . $referrer . "</td>";
        print "<td>" . number_format($amount) . "</td>";
        print "</tr>";
    }
    print "</table>";
}

$CachedDate = new DateTime();
$CachedDate->setTimestamp($last_cache_update);
print "Data last updated: <strong>" . $CachedDate->format('Y-m-d H:i:s T') . "</strong><form method=\"GET\" action=\"\"><input type=\"hidden\" name=\"account\" value=\"" . $account . "\"><input type=\"hidden\" name=\"refresh\" value=\"1\"><button type=\"submit\" class=\"btn btn-primary\">Refresh</button></form><br /><br />";

print "</div>";

}

?>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

  </body>
</html>