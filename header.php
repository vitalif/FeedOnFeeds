<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * header.php - common header for all pages
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

if(isset($_COOKIE['fof_sidebar_width']))
    $width = $_COOKIE['fof_sidebar_width'];
else
    $width = 250;

$unread_count = fof_get_unread_count(fof_current_user());

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

  <title>Feed on Feeds<?php if($unread_count) echo " ($unread_count)";?></title>

  <link rel="stylesheet" type="text/css" href="fof.css" media="screen" />
  <link rel="stylesheet" type="text/css" href="fof-mobile.css" media="handheld" />
  <link rel="stylesheet" type="text/css" href="fof-mobile.css" media="only screen and (max-device-width: 600px)" />-->

  <script src="prototype/prototype.js" type="text/javascript"></script>
  <script src="fof.js" type="text/javascript"></script>

  <!--[if IE]>
  <script>window.isIE = true;</script>
  <style>
    @media screen { #sidebar table { width: <?=($width-20)?>px; } }
  </style>
  <![endif]-->

  <style>
    @media only screen and (min-device-width: 600px) {
      #sidebar { width: <?=$width?>px; }
      #handle { left: <?=$width?>px; }
      #items { margin-left: <?=($width+20)?>px; }
      #item-display-controls { left: <?=($width+10)?>px; }
    }
  </style>

  <script>
    document.onmousemove = dragResize;
    document.onmouseup = completeDrag;
  <?php if($fof_prefs_obj->get('keyboard')) { ?>
    document.onkeypress = keyboard;
  <?php } ?>
  </script>

</head>

<body class="highlight-on">

<div id="sidebar">
<?php include("sidebar.php") ?>
</div>

<div id="handle" onmousedown="startResize(event)"></div>

<div id="items">
