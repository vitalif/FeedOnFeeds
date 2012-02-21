<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add.php - displays form to add a feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 * Modified by Vitaliy Filippov (c) 2009
 * vitalif@mail.ru - http://lib.custis.ru/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include("header.php");

$url = $_REQUEST['rss_url'];
$new_tags = $_REQUEST['new_tags'];
$login = $_REQUEST['basic_login'];
$password = $_REQUEST['basic_password'];
$opml = $_REQUEST['opml_url'];
$file = $_POST['opml_file'];
$unread = $_REQUEST['unread'];

if ($url && !preg_match('!^[a-z0-9_]+://!is', $url))
    $url = "http://$url";

if ($login == '%user%')
    $login = fof_username();

$feeds = array();

if ($_REQUEST['do'])
{
    if ($opml)
    {
        $sfile = new SimplePie_File($opml);
        if(!$sfile->success)
        {
            echo "Cannot open $opml<br>";
            return false;
        }
        $content = $sfile->body;
        $feeds = fof_opml_to_array($content);
    }
    if ($url)
    {
        if ($login && strlen($password))
            $url = preg_replace('!^([a-z0-9_]+)://([^/]*:[^/]*@)?!is', '\1://' . str_replace("\\", "\\\\", urlencode($login) . ':' . urlencode($password)) . '@', $url);
        $feeds[] = $url;
    }
}

$url = preg_replace('!^([a-z0-9_]+)://([^/]*:[^/]*@)?!is', '\1://', $url);

if ($_FILES['opml_file']['tmp_name'])
{
    if(!$content_array = file($_FILES['opml_file']['tmp_name']))
    {
        echo "Cannot open uploaded file<br>";
    }
    else
    {
        $content = implode("", $content_array);
        $feeds = fof_opml_to_array($content);
    }
}

$add_feed_url = "http";
if($_SERVER["HTTPS"] == "on")
    $add_feed_url = "https";
$add_feed_url .= "://" . $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
?>

<div class="fof-add-feeds">

<h1>Use FeedOnFeeds for reading feeds always</h1>

<div style="background: #eee; border: 1px solid black; padding: 1.5em; margin: 1em;">
If your browser is cool, you can <a href='javascript:window.navigator.registerContentHandler("application/vnd.mozilla.maybe.feed", "<?php echo $add_feed_url ?>?basic_login=%25user%25&do=1&rss_url=%s", "Feed on Feeds")'>register Feed on Feeds as a Feed Reader</a>.
If it is not cool, you can still use the <a href="javascript:void(location.href='<?php echo $add_feed_url ?>?basic_login=%25user%25&do=1&rss_url='+escape(location))">FoF subscribe</a> bookmarklet to subscribe to any page with a feed.
Just add it as a bookmark and then click on it when you are at a page you'd like to subscribe to!
</div>

<form method="post" name="addform" action="add.php" enctype="multipart/form-data">

When adding feeds, mark <select name="unread"><option value=today <?= $unread == "today" ? "selected" : "" ?> >today's</option><option value=all <?= $unread == "all" ? "selected" : "" ?> >all</option><option value=no <?= $unread == "no" ? "selected" : "" ?> >no</option></select> items as unread<br />

<h1>Enter URL manually</h1>

<p>
RSS or weblog URL: <input type="text" name="rss_url" size="40" value="<?= htmlspecialchars($url) ?>" /> <input name="do" type="Submit" value="Add a feed" /><br />
Login: <input type="text" name="basic_login" value="<?= htmlspecialchars($login) ?>" /> Password: <input type="password" name="basic_password" value="<?= htmlspecialchars($password) ?>" /> (optional) for password-protected feeds<br />
Tags for new feed(s): <input type="text" name="new_tags" size="40" value="<?= htmlspecialchars($new_tags) ?>" /> (separate by comma)
</p>

<h1>OPML import</h1>

<p>
OPML URL: <input type="text" name="opml_url" size="40" value="<?= htmlspecialchars($opml) ?>" /> <input name="do" type="Submit" value="Add feeds from OPML file on the Internet" /><br>
OPML filename: <input type="hidden" name="MAX_FILE_SIZE" value="100000" /><input type="file" name="opml_file" size="40" value="<?= htmlspecialchars($file) ?>" /> <input name="do" type="Submit" value="Upload an OPML file" />
</p>

</form>

<h1>OPML export</h1>
<form style="margin: 1em" method="post" action="opml.php"><input type="submit" value="Export subscriptions as OPML"></form>

<?php if (!count($feeds) && $fof_prefs_obj && ($suggest = intval($fof_prefs_obj->admin_prefs['suggestadd'])) &&
    count($suggest = fof_db_get_most_popular_feeds($suggest))) { ?>
<h1>Most popular feeds</h1>
<p>
<?php foreach ($suggest as $feed) { ?>
<a href="<?=htmlspecialchars('?do=1&rss_url='.urlencode($feed['feed_url']))?>"><?=htmlspecialchars($feed['feed_title'])?></a> <a href="<?=htmlspecialchars($feed['feed_link'])?>"><img src="image/external.png" alt=" " width="10" height="10" /></a> &ndash; <?=$feed['readers']?> readers<br />
<?php } ?>
</p>
<?php } ?>

</div>

<?php
if(count($feeds))
{
print("<script>\nwindow.onload = ajaxadd;\nfeedslist = [");

foreach($feeds as $feed)
    $feedjson[] = "{'url': '" . addslashes($feed) . "'}";

print(join($feedjson, ", "));
print("];\n</script>");
}
print("<br />");

include("footer.php");
