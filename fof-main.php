<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-main.php - initializes FoF, and contains functions used from other scripts
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

fof_repair_drain_bamage();

if ( !file_exists( dirname(__FILE__) . '/fof-config.php') )
{
    echo "You will first need to create a fof-config.php file.  Please copy fof-config-sample.php to fof-config.php and then update the values to match your database settings.";
    die();
}

require_once("fof-config.php");
require_once("fof-db.php");
require_once("classes/fof-prefs.php");
#if (file_exists(dirname(__FILE__).'/login-external.php'))
#    require_once(dirname(__FILE__).'/login-external.php');

fof_db_connect();

if(!$fof_installer)
{
    if(!$fof_no_login)
    {
        require_user();
        $fof_prefs_obj =& FoF_Prefs::instance();
    }
    else
    {
        $fof_user_id = 1;
        $fof_prefs_obj =& FoF_Prefs::instance();
    }

    ob_start();
    fof_init_plugins();
    ob_end_clean();
}

require_once('simplepie/simplepie.php');

function fof_set_content_type()
{
    static $set;
    if(!$set)
    {
        header("Content-Type: text/html; charset=utf-8");
        $set = true;
    }
}

function fof_log($message, $topic="debug")
{
    global $fof_prefs_obj;

    if(!$fof_prefs_obj) return;

    $p = $fof_prefs_obj->admin_prefs;
    if(!$p['logging']) return;

    static $log;
    if(!isset($log)) $log = @fopen("fof.log", 'a');

    if(!$log) return;

    $message = str_replace ("\n", "\\n", $message);
    $message = str_replace ("\r", "\\r", $message);

    fwrite($log, date('r') . " [$topic] $message\n");
}

function require_user()
{
    // FIXME Пилять! Да это же по безопасности, как HTTP Basic авторизация! :-(
    $user_name = $_COOKIE["user_name"];
    $user_password_hash = $_COOKIE["user_password_hash"];

    if (!$user_name || !$user_password_hash || !fof_authenticate($user_name, $user_password_hash))
    {
        if (!function_exists('fof_require_user_hook') ||
            !fof_require_user_hook())
        {
            header("Location: login.php");
            exit;
        }
    }
}

function fof_authenticate($user_name, $user_password_hash)
{
    global $fof_user_name;

    if(fof_db_authenticate($user_name, $user_password_hash))
    {
        setcookie ( "user_name", $fof_user_name, time()+60*60*24*365*10 );
        setcookie ( "user_password_hash",  $user_password_hash, time()+60*60*24*365*10 );
        return true;
    }
}

function fof_logout()
{
    setcookie ( "user_name", "", time() );
    setcookie ( "user_password_hash", "", time() );
    setcookie ( "logged_out", "1", time() + 300 );
}

function fof_current_user()
{
    global $fof_user_id;

    return $fof_user_id;
}

function fof_username()
{
    global $fof_user_name;

    return $fof_user_name;
}

function fof_get_users()
{
    return fof_db_get_users();
}

function fof_prefs()
{
    $p =& FoF_Prefs::instance();
    return $p->prefs;
}

function fof_is_admin()
{
    global $fof_user_level;

    return $fof_user_level == "admin";
}

function fof_get_unread_count($user_id)
{
    return fof_db_get_unread_count($user_id);
}

function fof_get_tags($user_id)
{
    $tags = array();

    $result = fof_db_get_tags($user_id);

    $counts = fof_db_get_tag_unread($user_id);

    while($row = fof_db_get_row($result))
    {
        if(isset($counts[$row['tag_id']]))
            $row['unread'] = $counts[$row['tag_id']];
        else
            $row['unread'] = 0;

        $tags[] = $row;
    }

    return $tags;
}

function fof_get_item_tags($user_id, $item_id)
{
    $result = fof_db_get_item_tags($user_id, $item_id);

    $tags = array();

    while($row = fof_db_get_row($result))
    {
        $tags[] = $row['tag_name'];
    }

    return $tags;
}

function fof_tag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }

    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_tag_items($user_id, $tag_id, $items);

    fof_db_tag_feed($user_id, $feed_id, $tag_id);
}

function fof_untag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }

    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_untag_items($user_id, $tag_id, $items);

    fof_db_untag_feed($user_id, $feed_id, $tag_id);
}

function fof_tag_item($user_id, $item_id, $tag)
{
    if(is_array($tag)) $tags = $tag; else $tags[] = $tag;

    foreach($tags as $tag)
    {
        $tag_id = fof_db_get_tag_by_name($user_id, $tag);
        if($tag_id == NULL)
        {
                $tag_id = fof_db_create_tag($user_id, $tag);
        }

        fof_db_tag_items($user_id, $tag_id, $item_id);
    }
}

function fof_untag_item($user_id, $item_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    fof_db_untag_items($user_id, $tag_id, $item_id);
}

function fof_untag($user_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);

    $result = fof_db_get_items($user_id, $feed_id, $tag, NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_nice_time_stamp($age)
{
    $age = time() - $age;

    if($age == 0)
    {
        $agestr = "never";
        $agestrabbr = "&infin;";
    }
    else
    {
        $seconds = $age % 60;
        $minutes = $age / 60 % 60;
        $hours = $age / 60 / 60 % 24;
        $days = floor($age / 60 / 60 / 24);

        if($seconds)
        {
            $agestr = "$seconds second";
            if($seconds != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $seconds . "s";
        }

        if($minutes)
        {
            $agestr = "$minutes minute";
            if($minutes != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $minutes . "m";
        }

        if($hours)
        {
            $agestr = "$hours hour";
            if($hours != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $hours . "h";
        }

        if($days)
        {
            $agestr = "$days day";
            if($days != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $days . "d";
        }
    }

    return array($agestr, $agestrabbr);
}

function fof_get_feeds($user_id, $order = 'feed_title', $direction = 'asc')
{
    $feeds = array();

    $result = fof_db_get_subscriptions($user_id);

    $i = 0;

    while($row = fof_db_get_row($result))
    {
        $id = $row['feed_id'];
        $age = $row['feed_cache_date'];

        $feeds[$i]['prefs'] = unserialize($row['subscription_prefs']);
        $feeds[$i]['feed_id'] = $id;
        $feeds[$i]['feed_url'] = $row['feed_url'];
        $feeds[$i]['feed_title'] = $row['feed_title'];
        $feeds[$i]['feed_link'] = $row['feed_link'];
        $feeds[$i]['feed_description'] = $row['feed_description'];
        $feeds[$i]['feed_image'] = $row['feed_image'];
        $feeds[$i]['feed_age'] = $age;

        list($agestr, $agestrabbr) = fof_nice_time_stamp($age);

        $feeds[$i]['agestr'] = $agestr;
        $feeds[$i]['agestrabbr'] = $agestrabbr;

        $i++;
    }

    $tags = fof_db_get_tag_id_map();

    for($i=0; $i<count($feeds); $i++)
    {
        $feeds[$i]['tags'] = array();
        if(is_array($feeds[$i]['prefs']['tags']))
        {
            foreach($feeds[$i]['prefs']['tags'] as $tag)
            {
                $feeds[$i]['tags'][] = $tags[$tag];
            }
        }
    }

    $result = fof_db_get_item_count($user_id);

    while($row = fof_db_get_row($result))
    {
        for($i=0; $i<count($feeds); $i++)
        {
            if($feeds[$i]['feed_id'] == $row['id'])
            {
                $feeds[$i]['feed_items'] = $row['count'];
                $feeds[$i]['feed_read'] = $row['count'];
                $feeds[$i]['feed_unread'] = 0;
            }
        }
    }

    $result = fof_db_get_unread_item_count($user_id);

    while($row = fof_db_get_row($result))
        for($i=0; $i<count($feeds); $i++)
            if($feeds[$i]['feed_id'] == $row['id'])
                $feeds[$i]['feed_unread'] = $row['count'];

    foreach($feeds as $feed)
    {
        $feed['feed_starred'] = 0;
    }

    $result = fof_db_get_starred_item_count($user_id);

    while($row = fof_db_get_row($result))
    {
        for($i=0; $i<count($feeds); $i++)
        {
            if($feeds[$i]['feed_id'] == $row['id'])
            {
                $feeds[$i]['feed_starred'] = $row['count'];
            }
        }
    }

    $result = fof_db_get_latest_item_age($user_id);

    while($row = fof_db_get_row($result))
    {
        for($i=0; $i<count($feeds); $i++)
        {
            if($feeds[$i]['feed_id'] == $row['id'])
            {
                $feeds[$i]['max_date'] = $row['max_date'];
                list($agestr, $agestrabbr) = fof_nice_time_stamp($row['max_date']);

                $feeds[$i]['lateststr'] = $agestr;
                $feeds[$i]['lateststrabbr'] = $agestrabbr;

            }
        }
    }

    $feeds = fof_multi_sort($feeds, $order, $direction != "asc");

    return $feeds;
}

function fof_feed_title($feed, $prefs = NULL)
{
    if (!$prefs)
        $prefs = $feed['prefs'];
    if (!$prefs)
        $prefs = fof_db_get_subscription_prefs(fof_current_user(), $feed['feed_id']);
    $t = $prefs['feed_title'];
    if (!$t)
        $t = $feed['feed_title'];
    return $t;
}

function fof_view_title($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL, $search=NULL)
{
    $prefs = fof_prefs();
    $title = "feed on feeds";

    if(!is_null($when) && $when != "")
    {
        $title .= ' - ' . $when;
    }

    if (!is_null($feed) && $feed != "" &&
        ($r = fof_db_get_feed_by_id($feed)))
    {
        $title .= ' - ' . htmlspecialchars(fof_feed_title($r), ENT_QUOTES);
    }

    if(is_numeric($start))
    {
        if(!is_numeric($limit)) $limit = $prefs["howmany"];
        $title .= " - items $start to " . ($start + $limit);
    }

    if($what != "all")
    {
        $wh = array();
        $new = '';
        foreach(explode(',', $what) as $t)
        {
            if ($t == 'unread')
                $new = ' new';
            else
                $wh[] = $t;
        }
        $title .= ' -' . $new . ' items';
        if (count($wh))
            $title .= ' tagged '.implode(',', $wh);
    }
    else
        $title .= ' - all items';

    if(isset($search))
    {
        $title .= " - <a href='javascript:toggle_highlight()'>matching <i class='highlight'>$search</i></a>";
    }

    return $title;
}

function fof_get_items($user_id, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
    global $fof_item_filters;

    $items = fof_db_get_items($user_id, $feed, $what, $when, $start, $limit, $order, $search);
    for ($i = 0; $i < count($items); $i++)
    {
        foreach($fof_item_filters as $filter)
            $items[$i]['item_content'] = $filter($items[$i]['item_content']);
        $items[$i]['feed_title'] = fof_feed_title($items[$i]);
    }

    return $items;
}

function fof_get_item($user_id, $item_id)
{
    global $fof_item_filters;

    $item = fof_db_get_item($user_id, $item_id);
    foreach($fof_item_filters as $filter)
        $item['item_content'] = $filter($item['item_content']);
    $item['feed_title'] = fof_feed_title($item);

    return $item;
}

function fof_mark_read($user_id, $items)
{
    fof_db_mark_read($user_id, $items);
}

function fof_mark_unread($user_id, $items)
{
    fof_db_mark_unread($user_id, $items);
}

function fof_delete_subscription($user_id, $feed_id)
{
    fof_db_delete_subscription($user_id, $feed_id);

    if(mysql_num_rows(fof_get_subscribed_users($feed_id)) == 0)
    {
        fof_db_delete_feed($feed_id);
    }
}

function fof_get_nav_links($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL)
{
    $prefs = fof_prefs();
    $string = "";

    if(!is_null($when) && $when != "")
    {
        if($when == "today")
            $whendate = fof_todays_date();
        else
            $whendate = $when;

        $begin = strtotime($whendate);

        $tomorrow = date( "Y/m/d", $begin + (24 * 60 * 60) );
        $yesterday = date( "Y/m/d", $begin - (24 * 60 * 60) );

        $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$yesterday&amp;how=$how&amp;howmany=$howmany\">[&laquo; $yesterday]</a> ";
        if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=today&amp;how=$how&amp;howmany=$howmany\">[today]</a> ";
        if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$tomorrow&amp;how=$how&amp;howmany=$howmany\">[$tomorrow &raquo;]</a> ";
    }

    if(is_numeric($start))
    {
        if(!is_numeric($limit)) $limit = $prefs["howmany"];

        $earlier = $start + $limit;
        $later = $start - $limit;

        $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;which=$earlier&amp;howmany=$limit\">[&laquo; previous $limit]</a> ";
        if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;howmany=$limit\">[current items]</a> ";
        if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;which=$later&amp;howmany=$limit\">[next $limit &raquo;]</a> ";
    }

    return $string;
}

function fof_render_feed_link($row)
{
    $link = $row['feed_link'];
    $description = $row['feed_description'];
    $title = htmlspecialchars(fof_feed_title($row), ENT_QUOTES);
    $url = $row['feed_url'];

    $s = "<b><a href=\"$link\" title=\"$description\">$title</a></b> ";
    $s .= "<a href=\"$url\">(feed)</a>";

    return $s;
}

function fof_opml_to_array($opml)
{
    $rx = "/xmlurl=\"(.*?)\"/mi";

    if (preg_match_all($rx, $opml, $m))
    {
        for($i = 0; $i < count($m[0]) ; $i++)
        {
            $r[] = $m[1][$i];
        }
  }

  return $r;
}

function fof_prepare_url($url)
{
    $url = trim($url);

    if(substr($url, 0, 7) == "feed://") $url = substr($url, 7);

    if(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://')
        $url = 'http://' . $url;

    return $url;
}

function fof_subscribe($user_id, $url, $unread = "today")
{
    if (!$url)
        return array("Empty URL", false);

    $url = fof_prepare_url($url);
    $feed = fof_db_get_feed_by_url($url);

    if (fof_is_subscribed($user_id, $url))
        return array("You are already subscribed to " . fof_render_feed_link($feed), $feed);

    if (fof_feed_exists($url))
    {
        fof_db_add_subscription($user_id, $feed['feed_id']);
        fof_apply_plugin_tags($id, NULL, $user_id);
        fof_update_feed($feed['feed_id']);

        if ($unread != "no")
            fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);
        return array('<font color="green"><b>Subscribed.</b></font>', $feed);
    }

    $rss = fof_parse($url, $user_id);

    if (isset($rss->error))
        return array("Error: <b>" . $rss->error . "</b>", $feed);
    else
    {
        // Changing saved feed URL automatically is a fucking bad idea!
        // It's very unpleasant to see that URL just changed sometime
        // by itself because the remote site had an error and gave FOF
        // some crap instead of real feed.
        // The another problem is handling feeds with authorization data
        // in the feed link: such feeds don't usually include login and
        // password inside the 'self' link.
        // So we don't change URLs in any way (original FOF did).

        $id = fof_add_feed($url, rss_feed_title($rss), $rss->get_link(), $rss->get_description());

        fof_update_feed($id);
        fof_db_add_subscription($user_id, $id);
        if ($unread != "no")
            fof_db_mark_feed_unread($user_id, $id, $unread);

        fof_apply_plugin_tags($id, NULL, $user_id);

        $feed = fof_db_get_feed_by_id($id);
        return array('<font color="green"><b>Subscribed.</b></font>', $feed);
    }
}

function fof_add_feed($url, $title, $link, $description)
{
    if ($title == "")
        $title = "[no title]";

    return fof_db_add_feed($url, $title, $link, $description);
}

function fof_is_subscribed($user_id, $url)
{
    return fof_db_is_subscribed($user_id, $url);
}

function fof_feed_exists($url)
{
    $feed = fof_db_get_feed_by_url($url);

    return $feed;
}

function fof_get_subscribed_users($feed_id)
{
    return fof_db_get_subscribed_users($feed_id);
}

function fof_mark_item_unread($feed_id, $id, $filtered = array())
{
    $result = fof_get_subscribed_users($feed_id);

    $users = array();
    while($row = fof_db_get_row($result))
        if (!$filtered[$row['user_id']])
            $users[] = $row['user_id'];

    fof_db_mark_item_unread($users, $id);
}

function fof_generate_sudo_id()
{
    global $fof_sudo_id_user;
    $sudo_id = unpack('H*', urandom(16));
    $sudo_id = $sudo_id[1];
    fof_cache_set("sudo-$sudo_id", $fof_sudo_id_user, 30);
    return "fof_sudo_id=$sudo_id";
}

function fof_parse($url, $as_user = NULL)
{
    $p = FoF_Prefs::instance();
    $admin_prefs = $p->admin_prefs;

    $pie = new SimplePie();
    /* Bug 63447 - Allow FoF to securely pass user authorization without verification */
    if (!is_null($as_user) && strpos($url, "fof_sudo") !== false)
    {
        global $fof_sudo_id_user;
        $fof_sudo_id_user = $as_user;
        $pie->headers['Cookie'] = new SimplePie_Callback('fof_generate_sudo_id');
    }
    $pie->set_cache_duration($admin_prefs["manualtimeout"] * 60);
    $pie->set_favicon_handler("favicon.php");
    $pie->set_feed_url($url);
    $pie->set_javascript(false);
    $pie->remove_div(false);
    $pie->init();

    return $pie;
}

function fof_apply_tags($item)
{
    global $fof_subscription_to_tags;

    if(!isset($fof_subscription_to_tags))
        $fof_subscription_to_tags = fof_db_get_subscription_to_tags();

    // add subscription tags
    foreach((array)$fof_subscription_to_tags[$item['feed_id']] as $user_id => $tags)
        if(is_array($tags))
            foreach($tags as $tag)
                fof_db_tag_items($user_id, $tag, $item['item_id']);

    $filtered = array();

    // filter duplicate items
    $dup = fof_db_get_is_duplicate_item($item['item_id'], $item['item_guid'], md5($item['item_content']));
    foreach ($dup as $user_id)
        $filtered[$user_id] = true;

    // regexp filter items
    foreach((array)$fof_subscription_to_tags['filter'][$item['feed_id']] as $user_id => $filter)
        if ($filter && (preg_match($filter, $title) || preg_match($filter, $content)))
            $filtered[$user_id] = true;

    // mark item as unread for some users
    fof_mark_item_unread($item['feed_id'], $item['item_id'], $filtered);
}

function rss_feed_title($rss)
{
    return html_entity_decode(strip_tags($rss->get_title()), ENT_QUOTES);
}

function fof_update_feed($id, $as_user = NULL)
{
    if(!$id) return 0;

    if($as_user === NULL)
        $as_user = fof_db_get_feed_single_user($id);

    $feed = fof_db_get_feed_by_id($id);
    $url = $feed['feed_url'];
    fof_log("Updating $url");

    fof_db_feed_mark_attempted_cache($id);

    $rss = fof_parse($feed['feed_url'], $as_user);

    if ($rss->error())
    {
        fof_log("feed update failed: " . $rss->error(), "update");
        return array(0, "Error: <b>" . $rss->error() . "</b>");
    }

    $sub = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
    $self_link = $rss->get_link(0, 'self');
    // Using <link rel="self"> as feed URL instead of user given URL is a fucking bad idea!
    // Why? 1) handling broken feeds 2) handling authorized feeds 3) user knows URL better
    //if($self_link) $sub = html_entity_decode($self_link, ENT_QUOTES);

    fof_log("subscription url is $sub");

    $image = $feed['feed_image'];
    $image_cache_date = $feed['feed_image_cache_date'];

    if($feed['feed_image_cache_date'] < (time() - (7*24*60*60)))
    {
        $image = $rss->get_favicon();
        $image_cache_date = time();
    }

    $title = rss_feed_title($rss);
    if($title == "") $title = "[no title]";

    fof_db_feed_update_metadata($id, $sub, $title, $rss->get_link(), $rss->get_description(), $image, $image_cache_date );

    $feed_id = $feed['feed_id'];
    $n = 0;

    if ($feed['feed_filter'])
    {
        $filter = @unserialize($feed['feed_filter']);
        if (!$filter || !$filter['re'] || !$filter['tags'])
            $filter = NULL;
        else
            $filter['tags'] = preg_split('/[\s,]*,[\s,]*/', $filter['tags']);
    }

    if($rss->get_items())
    {
        foreach($rss->get_items() as $item)
        {
            $link = $item->get_permalink();
            $author = $item->get_authors();
            if ($author)
            {
                foreach ($author as &$a)
                {
                    $an = htmlspecialchars($a->get_name());
                    $al = htmlspecialchars($a->get_link());
                    $ae = htmlspecialchars($a->get_email());
                    if ($al)
                        $a = "<a href=\"$al\">$an</a>";
                    else if ($ae)
                        $a = "<a href=\"mailto:$ae\">$an</a>";
                    else
                        $a = $an;
                }
                $author = implode(', ', $author);
            }
            else
                $author = '';
            $title = $item->get_title();
            $content = $item->get_content();
            $date = $item->get_date('U');
            if(!$date) $date = time();
            $item_id = $item->get_id();

            if(!$item_id)
                $item_id = $link;

            $id = fof_db_find_item($feed_id, $item_id);

            if($id == NULL)
            {
                $n++;

                global $fof_item_prefilters;
                foreach($fof_item_prefilters as $filter)
                {
                    list($link, $title, $content) = $filter($item, $link, $title, $content);
                }

                $id = fof_db_add_item($feed_id, array(
                    'item_guid'      => $item_id,
                    'item_link'      => $link,
                    'item_title'     => $title,
                    'item_author'    => $author,
                    'item_content'   => $content,
                    'item_cached'    => time(),
                    'item_published' => $date,
                    'item_updated'   => $date,
                ));
                $fof_item = fof_db_get_item_by_id($id);
                fof_apply_tags($fof_item);
                fof_apply_plugin_tags($feed_id, $id, NULL);
            }

            $ids[] = $id;
        }
    }

    // optionally purge old items -  if 'purge' is set we delete items that are not
    // unread or starred, not currently in the feed or within sizeof(feed) items
    // of being in the feed, and are over 'purge' many days old

    $p =& FoF_Prefs::instance();
    $admin_prefs = $p->admin_prefs;

    if($admin_prefs['purge'] != "")
    {
        fof_log('purge is ' . $admin_prefs['purge']);
        $count = count($ids);
        fof_log('items in feed: ' . $count);

        if(count($ids) != 0)
        {
            $in = implode ( ", ", $ids );

            global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
            $sql = "select item_id, item_cached from $FOF_ITEM_TABLE where feed_id = $feed_id and item_id not in ($in) order by item_cached desc limit $count, 1000000000";
            $result = fof_db_query($sql);

            while($row = fof_db_get_row($result))
                if($row['item_cached'] < (time() - ($admin_prefs['purge'] * 24 * 60 * 60)))
                    if(!fof_item_has_tags($row['item_id']))
                        $delete[] = $row['item_id'];

            $ndelete = count($delete);
            if(count($delete) != 0)
            {
                $in = implode(", ", $delete);
                fof_db_query( "delete from $FOF_ITEM_TABLE where item_id in ($in)" );
                fof_db_query( "delete from $FOF_ITEM_TAG_TABLE where item_id in ($in)" );
            }
        }
    }

    unset($rss);

    fof_db_feed_mark_cached($feed_id);

    $log = "feed update complete, $n new items, $ndelete items purged";
    if($admin_prefs['purge'] == "")
    {
        $log .= " (purging disabled)";
    }
    fof_log($log, "update");

    return array($n, "");
}

function fof_apply_plugin_tags($feed_id, $item_id = NULL, $user_id = NULL)
{
    $users = array();

    if($user_id)
        $users[] = $user_id;
    else
    {
        $result = fof_get_subscribed_users($feed_id);

        while($row = fof_db_get_row($result))
            $users[] = $row['user_id'];
    }

    $items = array();
    if($item_id)
        $items[] = fof_db_get_item($user_id, $item_id);
    else
    {
        $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
        foreach($result as $r)
            $items[] = $r;
    }

    $userdata = fof_get_users();

    foreach($users as $user)
    {
        fof_log("tagging for $user");

        global $fof_tag_prefilters;
        foreach($fof_tag_prefilters as $plugin => $filter)
        {
            fof_log("considering $plugin $filter");

            if(!$userdata[$user]['prefs']['plugin_' . $plugin])
            {
                foreach($items as $item)
                {
                    $tags = $filter($item['item_link'], $item['item_title'], $item['item_content']);
                    fof_tag_item($user, $item['item_id'], $tags);
                }
            }
        }
    }
}

function fof_item_has_tags($item_id)
{
    return fof_db_item_has_tags($item_id);
}

function fof_init_plugins()
{
    global $fof_item_filters, $fof_item_prefilters, $fof_tag_prefilters, $fof_plugin_prefs;

    $fof_item_filters = array();
    $fof_item_prefilters = array();
    $fof_plugin_prefs = array();
    $fof_tag_prefilters = array();

    $p =& FoF_Prefs::instance();

    $dirlist = opendir(FOF_DIR . "/plugins");
    while ($file = readdir($dirlist))
    {
        fof_log("considering " . $file);
        if (ereg('\.php$',$file) &&
            is_readable(FOF_DIR . "/plugins/" . $file) &&
            !$p->get('plugin_' . substr($file, 0, -4)))
        {
            fof_log("including " . $file);
            include(FOF_DIR . "/plugins/" . $file);
        }
    }

    closedir();
}

function fof_add_tag_prefilter($plugin, $function)
{
    global $fof_tag_prefilters;

    $fof_tag_prefilters[$plugin] = $function;
}

function fof_add_item_filter($function)
{
    global $fof_item_filters;

    $fof_item_filters[] = $function;
}

function fof_add_item_prefilter($function)
{
    global $fof_item_prefilters;

    $fof_item_prefilters[] = $function;
}

function fof_add_pref($name, $key, $type="string")
{
    global $fof_plugin_prefs;

    $fof_plugin_prefs[] = array($name, $key, $type);
}

function fof_add_item_widget($function)
{
    global $fof_item_widgets;

    $fof_item_widgets[] = $function;
}

function fof_get_widgets($item)
{
    global $fof_item_widgets;

    if (!is_array($fof_item_widgets))
    {
        return false;
    }

    foreach($fof_item_widgets as $widget)
    {
        $w = $widget($item);
        if($w) $widgets[] = $w;
    }

    return $widgets;
}

function fof_get_plugin_prefs()
{
    global $fof_plugin_prefs;

    return $fof_plugin_prefs;
}

function fof_multi_sort($tab,$key,$rev)
{
    if($rev)
    {
        $compare = create_function('$a,$b','if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return (strtolower($a["'.$key.'"]) > strtolower($b["'.$key.'"])) ? -1 : 1;}');
    }
    else
    {
        $compare = create_function('$a,$b','if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return (strtolower($a["'.$key.'"]) < strtolower($b["'.$key.'"])) ? -1 : 1;}');
    }

    usort($tab,$compare) ;
    return $tab ;
}

function fof_todays_date()
{
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    if ($prefs['dst'])
        $offset += date('I');

    return gmdate( "Y/m/d", time() + ($offset * 60 * 60) );
}

function fof_repair_drain_bamage()
{
    if (ini_get('register_globals'))
        foreach($_REQUEST as $k => $v)
            unset($GLOBALS[$k]);

    // thanks to submitter of http://bugs.php.net/bug.php?id=39859
    if (get_magic_quotes_gpc())
    {
        function undoMagicQuotes($array, $topLevel=true)
        {
            $newArray = array();
            foreach($array as $key => $value)
            {
                if (!$topLevel)
                    $key = stripslashes($key);
                if (is_array($value))
                    $newArray[$key] = undoMagicQuotes($value, false);
                else
                    $newArray[$key] = stripslashes($value);
            }
            return $newArray;
        }
        $_GET = undoMagicQuotes($_GET);
        $_POST = undoMagicQuotes($_POST);
        $_COOKIE = undoMagicQuotes($_COOKIE);
        $_REQUEST = undoMagicQuotes($_REQUEST);
    }
}

// for PHP 4 compatibility

if(!function_exists('str_ireplace'))
{
    function str_ireplace($search,$replace,$subject)
    {
        $search = preg_quote($search, "/");
        return preg_replace("/".$search."/i", $replace, $subject);
    }
}
