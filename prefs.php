<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * prefs.php - display and change preferences
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$prefs = FoF_Prefs::instance();

if (fof_is_admin() && isset($_POST['adminprefs']))
{
    $prefs->set('purge', $_POST['purge']);
    $prefs->set('manualtimeout', $_POST['manualtimeout']);
    $prefs->set('autotimeout', $_POST['autotimeout']);
    $prefs->set('logging', !empty($_POST['logging']));
    $prefs->set('suggestadd', intval($_POST['suggestadd']));
    $prefs->set('topreaders_days', intval($_POST['topreaders_days']));
    $prefs->set('topreaders_count', intval($_POST['topreaders_count']));

    $prefs->save();

    $message[] = 'Saved admin prefs.';

    if($prefs->get('logging') && !@fopen("fof.log", 'a'))
    {
        $message[] = 'Warning: could not write to log file!';
    }
}

if (isset($_REQUEST['tagfeeds']))
{
    $allow_prop = array('untag' => 1, 'tag' => 1, 'filter' => 1, 'title' => 1, 'hide' => 1, 'orighide' => 1);
    foreach ($_REQUEST as $k => $v)
    {
        $prop = explode('_', $k);
        if (count($prop) < 2)
            continue;
        list($prop, $feed_id) = $prop;
        if (empty($allow_prop[$prop]))
            continue;
        if (!($feed = fof_db_get_feed_by_id($feed_id)))
            continue;
        // remove tags
        if ($prop == 'untag')
        {
            foreach ($v as $tag)
            {
                fof_untag_feed(fof_current_user(), $feed_id, $tag);
                $message[] = 'Dropped \''.$tag.'\' from \''.htmlspecialchars($_REQUEST["title_$feed_id"]).'\'';
            }
        }
        // add tags
        elseif ($prop == 'tag')
        {
            foreach (preg_split("/[\s,]*,[\s,]*/", $v) as $tag)
            {
                if ($tag)
                {
                    fof_tag_feed(fof_current_user(), $feed_id, $tag);
                    $message[] = 'Tagged \''.htmlspecialchars($_REQUEST["title_$feed_id"]).'\' as '.htmlspecialchars($tag);
                }
            }
        }
        // change filter
        elseif ($prop == 'filter')
        {
            if (fof_db_set_feedprop(fof_current_user(), $feed_id, 'filter', $v))
                $message[] = 'Set filter \''.htmlspecialchars($v).'\' for feed \''.htmlspecialchars($_REQUEST["title_$feed_id"]).'\'';
        }
        // rename feed
        else if ($prop == 'title' && $v != $_POST['origtitle_'.$feed_id])
        {
            if ($feed['feed_title'] == $v)
                $v = '';
            if (fof_db_set_feedprop(fof_current_user(), $feed_id, 'feed_title', $v))
            {
                if ($v)
                    $message[] = 'Renamed feed \''.htmlspecialchars($feed['feed_title']).'\' to \''.htmlspecialchars($v).'\'';
                else
                    $message[] = 'Feed title resetted for \''.htmlspecialchars($feed['feed_title']).'\'';
            }
        }
        // show item content by default
        else if ($prop == 'hide' && $v && empty($_POST['orighide_'.$feed_id]))
        {
            if (fof_db_set_feedprop(fof_current_user(), $feed_id, 'hide_content', true))
                $message[] = 'Items of feed \''.htmlspecialchars($_REQUEST["title_$feed_id"]).'\' will be shown collapsed by default';
        }
        // hide item content by default
        else if ($prop == 'orighide' && $v && empty($_POST['hide_'.$feed_id]))
        {
            if (fof_db_set_feedprop(fof_current_user(), $feed_id, 'hide_content', false))
                $message[] = 'Items of feed \''.htmlspecialchars($_REQUEST["title_$feed_id"]).'\' will be shown expanded by default';
        }
    }
}

if (!empty($message))
    $message = join('<br>', $message);

if(isset($_POST['prefs']))
{
    $prefs->set('favicons', isset($_POST['favicons']));
    $prefs->set('keyboard', isset($_POST['keyboard']));
    $prefs->set('tzoffset', intval($_POST['tzoffset']));
    $prefs->set('dst', isset($_POST['dst']));
    $prefs->set('howmany', intval($_POST['howmany']));
    $prefs->set('order', $_POST['order']);
    $prefs->set('sharing', $_POST['sharing']);
    $prefs->set('sharedname', $_POST['sharedname']);
    $prefs->set('sharedurl', $_POST['sharedurl']);

    $prefs->save(fof_current_user());

    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
        fof_db_change_password($fof_user_name, $_POST['password']);
        setcookie ("user_password_hash", md5($_POST['password'] . $fof_user_name), time()+60*60*24*365*10);
        $message = "Updated password.";
    }
    else if($_POST['password'] || $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }

    $message .= ' Saved prefs.';
}

if(isset($_POST['plugins']))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
        $key = $plugin_pref[1];
        $prefs->set($key, $_POST[$key]);
    }

    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
        if(ereg('\.php$',$file))
            $plugins[] = substr($file, 0, -4);

    closedir();

    foreach($plugins as $plugin)
        $prefs->set("plugin_" . $plugin, $_POST[$plugin] != "on");

    $prefs->save(fof_current_user());

    $message .= ' Saved plugin prefs.';
}

if(isset($_POST['changepassword']))
{
    if($_POST['password'] != $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
    else
    {
        $username = $_POST['username'];
        $password = $_POST['password'];
        fof_db_change_password($username, $password);

        $message = "Changed password for $username.";
    }
}

if(fof_is_admin() && isset($_POST['adduser']) && $_POST['username'] && $_POST['password'])
{
    $username = $_POST['username'];
    $password = $_POST['password'];

    fof_db_add_user($username, $password);
    $message = "User '$username' added.";
}

if(fof_is_admin() && isset($_POST['deleteuser']) && $_POST['username'])
{
    $username = $_POST['username'];

    fof_db_delete_user($username);
    $message = "User '$username' deleted.";
}

include("header.php");

?>

<?php if(isset($message)) { ?>

<br><font color="red"><?php echo $message ?></font><br>

<?php } ?>

<br><h1>Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Default display order: <select name="order"><option value=desc>new to old</option><option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option></select><br><br>
Number of items in paged displays: <input type="string" name="howmany" value="<?php echo $prefs->get('howmany') ?>"><br><br>
Display custom feed favicons? <input type="checkbox" name="favicons" <?php if($prefs->get('favicons')) echo "checked=true"; ?> ><br><br>
Use keyboard shortcuts? <input type="checkbox" name="keyboard" <?php if($prefs->get('keyboard')) echo "checked=true";?> ><br><br>
Time offset in hours: <input size=3 type=string name=tzoffset value="<?php echo $prefs->get('tzoffset')?>"> <input type="checkbox" name="dst" <?php if($prefs->get('dst')) echo "checked=true";?> /> use <a href="http://en.wikipedia.org/wiki/Daylight_saving_time">DST</a> &nbsp; (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + ($prefs->get("tzoffset") + ($prefs->get('dst') ? date('I') : 0))*60*60) ?>)<br><br>
<table border=0 cellspacing=0 cellpadding=2><tr><td>New password:</td><td><input type=password name=password> (leave blank to not change)</td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<br>

Share
<select name="sharing">
<option value=no>no</option>
<option value=all <?php if($prefs->get('sharing') == "all") echo "selected";?>>all</option>
<option value=shared <?php if($prefs->get('sharing') == "shared") echo "selected";?>>tagged as "shared"</option>
<option value=star <?php if($prefs->get('sharing') == "star") echo "selected";?>>starred</option>
</select>
items.
<?php if($prefs->get('sharing') != "no") echo " <small><i>(your shared page is <a href='./shared.php?user=$fof_user_id'>here</a>)</i></small>";?><br><br>
Name to be shown on shared page: <input type=string name=sharedname value="<?php echo $prefs->get('sharedname')?>"><br><br>
URL to be linked on shared page: <input type=string name=sharedurl value="<?php echo $prefs->get('sharedurl')?>">
<br><br>

<input type=submit name=prefs value="Save Preferences">
</form>

<br><h1>Feed on Feeds - Plugin Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">

<?php
    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file = readdir($dirlist))
    {
        fof_log("considering " . $file);
        if(substr($file, -4) === '.php' && is_readable(FOF_DIR . "/plugins/" . $file))
            $plugins[] = substr($file, 0, -4);
    }
    closedir();
?>

<?php foreach($plugins as $plugin) { ?>
<input type="checkbox" name="<?php echo $plugin ?>" <?php if(!$prefs->get("plugin_" . $plugin)) echo "checked"; ?>> Enable plugin <tt><?php echo $plugin?></tt>?<br>
<?php } ?>

<br>
<?php foreach(fof_get_plugin_prefs() as $plugin_pref) { $name = $plugin_pref[0]; $key = $plugin_pref[1]; $type = $plugin_pref[2]; ?>
<?php echo $name ?>:

<?php if($type == "boolean") { ?>
<input name="<?php echo $key ?>" type="checkbox" <?php if($prefs->get($key)) echo "checked" ?>><br>
<?php } else { ?>
<input name="<?php echo $key ?>" value="<?php echo $prefs->get($key)?>"><br>
<?php } } ?>
<br>
<input type="submit" name="plugins" value="Save Plugin Preferences">
</form>

<br><h1>Feed on Feeds - Feeds, Tags and Filters</h1>
<p style="font-size: 90%"><font color=red>*</font> Check 'Hide' if you want to hide contents of items of the corresponding feed by default.<br />
Click 'Filter' and enter a regular expression to filter out items matching it directly into "already read" state.<br />
Don't forget to Save preferences after making changes :-)</p>
<div style="border: 1px solid black; margin: 10px; padding: 10px; font-size: 12px; font-family: verdana, arial;">
<form method="post" action="?tagfeeds=1">
<table cellpadding="3" cellspacing="0" class="feedprefs">
<tr valign="top">
    <th colspan="2" align="left">Feed</th>
    <th>Remove tags</th>
    <th>Add tags<br><small style='font-weight: normal'>(separate with ,)</small></th>
    <th>Preferences</th>
</tr>
<?php
foreach($feeds as $row)
{
    $id = $row['feed_id'];
    $url = $row['feed_url'];
    $title = fof_feed_title($row);
    $link = $row['feed_link'];
    $description = $row['feed_description'];
    $age = $row['feed_age'];
    $unread = $row['feed_unread'];
    $starred = $row['feed_starred'];
    $items = $row['feed_items'];
    $agestr = $row['agestr'];
    $agestrabbr = $row['agestrabbr'];
    $lateststr = $row['lateststr'];
    $lateststrabbr = $row['lateststrabbr'];
    $tags = $row['tags'];

    if(++$t % 2)
        print '<tr class="odd-row">';
    else
        print '<tr>';

    if($row['feed_image'] && $prefs->get('favicons')) { ?>
        <td><a href="<?=$url?>" title="<?=htmlspecialchars($row['feed_title'])?>"><img src='<?=$row['feed_image']?>' width='16' height='16' border='0' /></a></td>
    <? } else { ?>
        <td><a href="<?=$url?>" title="<?=htmlspecialchars($row['feed_title'])?>"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a></td>
    <? } ?>

    <td><input type="hidden" name="origtitle_<?=$id?>" value="<?=htmlspecialchars($title)?>" /><input class="editbox" type="text" name="title_<?=$id?>" value="<?=htmlspecialchars($title)?>" size="50" /> <a href="<?=$link?>" title="home page"><img src="image/external.png" alt=" " width="10" height="10" /></a</td>
    <td align=right>

    <?
    if($tags)
    {
        $i = 0;
        foreach($tags as $tag)
        {
            $utag = htmlspecialchars($tag);
            print "<span id='t{$id}_{$i}'>$tag</span> <input onclick='document.getElementById(\"t{$id}_{$i}\").style.textDecoration=this.checked ? \"line-through\" : \"\"' type='checkbox' name='untag_{$id}[]' value='$utag'>";
            $i++;
        }
    }
    $flt = isset($row['prefs']['filter']) ? htmlspecialchars($row['prefs']['filter']) : '';
    ?>
    </td>
    <td><input class="editbox" type="text" name="tag_<?=$id?>" /></td>
    <td>
        <input type="hidden" name="orighide_<?=$id?>" value="<?=$row['prefs']['hide_content'] ? 1 : 0?>" />
        <input type="checkbox" value="1" name="hide_<?=$id?>" title="Hide item content by default" <?= !empty($row['prefs']['hide_content']) ? "checked" : ""?> /><label for="hide_<?=$id?>" title="Hide item content by default">Hide</label> |
        <span id="fspan<?=$id?>" style="display:none">Filter: <input class="editbox" type="text" name="filter_<?=$id?>" value="<?=$flt?>" /></span>
        <span id="ftspan<?=$id?>"><a id="fa<?=$id?>" href="javascript:show_filter('<?=$id?>')">Filter</a><?=$flt ? ": $flt" : ""?></span>
    </td>
</tr>
<? } ?>
</table>
<input type="submit" value="Save feed preferences" />
</form>
</div>

<?php if(fof_is_admin()) { ?>

<br><h1>Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Enable logging? <input type=checkbox name=logging <?php if($prefs->get('logging')) echo "checked" ?> /><br><br>
Purge read items after <input size=4 type=string name=purge value="<?php echo $prefs->get('purge')?>" /> days (leave blank to never purge)<br><br>
Allow automatic feed updates every <input size=4 type=string name=autotimeout value="<?php echo $prefs->get('autotimeout')?>" /> minutes<br><br>
Allow manual feed updates every <input size=4 type=string name=manualtimeout value="<?php echo $prefs->get('manualtimeout')?>" /> minutes<br><br>
Show <b>Top <input size=3 type=string name=topreaders_count value="<?=intval($prefs->get('topreaders_count'))?>" /> readers in last <input size=3 type=string name=topreaders_days value="<?=intval($prefs->get('topreaders_days'))?>" /> days</b> statistics on the login page<br><br>
Suggest users to subscribe to <input size=3 type=string name=suggestadd value="<?=intval($prefs->get('suggestadd'))?>" /> most popular feeds<br><br>
<input type=submit name=adminprefs value="Save Options" />
</form>

<br><h1>Add User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Username: <input type=string name=username> Password: <input type=string name=password> <input type=submit name=adduser value="Add user">
</form>

<?php
    $result = fof_db_query("select user_name from $FOF_USER_TABLE where user_id > 1");

    $delete_options = '';
    while($row = fof_db_get_row($result))
    {
        $username = $row['user_name'];
        $delete_options .= "<option value=$username>$username</option>";
    }

    if(isset($delete_options))
    {
?>

<br><h1>Delete User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Delete User - Are you sure?')">
<select name=username><?php echo $delete_options ?></select>
<input type=submit name=deleteuser value="Delete user"><br>
</form>

<br><h1>Change User's Password</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Change Password - Are you sure?')">
<table border=0 cellspacing=0 cellpadding=2>
<tr><td>Select user:</td><td><select name=username><?php echo $delete_options ?></select></td></tr>
<tr><td>New password:</td><td><input type=password name=password></td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<input type=submit name=changepassword value="Change"><br>
</form>

<?php } ?>

<br>
<form method="get" action="uninstall.php" onsubmit="return confirm('Really?  This will delete all the database tables!')">
<center><input type=submit name=uninstall value="Uninstall Feed on Feeds" style="background-color: #ff9999"></center>
</form>

<?php } ?>

<?php include("footer.php") ?>
