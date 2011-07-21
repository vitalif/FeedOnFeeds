<?php

$fof_no_login = 1;
include_once("fof-main.php");

$sudo_id = $_GET['id'];
if ($sudo_id)
{
    $user_id = fof_cache_get("sudo-$sudo_id");
    fof_cache_unset("sudo-$sudo_id");
}

if ($user_id)
    $user = fof_db_get_user(NULL, $user_id);

if ($user)
{
    $prefs = unserialize($user['user_prefs']);
    $globalauth = $prefs['globalauth'];
    if (!$globalauth)
        $globalauth = array('user_name' => $user['user_name']);
}
else
    $globalauth = array('error' => "FOF_SUDO authorization error: session id $sudo_id is unknown");

print json_encode($globalauth);
