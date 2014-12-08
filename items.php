<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * items.php - displays right hand side "frame"
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
include_once("fof-render.php");

$which = !empty($_GET['which']) ? $_GET['which'] : 0;
$order = !empty($_GET['order']) ? $_GET['order'] : $fof_prefs_obj->get('order');
$what = !empty($_GET['what']) ? $_GET['what'] : 'unread';

$how = !empty($_GET['how']) ? $_GET['how'] : NULL;
$feed = !empty($_GET['feed']) ? $_GET['feed'] : NULL;
$when = !empty($_GET['when']) ? $_GET['when'] : NULL;
$howmany = !empty($_GET['howmany']) ? $_GET['howmany'] : $fof_prefs_obj->get('howmany');
$search = !empty($_GET['search']) ? $_GET['search'] : NULL;

$title = fof_view_title($feed, $what, $when, $which, $howmany, $search);

?>

<p class="items-title"><?php echo $title ?></p>

<ul id="item-display-controls" class="inline-list">
	<li class="orderby"><?php

	echo ($order == "desc") ? '[new to old]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=desc\">[new to old]</a>";
	
	?></li>
	<li class="orderby"><?php

	echo ($order == "asc") ? '[old to new]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=asc\">[old to new]</a>";
	
	?></li>
	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
	<li><a href="javascript:delete_flagged(<?= fof_is_admin() ? 1 : 0 ?>)">Delete flagged</a></li>
</ul>

<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
$links = fof_get_nav_links($feed, $what, $when, $which, $howmany);

if($links) { ?>
	<center><?php echo $links ?></center><?php
}

$result = fof_get_items(fof_current_user(), $feed, $what, $when, $which, $howmany, $order, $search);

$first = true;

foreach($result as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$first = false;
	print '<div class="item '.(!empty($row['prefs']['hide_content']) ? 'hidden' : 'shown').'" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
	fof_render_item($row);
	print '</div>';
}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

if($links) { ?>
	<center><?php echo $links ?></center><?php
}
?>
		</form>

        <div id="end-of-items"></div>

<script>itemElements = $$('.item');</script>
