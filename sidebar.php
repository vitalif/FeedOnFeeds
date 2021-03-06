<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * sidebar.php - sidebar for all pages
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

fof_set_content_type();

?>
<img id="throbber" src="image/throbber.gif" align="left" style="display: none" />

<div id="welcome">Welcome <b><?php echo $fof_user_name ?></b>! <a href="prefs.php">prefs</a> | <a href="logout.php">log out</a> | <a href="http://feedonfeeds.com/">about</a></div>
<div id="addupd"><a href="add.php"><b>Add Feeds</b></a> / <a href="update.php"><b>Update Feeds</b></a></div>

<ul id="nav">

<?php

$order = $fof_prefs_obj->get('feed_order');
$direction = $fof_prefs_obj->get('feed_direction');

$what = !empty($_GET['what']) ? $_GET['what'] : 'unread';
$when = !empty($_GET['when']) ? $_GET['when'] : NULL;
$search = !empty($_GET['search']) ? $_GET['search'] : NULL;

echo "<script>what='$what'; when='$when';</script>";

$feeds = fof_get_feeds(fof_current_user(), $order, $direction);

$unread = $starred = $total = 0;
foreach($feeds as $row)
{
    $unread += $row['feed_unread'];
    $starred += $row['feed_starred'];
    $total += $row['feed_items'];
}

if($unread)
{
    echo "<script>document.title = 'Feed on Feeds ($unread)';</script>";
}
else
{
    echo "<script>document.title = 'Feed on Feeds';</script>";
}

echo "<script>starred = $starred;</script>";

?>

<li <?php if($what == "unread") echo "style='background: #ddd'" ?> ><a href=".?what=unread"><font color=red><b>Unread <?php if($unread) echo "($unread)" ?></b></font></a></li>
<li <?php if($what == "star") echo "style='background: #ddd'" ?> ><a href=".?what=star"><img src="image/star-on.gif" border="0" height="10" width="10"> Starred <span id="starredcount"><?php if($starred) echo "($starred)" ?></span></a></li>
<li <?php if($what == "all" && isset($when)) echo "style='background: #ddd'" ?> ><a href="?what=all&when=today">&lt; Today</a></li>
<li <?php if($what == "all" && !isset($when)) echo "style='background: #ddd'" ?> ><a href="?what=all">All Items <?php if($total) echo "($total)" ?></a></li>
<li <?php if(isset($search)) echo "style='background: #ddd'" ?> ><a href="javascript:Element.toggle('search'); Field.focus('searchfield');void(0);">Search</a>
<form action="." id="search" <?php if(!isset($search)) echo 'style="display: none"' ?>>
<input id="searchfield" name="search" value="<?php echo $search?>">
<?php
	if($what == "unread")
		echo "<input type='hidden' name='what' value='all'>";
	else
		echo "<input type='hidden' name='what' value='$what'>";
?>
<?php if(isset($_GET['when'])) echo "<input type='hidden' name='what' value='${_GET['when']}'>" ?>
</form>
</li>
</ul>

<?php

$tags = fof_get_tags(fof_current_user());

$n = 0;
foreach($tags as $tag)
{
    $tag_id = $tag['tag_id'];
    if($tag_id == 1 || $tag_id == 2) continue;
    $n++;
}

if($n)
{
?>

<div id="tags">

<table cellspacing="0" cellpadding="1" border="0" id="taglist">

<tr class="heading">
<td><span class="unread">#</span></td><td>tag name</td><td>untag all items</td>
</tr>

<?php
$t = 0;
foreach($tags as $tag)
{
    $tag_name = $tag['tag_name'];
    $tag_id = $tag['tag_id'];
    $count = $tag['count'];
    $unread = $tag['unread'];

    if($tag_id == 1 || $tag_id == 2) continue;

    if(++$t % 2)
        print "<tr class=\"odd-row\">";
    else
        print "<tr>";

    print "<td>";
    if ($unread) print "<a class='unread' href='.?what=$tag_name,unread'>$unread</a>/";
    print "<a href='?what=$tag_name'>$count</a></td>";
    print "<td><b><a href='.?what=$tag_name".($unread?",unread":"")."'>$tag_name</a></b></td>";
    print "<td><a href=\"#\" title=\"untag all items\" onclick=\"if(confirm('Untag all [$tag_name] items --are you SURE?')) { delete_tag('$tag_name'); return false; }  else { return false; }\">[x]</a></td>";

    print "</tr>";
}
?>

</table>

</div>

<?php } ?>

<div id="feeds">

<div id="feedlist">

<table cellspacing="0" cellpadding="1" border="0" id="feedlisttable">

<tr class="heading">

<?php

$title["feed_age"] = "sort by last update time";
$title["max_date"] = "sort by last new item";
$title["feed_unread"] = "sort by number of unread items";
$title["feed_url"] = "sort by feed URL";
$title["feed_title"] = "sort by feed title";

$name["feed_age"] = "age";
$name["max_date"] = "latest";
$name["feed_unread"] = "#";
$name["feed_url"] = "feed";
$name["feed_title"] = "title";

foreach (array("feed_age", "max_date", "feed_unread", "feed_url", "feed_title") as $col)
{
    if($col == $order)
    {
        $url = "return change_feed_order('$col', '" . ($direction == "asc" ? "desc" : "asc") . "')";
    }
    else
    {
        $url = "return change_feed_order('$col', 'asc')";
    }

    echo "<td><nobr><a href='#' title='$title[$col]' onclick=\"$url\">";

    if($col == "feed_unread")
    {
        echo "<span class=\"unread\">#</span>";
    }
    else
    {
        echo $name[$col];
    }

    if($col == $order)
    {
        echo ($direction == "asc") ? "&darr;" : "&uarr;";
    }

    echo "</a></nobr></td>";
}

?>

<td></td>
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

    if(++$t % 2)
        print "<tr class=\"odd-row\">";
    else
        print "<tr>";

    $u = ".?feed=$id";
    $u2 = ".?feed=$id&amp;what=all";

    print "<td><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>";

    print "<td><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>";

    print "<td class=\"nowrap\" id=\"${id}-items\">";

    if($unread)
        print "<a class=\"unread\" title=\"new items\" href=\"$u\">$unread</a>/";

    print "<a href=\"$u2\" title=\"all items\">$items</a>";

    print "</td>";

    print "<td align='center'>";
    if($row['feed_image'] && $fof_prefs_obj->get('favicons'))
        print "<a href=\"$url\" title=\"feed\"><img src='" . $row['feed_image'] . "' width='16' height='16' border='0' /></a>";
    else
        print "<a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a>";
    print "</td>";

    print "<td>";
    print "<a href=\"".($unread ? $u : $u2)."\" title=\"".($unread ? "unread" : "all")." items\"><b>".htmlspecialchars($title)."</b></a>";
    if ($link)
        print " <a href=\"$link\" title=\"home page\"><img width=\"10\" height=\"10\" alt='home page' src='image/external.png' /></a>";
    print "</td>";
    print "<td><nobr>";

    print "<a href=\"update.php?feed=$id\" title=\"update\">u</a>";
    $stitle = addslashes($title);
    print " <a href=\"#\" title=\"mark all read\" onclick=\"if(confirm('Mark all [$stitle] items as read --are you SURE?')) { mark_feed_read($id); return false; }  else { return false; }\">m</a>";
    print " <a href=\"delete.php?feed=$id\" title=\"delete\" onclick=\"return confirm('Unsubscribe [$stitle] --are you SURE?')\">d</a>";

    print "</nobr></td>";

    print "</tr>";
}

?>

</table>

</div>

</div>
