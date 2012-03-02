<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * login.php - username / password entry
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 */

ob_start();

$fof_no_login = true;

require_once("fof-main.php");

fof_set_content_type();

if(isset($_POST["user_name"]) && isset($_POST["user_password"]))
{
    if(fof_authenticate($_POST['user_name'], md5($_POST['user_password'] . $_POST['user_name'])))
    {
        Header("Location: .");
        exit();
    }
    $failed = true;
}

/* Site stats */
$users = fof_db_get_value("SELECT COUNT(*) FROM fof_user");
$feeds = fof_db_get_value("SELECT COUNT(*) FROM fof_feed");
$items = fof_db_get_value("SELECT COUNT(*) FROM fof_item");

if ($fof_prefs_obj && ($days = intval($fof_prefs_obj->admin_prefs['topreaders_days'])) && ($count = intval($fof_prefs_obj->admin_prefs['topreaders_count'])))
    $topreaders = fof_db_get_top_readers($days, $count);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title>Feed on Feeds - Log on</title>
<style>
body { font-family: georgia; font-size: 16px; text-align: center; }
div { background: #eee; border: 1px solid black; width: 20em; margin: 5em auto 2em; padding: 1.5em; }
form { margin: 0 0 0 -3px; }
</style>
</head>

<body>
<div>
	<form action="login.php" method="POST">
		<center><a href="http://feedonfeeds.com/" style="font-size: 20px; font-family: georgia;">Feed on Feeds</a></center><br>
		User name:<br><input class="editbox" type='string' name='user_name' style='font-size: 16px; width: 20em'><br><br>
		Password:<br><input class="editbox" type='password' name='user_password' style='font-size: 16px; width: 20em'><br><br>
		<p style="text-align: right; margin: 0"><input type="submit" value="Log on!" style='font-size: 16px'></p>
		<?php if($failed) echo '<br><center><font color="red"><b>Incorrect user name or password</b></font></center>'; ?>
		<center style="padding: 20px 0 0 0; font-size: 75%"><?= "As of ".date("Y-m-d").", $users&nbsp;our&nbsp;users subscribed to $feeds&nbsp;unique&nbsp;feeds with $items&nbsp;items." ?></center>
	</form>
</div>
<?php if($topreaders) { ?>
<p><?=$days > 1 ? "Last $days days" : "Today's"?> top readers:<br />
<?php foreach($topreaders as $t) { ?>
<b><?=htmlspecialchars($t['user_name'])?></b>: <?=$t['posts']?> posts read<br />
<? } ?>
</p>
<? } ?>
</body>

</html>
