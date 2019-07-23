<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
setlocale(LC_MONETARY,"en_US.utf8");
include '../includes/functions.php';

$required_payment_amount = 1; // amount required per month to refresh the data.
$owner = 'lukeeosproxy';

$account = '';
if (array_key_exists('account', $_GET)) {
    if (strlen(strip_tags($_GET['account'])) <= 12) {
        $account = strtolower(strip_tags($_GET['account']));
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


<style>
.nth-table-number tr:nth-child(4n-1) {
    background: rgba(0,0,0,.05);
}
</style>

  </head>
  <body>
    <center>
        <a href="https://play.prospectors.io?ref=1lukestokes1"><img src="https://prospectors.io/assets/logo-c4a2df66ead25380fa80adac0b5f4c9bd8876c35f941a3dcbfe6b3a344ad6540.png"></a>
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

$token_prices = getTokenPrices($api_credentials['marketcapone_access_key']);

if ($account == '') {
?>
<div class="container">

<?php

if (array_key_exists('stats', $_GET) && $_GET['stats'] == "1") {
    $users = array();
    foreach (glob(__DIR__ . '/../cache/last_cache_update_*.txt') as $filename) {
        $eos_account = str_replace(array(__DIR__ . '/../cache/last_cache_update_','.txt'), array("",""), $filename);
        $timestamp = file_get_contents($filename);
        $users[$eos_account] = $timestamp;
    }
    arsort($users);
    print "<h2>" . count($users) . " Users</h2>";
    print "<table class=\"table table-striped\">";
    print "<tr><th>Account</th><th>Last Updated</th></tr>";
    foreach ($users as $key => $value) {
        print "<tr>";
        print "<td><a href=\"/history/?account=" . $key . "\">" . $key . "</a></td>";
        print "<td>" . date('Y-m-d H:i:s',$value) . "</td>";
        print "</tr>";
    }
    print "</table>";
}

?>
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
        This tool is (for now) free to try out. If you'd like to refresh the data, please send 1 EOS to <code>lukeeosproxy</code>, after which you can refresh the data up to every hour for the next 30 days. After that, just pay again if you'd like to keep getting the latest information. I plan to add more features as time allows. You can contact me in game as 1lukestokes1.
    </p>
    <p>
        Note: The first time you load your page, it may take a few minutes or even time out.
    </p>
    <p>
        If you're new to <a href="https://prospectors.io/">Prospectors</a> and don't yet have an EOS account, <a href="https://steemit.com/blockchain/@lukestokes/blockchain-gaming-for-cryptocurrency-onboarding">check this post to get started</a>. Please use this referral link to sign up: <a href="https://play.prospectors.io?ref=1lukestokes1">https://play.prospectors.io?ref=1lukestokes1</a>.
    </p>
</div>
<?php
} else {

?>
<div class="container">
<?php

if ($token_prices['EOS'] && $token_prices['PGL']) {
    ?>
    <a href="https://marketcap.one/">
    <span class="badge badge-pill badge-primary">PGL <span class="badge badge-light"><?php print $token_prices['PGL']; ?> EOS</span></span>
    <span class="badge badge-pill badge-primary">EOS <span class="badge badge-light">$<?php print $token_prices['EOS']; ?></span></span>
    </a>
    <?php
}

$cache_file_name = __DIR__ . '/../cache/last_cache_update_' . $account . '.txt';
$last_cache_update = @file_get_contents($cache_file_name);
$update_cache = false;
if ($last_cache_update) {
    if (isset($_GET['refresh']) && $_GET['refresh'] == 1) {
        if ((time() - $last_cache_update) > 3600) {
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
    clearEmptyCacheFiles($account);
    $last_cache_update = time();
    file_put_contents($cache_file_name,$last_cache_update);
}

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

if (!count($my_workers)) {
    ?>
    <div class="alert alert-warning" role="alert">
      Prospectors account <strong><?php print $account; ?></strong> not found.
    </div>
    <?php
} else {

$result = getAccountBalanceChanges($account, $api_credentials['token']);
$account_actions = $result['account_actions'];
$raw_data = $result['raw_data'];

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
$daily_summary = array();
$daily_balance = 0;
$referral_totals = array();

$transfers = array();
?>

</div>

<?php

foreach ($account_actions as $key => $account_action) {
    // daily summary
    $BlockTime = getDateTimeFromBlockTime($account_action['block_time']);
    $block_day = $BlockTime->format('Y-m-d');
    if (!array_key_exists($block_day, $daily_summary)) {
        $daily_summary[$block_day] = array('balance' => $daily_balance, 'change' => 0, 'transactions' => array());
    }
    $daily_balance += $account_action['amount'];
    $daily_summary[$block_day]['change'] += $account_action['amount'];
    $daily_summary[$block_day]['balance'] = $daily_balance;

    // transaction details
    $transaction = array();
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
    }
    if (in_array($account_action['activity'], array('deposit','withdraw'))) {
        $transfers[] = $account_action;
        $net_profits += $account_action['amount'];
    }
    $balance += $account_action['amount'];
    $transaction['is_referral'] = ($from_referrer != '');
    $transaction['balance'] = $balance;
    $transaction['amount'] = $account_action['amount'];
    $transaction['activity'] = getActivityDescription($account_action['activity'],$account_action['amount']);
    if ($from_referrer != '') {
        $transaction['activity'] = "Referral " . $from_referrer . " " . $transaction['activity'];
        if (!array_key_exists($from_referrer, $referral_totals)) {
            $referral_totals[$from_referrer] = 0;
        }
        $referral_totals[$from_referrer] += $account_action['amount'];
        $referral_gains += $account_action['amount'];
    }
    $transaction['time'] = "<a target=\"_new\" href=\"https://eosq.app/tx/" . $account_action['id'] . "\">" . $account_action['block_time'] . "</a>";

    $details_display = '';
    if ($account_action['details'] != '') {
        $details = json_decode($account_action['details'],true);
        $details_display .= "<table>";
        foreach ($details as $key => $value) {
            $key_display = $key;
            $value_display = $value;
            if ($key == 'type_id') {
                $key_display = 'type';
                $value_display = $types[$value];
            }
            $details_display .= "<tr>";
            $details_display .= "<td>" . $key_display . "</td>";
            $details_display .= "<td>";
            if (is_array($value)) {
                foreach ($value as $inner_key => $inner_value) {
                    $inner_key_display = $inner_key;
                    $inner_value_display = $inner_value;
                    if ($inner_key == 'type_id') {
                        $inner_key_display = 'type';
                        $inner_value_display = $types[$inner_value];
                    }
                    $details_display .= $inner_key_display . ": " . $inner_value_display . "<br />";
                }
            } else {
                $details_display .= $value_display;
            }
        }
        $details_display .= "</table>";
    }
    $transaction['details'] = $details_display;

    $daily_summary[$block_day]['transactions'][] = $transaction;
}

?>
<div class="container">
<table class="table nth-table-number">
    <tr>
        <th>Day</th>
        <th class="text-right">Balance</th>
        <th class="text-right">Change</th>
        <th class="text-right">Transaction Count</th>
        <th class="text-right">Show Transactions</th>
    </tr>
<?php
foreach ($daily_summary as $day => $summary) {
    print "<tr class=\"collapse\" id=\"transactions_" . $day . "\">";
    print "<td colspan=\"5\">";
    ?>
    <table class="table table-striped">
    <tr>
        <th class="text-right">Balance</th>
        <th class="text-right">Change</th>
        <th>Activity</th>
        <th>Time</th>
        <th class="text-right">Show Details</th>
    </tr>
    <?php
    foreach ($summary['transactions'] as $key => $transaction) {
        print "<tr>";
        print "<td class=\"text-right\">" . number_format($transaction['balance']) . "</td>";
        $font_class = 'text-danger';
        $amount_display = number_format($transaction['amount']);
        if ($transaction['amount'] > 0) {
            $font_class = 'text-success';
            $amount_display = "+" . number_format($transaction['amount']);
        }
        print "<td class=\"text-right " . $font_class . "\">";
        print $amount_display . "</td>";
        print "<td>" . $transaction['activity'] . "</td>";
        print "<td>" . $transaction['time'] . "</td>";
?>
<td class="text-right">
    <?php
        if ($transaction['details'] != '') {
    ?>
<p>
  <button class="btn btn-outline-primary btn-sm" type="button" data-toggle="collapse" data-target="#details_<?php print $day . $key; ?>" aria-expanded="false" aria-controls="details_<?php print $day . $key; ?>">Toggle</button>
</p>
<div class="collapse" id="details_<?php print $day . $key; ?>">
  <div class="card card-body">
    <?php
    print $transaction['details'];
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
print "</td>";
print "</tr>";

    print "<tr class=\"highlight\">";
    print "<td>" . $day . "</td>";
    print "<td class=\"text-right\">" . number_format($summary['balance']) . "</td>";
    $font_class = 'text-danger';
    $amount_display = number_format($summary['change']);
    if ($summary['change'] > 0) {
        $font_class = 'text-success';
        $amount_display = "+" . number_format($summary['change']);
    }
    print "<td class=\"text-right " . $font_class . "\">";
    print $amount_display . "</td>";
    print "<td class=\"text-right\">" . count($summary['transactions']) . "</td>";
    print "<td class=\"text-right\"><button class=\"btn btn-primary\" type=\"button\" data-toggle=\"collapse\" data-target=\"#transactions_" . $day . "\" aria-expanded=\"false\" aria-controls=\"#transactions_" . $day . "\">Toggle</button></td>";
    print "</tr>";
}
?>
<tr>
    <td><strong>Current Balance:</strong></td>
    <td class="text-right"><?php print number_format($daily_balance); ?></td>
    <td colspan="3"><?php print getGoldToTokenValue($daily_balance,$token_prices); ?></td>
</tr>
</table>

<table class="table table-striped">
<tr>
    <th class="text-right">Net</th>
    <th class="text-right">Change</th>
    <th>Transfer Time</th>
</tr>

<?php
print "<h2>Net Profit/Loss: " . number_format((0-$net_profits)) . getGoldToTokenValue((0-$net_profits),$token_prices) . "</h2>";

$net_balance = 0;
foreach ($transfers as $transfer) {
    $change = (0-$transfer['amount']);
    $net_balance += $change;
    print "<tr>";
    print "<td class=\"text-right\">" . number_format($net_balance) . "</td>";
    $font_class = 'text-danger';
    $amount_display = number_format($change);
    if ($change > 0) {
        $font_class = 'text-success';
        $amount_display = "+" . number_format($change);
    }
    print "<td class=\"text-right " . $font_class . "\">" . $amount_display . "</td>";
    print "<td><a  target=\"_new\" href=\"https://eosq.app/tx/" . $transfer['id'] . "\">" . $transfer['block_time'] . "</a></td>";
    print "</tr>";
}
print "</table>";

arsort($referral_totals);

if (count($referral_totals)) {
    print "<h2>Total Referral Gains: " . number_format($referral_gains) . getGoldToTokenValue($referral_gains,$token_prices) . "</h2>";

    print "<table class=\"table table-striped col-md-6\">";
    foreach ($referral_totals as $referrer => $amount) {
        print "<tr>";
        print "<td>" . $referrer . "</td>";
        print "<td class=\"text-right\">" . number_format($amount) . "</td>";
        print "</tr>";
    }
    print "</table>";
}

$CachedDate = new DateTime();
$CachedDate->setTimestamp($last_cache_update);
print "Data last updated: <strong>" . $CachedDate->format('Y-m-d H:i:s T') . "</strong><form method=\"GET\" action=\"\"><input type=\"hidden\" name=\"account\" value=\"" . $account . "\"><input type=\"hidden\" name=\"refresh\" value=\"1\"><button type=\"submit\" class=\"btn btn-primary\">Refresh</button></form><br /><br />";

print "</div>";

} // account found

?>
    <div class="container">
        <form method="GET" action="">
            <button type="submit" class="btn btn-primary">Select Different Account</button>
        </form>
        <br /><br /><br /><br /><br />
    </div>
<?php

} // $account set

?>
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

  </body>
</html>