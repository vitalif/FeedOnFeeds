<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * shared.php - display shared items for a user
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;
include_once("fof-main.php");
include_once("fof-render.php");

$user = !empty($_GET['user']) ? intval($_GET['user']) : 0;
if(!$user) die;

$format = !empty($_GET['format']) ? $_GET['format'] : '';

$prefs = new FoF_Prefs($user);
$sharing = $prefs->get("sharing");
if($sharing == "no") die;

$name = $prefs->get("sharedname");
$url = $prefs->get("sharedurl");

$what = $sharing;
$extratitle = '';
if(isset($_GET['what']))
{
    $what = ($sharing == "all") ? $_GET['what'] : "$sharing, " . $_GET['what'];
    $extratitle .= " items tagged " . $_GET['what'];
}

$feed = NULL;
if(isset($_GET['feed']))
{
    $feed = intval($_GET['feed']);
    $r = fof_db_get_feed_by_id($feed, fof_current_user());
    $extratitle .= ' from <a href="' . htmlspecialchars($r['feed_link']) . '">' . htmlspecialchars(fof_feed_title($r)) . '</a>';
}

$when = NULL;
if(isset($_GET['when']) && preg_match('#^\d+/\d+/\d+$#s', $_GET['when']))
    $when = $_GET['when'];

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$result = fof_get_items($user, $feed, $what, $when, $offset, 101);
if (count($result) > 100)
{
    $next = true;
    array_pop($result);
}
else
    $next = false;

function lnk($what = NULL, $atom = false, $offset = NULL)
{
    global $user;
    $link = "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "?user=$user";
    if (isset($_GET['what']) && $what === NULL)
        $what = $_GET['what'];
    if ($what !== NULL)
        $link .= '&what='.urlencode($what);
    if (!empty($_GET['feed']))
        $link .= '&feed='.intval($_GET['feed']);
    if ($offset > 0)
        $link .= '&offset='.intval($offset);
    if ($atom)
        $link .= '&format=atom';
    return htmlspecialchars($link);
}

if($format == "atom")
{
    header("Content-Type: application/atom+xml; charset=utf-8");
    print '<?xml version="1.0"?>';
?>

<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Feed on Feeds - Shared Items<?php if($name) echo " from $name"; if($extratitle) echo " " . strip_tags($extratitle) ?></title>
  <updated><?= gmdate('Y-m-d\TH:i:s\Z')?></updated>
  <generator uri="http://feedonfeeds.com/">Feed on Feeds</generator>
  <?php if($name) echo "<author><name>$name</name></author>"; ?>
  <id><?= lnk(NULL, true) ?></id>
  <link href="<?= lnk(NULL, true) ?>" rel="self" type="application/atom+xml"/>
  <link href="<?= lnk() ?>" rel="alternate"/>

<?php

foreach($result as $item)
{
    $feed_link = htmlspecialchars($item['feed_link']);
    $feed_url = htmlspecialchars(preg_replace('!^([a-z0-9_]+)://[^/]*:[^/]*@!is', '\1://', $item['feed_url']));
    $feed_title = htmlspecialchars(fof_feed_title($item));

    $item_link = htmlspecialchars($item['item_link']);

    $item_guid = $item['item_guid'];
    if (!preg_match("/^[a-z0-9\.\+\-]+:/", $item_guid))
        $item_guid = $feed_link . '#' . $item_guid;
    $item_guid = htmlspecialchars($item_guid);

    $item_title = htmlspecialchars($item['item_title']);
    $item_content = htmlspecialchars($item['item_content']);

    $item_published = gmdate('Y-m-d\TH:i:s\Z', $item['item_published']);
    $item_cached = gmdate('Y-m-d\TH:i:s\Z', $item['item_cached']);
    $item_updated = gmdate('Y-m-d\TH:i:s\Z', $item['item_updated']);

    if(!$item_title) $item_title = "[no title]";

?>

  <entry>
    <id><?= $item_guid ?></id>
    <link href="<?= $item_link ?>" rel="alternate" type="text/html"/>
    <title type="html"><?= $item_title ?></title>
    <summary type="html"><?= $item_content ?></summary>
    <updated><?= $item_updated ?></updated>
    <source>
      <id><?= $feed_link ?></id>
      <link href="<?= $feed_link ?>" rel="alternate" type="text/html"/>
      <link href="<?= $feed_url ?>" rel="self" type="application/atom+xml"/>
      <title><?= $feed_title ?></title>
    </source>
  </entry>
<?php
}
echo '</feed>';
}
else
{
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <link rel="alternate" href="<?= lnk(NULL, true) ?>" type="application/atom+xml"/>
  <title>Feed on Feeds - Shared Items<?php if($name) echo " from $name"; if($extratitle) echo " " . strip_tags($extratitle) ?></title>
  <link rel="stylesheet" href="fof.css" media="screen" />
  <style>
  .box
  {
    font-family: georgia;
    background: #eee;
    border: 1px solid black;
    width: 30em;
    margin: 10px auto 20px;
    padding: 1em;
    text-align: center;
  }
  .pages { text-align: center; }
  .pages a { margin: 0.5em; }
  </style>
</head>

<body>

<h1 class="box">
  <a href="http://feedonfeeds.com/">Feed on Feeds</a> - Shared Items
  <?php if($name) { ?>
    from <a href="<?= lnk() ?>"><?= $name ?></a>
      <?php if($url) { ?>
        <a href="<?= $url ?>"><img src="image/external.png" width="10" height="10" /></a>
      <?php } ?>
    <?= $extratitle ? "<br><i>$extratitle</i>" : "" ?>
  <?php } ?>
</h1>

<div class="pages">
  <?php if($offset) { ?>
    <a href="<?= lnk(NULL, false, max($offset-100, 0)) ?>">newer items</a>
  <?php } if($next) { ?>
    <a href="<?= lnk(NULL, false, $offset+100) ?>">earlier items</a>
  <?php } ?>
</div>

<div id="items">

<?php

$first = true;

foreach($result as $item)
{
    $item_id = $item['item_id'];
    print '<div class="item shown" id="i' . $item_id . '">';

    $feed_link = $item['feed_link'];
    $feed_title = fof_feed_title($item);
    $feed_image = $item['feed_image'];
    $feed_description = $item['feed_description'];

    $item_link = $item['item_link'];
    $item_id = $item['item_id'];
    $item_title = $item['item_title'];
    $item_content = $item['item_content'];

    $item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset*60*60);
    $item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset*60*60);
    $item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset*60*60);

    if(!$item_title) $item_title = "[no title]";

?>

<div class="header">
  <h1>
    <?php if($item_link) { ?>
      <a href="<?=htmlspecialchars($item_link)?>"><?= $item_title ?></a>
    <?php } else { ?>
      <?= $item_title ?>
    <?php } ?>
  </h1>
  <span class='dash'> - </span>
  <h2>
    <a href="<?=htmlspecialchars($feed_link)?>" title="<?=htmlspecialchars($feed_description)?>"><img src="<?=htmlspecialchars($feed_image)?>" height="16" width="16" border="0" /></a>
    <a href="<?=htmlspecialchars($feed_link)?>" title="<?=htmlspecialchars($feed_description)?>"><?=htmlspecialchars($feed_title)?></a>
  </h2>
  <span class="tags">
    <?php foreach($item['tags'] as $t) {
      if (!preg_match('#\b'.str_replace('#', '\\#', preg_quote($t)).'\b#', $sharing)) { ?>
        <a href="<?= lnk($t) ?>"><?=htmlspecialchars($t)?></a>
    <?php } } ?>
  </span>
  <span class="meta">on <?= $item_published ?> GMT</span>
</div>

<div class="body"><?= $item_content ?></div>

<div class="clearer"></div>
</div>

<?php } if(!$result) { ?>
<p><i>No shared items.</i></p>
<?php } ?>

</div></body></html>

<?php } ?>
