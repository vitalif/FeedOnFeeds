<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * install.php - creates tables and cache directory, if they don't exist
 *
 * Copyright (C) 2004-2007 Stephen Minutillo steve@minutillo.com http://minutillo.com/steve/
 *           (C) 2009-2014 Vitaliy Filippov vitalif@mail.ru http://yourcmc.ru/wiki/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;
$fof_installer = true;

include_once("fof-main.php");

fof_set_content_type();

// compatibility testing code lifted from SimplePie

function get_curl_version()
{
    if (is_array($curl = curl_version()))
        $curl = $curl['version'];
    else if (preg_match('/curl\/(\S+)(\s|$)/', $curl, $match))
        $curl = $match[1];
    else
        $curl = 0;
    return $curl;
}

$php_ok = (function_exists('version_compare') && version_compare(phpversion(), '4.3.2', '>='));
$xml_ok = extension_loaded('xml');
$pcre_ok = extension_loaded('pcre');
$mysql_ok = extension_loaded('mysqli');

$curl_ok = (extension_loaded('curl') && version_compare(get_curl_version(), '7.10.5', '>='));
$zlib_ok = extension_loaded('zlib');
$mbstring_ok = extension_loaded('mbstring');
$iconv_ok = extension_loaded('iconv');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title>feed on feeds - installation</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" href="fof.css" media="screen" />
    <script src="fof.js" type="text/javascript"></script>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
    <style>
    body
    {
        font-family: georgia;
        font-size: 16px;
    }
    div
    {
        background: #eee;
        border: 1px solid black;
        width: 75%;
        margin: 5em auto;
        padding: 1.5em;
    }
    hr
    {
        height:0;
        border:0;
        border-top:1px solid #999;
    }
    .fail { color: red; }
    .pass { color: green; }
    .warn { color: #a60; }
    </style>
</head>

<body><div><center style="font-size: 20px;"><a href="http://feedonfeeds.com/">Feed on Feeds</a> - Installation</center><br>

<?php
if (!empty($_GET['password']))
{
    if ($_GET['password'] == $_GET['password2'])
    {
        $password_hash = md5($_GET['password'] . 'admin');
        fof_safe_query("insert into $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) values (1, 'admin', '%s', 'admin')", $password_hash);
        echo '<center><b>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b></div></body></html>';
    }
    else
    {
        echo '<center><font color="red">Passwords do not match!</font></center><br><br>';
    }
}
else
{
?>

Checking compatibility...
<?php
if($php_ok) echo "<span class='pass'>PHP ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP version is too old!</span>  Feed on Feeds requires at least PHP 4.3.2.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($xml_ok) echo "<span class='pass'>XML ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the XML extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($pcre_ok) echo "<span class='pass'>PCRE ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the PCRE extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($mysql_ok) echo "<span class='pass'>MySQL ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the MySQLi extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($curl_ok) echo "<span class='pass'>cURL ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is either missing the cURL extension, or it is too old!</span>  cURL version 7.10.5 or later is required to be able to subscribe to https or digest authenticated feeds.<br>";
}

if($zlib_ok) echo "<span class='pass'>Zlib ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the Zlib extension!</span>  Feed on Feeds will not be able to save bandwidth by requesting compressed feeds.<br>";
}

if($iconv_ok) echo "<span class='pass'>iconv ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the iconv extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

if($mbstring_ok) echo "<span class='pass'>mbstring ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the mbstring extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

?>
<br>Minimum requirements met!
<hr>

Creating tables...
<?php

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_FEED_TABLE` (
  `feed_id` int(11) NOT NULL auto_increment,
  `feed_url` text NOT NULL,
  `feed_title` text NOT NULL,
  `feed_link` text NOT NULL,
  `feed_description` text NOT NULL,
  `feed_image` text,
  `feed_image_cache_date` int(11) default '0',
  `feed_cache_date` int(11) default '0',
  `feed_cache_attempt_date` int(11) default '0',
  `feed_cache` text,
  PRIMARY KEY (`feed_id`)
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_TAG_TABLE` (
  `tag_id` int(11) NOT NULL auto_increment,
  `tag_name` char(100) NOT NULL default '',
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY (`tag_name`)
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_USER_TABLE` (
  `user_id` int(11) NOT NULL auto_increment,
  `user_name` varchar(100) NOT NULL default '',
  `user_password_hash` varchar(32) NOT NULL default '',
  `user_level` enum('user','admin') NOT NULL default 'user',
  `user_prefs` text,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TABLE` (
  `item_id` int(11) NOT NULL auto_increment,
  `feed_id` int(11) NOT NULL default '0',
  `item_guid` text NOT NULL,
  `item_link` text NOT NULL,
  `item_cached` int(11) NOT NULL default '0',
  `item_published` int(11) NOT NULL default '0',
  `item_updated` int(11) NOT NULL default '0',
  `item_title` text NOT NULL,
  `item_author` text NOT NULL,
  `item_content` text NOT NULL,
  PRIMARY KEY  (`item_id`),
  KEY `item_guid` (`item_guid`(255)),
  KEY `feed_id_item_cached` (`feed_id`,`item_cached`),
  KEY `item_published` (`item_published`),
  FOREIGN KEY (`feed_id`) REFERENCES `$FOF_FEED_TABLE` (`feed_id`) ON UPDATE CASCADE
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TAG_TABLE` (
  `user_id` int(11) NOT NULL default '0',
  `item_id` int(11) NOT NULL default '0',
  `tag_id` int(11) NOT NULL default '0',
  `item_published` int(11) NOT NULL default '0',
  `feed_id` int(11) NOT NULL default '0',
  PRIMARY KEY (`tag_id`,`user_id`,`item_id`),
  KEY `tag_id_user_id_item_published_item_id` (tag_id, user_id, item_published, item_id),
  KEY `tag_id_user_id_feed_id` (tag_id, user_id, feed_id),
  KEY `item_id_user_id_tag_id` (item_id, user_id, tag_id),
  FOREIGN KEY (`tag_id`) REFERENCES `$FOF_TAG_TABLE` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `$FOF_USER_TABLE` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `$FOF_ITEM_TABLE` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`feed_id`) REFERENCES `$FOF_FEED_TABLE` (`feed_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_SUBSCRIPTION_TABLE` (
  `feed_id` int(11) NOT NULL default '0',
  `user_id` int(11) NOT NULL default '0',
  `subscription_prefs` text,
  PRIMARY KEY (`feed_id`,`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `$FOF_USER_TABLE` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`feed_id`) REFERENCES `$FOF_FEED_TABLE` (`feed_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;
EOQ;

foreach($tables as $table)
    fof_db_query($table, 1);

?>
Tables exist.<hr>

Upgrading schema...
<?php

function add_fk($show_create_table, $table, $column, $ref_table, $action = 'on delete cascade on update cascade')
{
    if (!strpos($show_create_table, "FOREIGN KEY (`$column`)"))
        fof_db_query("alter table $table add foreign key ($column) references $ref_table ($column) $action");
}

$r = fof_db_query("show table status");
while ($row = fof_db_get_row($r))
{
    $table = $row['Name'];
    $alter = array();
    if (strtolower($row['Engine']) === 'myisam')
        $alter[] = 'engine=innodb';
    if (strpos($row['Collation'], 'utf8') === false)
        $alter[] = 'convert to character set utf8 collate utf8_unicode_ci';
    $r2 = fof_db_query("desc $table");
    while ($row2 = fof_db_get_row($r2))
        if (strtolower($row2['Type']) == 'mediumtext')
            $alter[] = 'change '.$row2['Field'].' '.$row2['Field'].' text'.(strtolower($row2['Null']) == 'no' ? ' not null' : '');
    if ($alter)
        fof_db_query("alter table $table ".implode(', ', $alter));
}

if (!fof_num_rows(fof_db_query("show columns from $FOF_FEED_TABLE like 'feed_image_cache_date'")))
    fof_db_query("ALTER TABLE $FOF_FEED_TABLE ADD `feed_image_cache_date` INT( 11 ) DEFAULT '0' AFTER `feed_image`;");

if (!fof_num_rows(fof_db_query("show columns from $FOF_USER_TABLE like 'user_password_hash'")))
{
    fof_db_query("ALTER TABLE $FOF_USER_TABLE CHANGE `user_password` `user_password_hash` VARCHAR( 32 ) NOT NULL");
    fof_db_query("update $FOF_USER_TABLE set user_password_hash = md5(concat(user_password_hash, user_name))");
}

if (!fof_num_rows(fof_db_query("show columns from $FOF_FEED_TABLE like 'feed_cache_attempt_date'")))
    fof_db_query("ALTER TABLE $FOF_FEED_TABLE ADD `feed_cache_attempt_date` INT( 11 ) DEFAULT '0' AFTER `feed_cache_date`;");

if (!fof_num_rows(fof_db_query("show columns from $FOF_ITEM_TABLE like 'item_author'")))
    fof_db_query("ALTER TABLE $FOF_ITEM_TABLE ADD `item_author` text NOT NULL AFTER `item_title`;");

$check = fof_db_get_row(fof_db_query("show create table $FOF_ITEM_TABLE"));
if (strpos($check[1], 'KEY `feed_id`') !== false)
    fof_db_query("alter table $FOF_ITEM_TABLE drop key feed_id, add key item_published (item_published)");

add_fk($check[1], $FOF_ITEM_TABLE, 'feed_id', $FOF_FEED_TABLE, 'on update cascade');

$check = fof_db_get_row(fof_db_query("show create table $FOF_ITEM_TAG_TABLE"));

add_fk($check[1], $FOF_ITEM_TAG_TABLE, 'tag_id', $FOF_TAG_TABLE);
add_fk($check[1], $FOF_ITEM_TAG_TABLE, 'user_id', $FOF_USER_TABLE);
add_fk($check[1], $FOF_ITEM_TAG_TABLE, 'item_id', $FOF_ITEM_TABLE);

if (strpos($check[1], 'PRIMARY KEY (`user_id`,`item_id`,`tag_id`)') !== false)
{
    fof_db_query(
        "alter table $FOF_ITEM_TAG_TABLE add key user_id (user_id),".
        " add key item_id_user_id_tag_id (item_id, user_id, tag_id), drop primary key,".
        " add primary key (tag_id, user_id, item_id), drop key tag_id, drop key item_id"
    );
}

if (!strpos($check[1], '`item_published`'))
{
    fof_db_query(
        "alter table $FOF_ITEM_TAG_TABLE add item_published int not null default '0',".
        " add feed_id int not null default 0,".
        " add key tag_id_user_id_item_published_item_id (tag_id, user_id, item_published, item_id),".
        " add key tag_id_user_id_feed_id (tag_id, user_id, feed_id),".
        " add key feed_id (feed_id)"
    );
}

if (fof_num_rows(fof_db_query("select count(*) from $FOF_ITEM_TAG_TABLE where feed_id=0")))
{
    fof_db_query(
        "update $FOF_ITEM_TAG_TABLE it, $FOF_ITEM_TABLE i".
        " set it.item_published=i.item_published, it.feed_id=i.feed_id".
        " where it.feed_id=0 and it.item_id=i.item_id"
    );
}

add_fk($check[1], $FOF_ITEM_TAG_TABLE, 'feed_id', $FOF_FEED_TABLE);

?>
Schema up to date.<hr>

Inserting initial data...
<?php
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (1, 'unread')", 1);
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (2, 'star')", 1);
?>
Done.<hr>

Checking cache directory...
<?php

if (!file_exists("cache"))
{
    $status = @mkdir("cache", 0755);
    if (!$status)
    {
        echo "<font color='red'>Can't create directory <code>" . getcwd() . "/cache/</code>.<br>You will need to create it yourself, and make it writeable by your PHP process.<br>Then, reload this page.</font>";
        echo "</div></body></html>";
        exit;
    }
}

if(!is_writable("cache"))
{
    echo "<font color='red'>The directory <code>" . getcwd() . "/cache/</code> exists, but is not writable.<br>You will need to make it writeable by your PHP process.<br>Then, reload this page.</font>";
    echo "</div></body></html>";
    exit;
}

?>
Cache directory exists and is writable.<hr>

<?php
$result = fof_db_query("select * from $FOF_USER_TABLE where user_name = 'admin'");
if (fof_num_rows($result) == 0) {
?>
You now need to choose an initial password for the 'admin' account:<br>

<form>
<table>
<tr><td>Password:</td><td><input type=password name=password></td></tr>
<tr><td>Password again:</td><td><input type=password name=password2></td></tr>
</table>
<input type=submit value="Set Password">
</form>

<?php } else { ?>

'admin' account already exists.<br>
<br><b><center>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b>

<?php } } ?>

</div></body></html>
