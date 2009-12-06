<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-single.php - adds a single feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$url = $_REQUEST['url'];
$unread = $_REQUEST['unread'];

$error = fof_subscribe(fof_current_user(), $url, $unread);
if (preg_match('/HTTP 401/', $error))
    print "<script>
document.addform.basic_login.style.backgroundColor='#FFC0C0';
document.addform.basic_password.style.backgroundColor='#FFC0C0';
document.addform.basic_password.focus();
</script>";
print $error;
?>
