<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-db.php - (nearly) all of the DB specific code
 *
 * Copyright (C) 2004-2007 Stephen Minutillo steve@minutillo.com http://minutillo.com/steve/
 *           (C) 2009-2014 Vitaliy Filippov vitalif@mail.ru http://yourcmc.ru/wiki/
 *
 * Distributed under the GPL - see LICENSE
 */

$FOF_FEED_TABLE = FOF_FEED_TABLE;
$FOF_ITEM_TABLE = FOF_ITEM_TABLE;
$FOF_ITEM_TAG_TABLE = FOF_ITEM_TAG_TABLE;
$FOF_SUBSCRIPTION_TABLE = FOF_SUBSCRIPTION_TABLE;
$FOF_TAG_TABLE = FOF_TAG_TABLE;
$FOF_USER_TABLE = FOF_USER_TABLE;

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

function fof_db_connect()
{
    global $fof_connection;

    $fof_connection = mysql_pconnect(FOF_DB_HOST, FOF_DB_USER, FOF_DB_PASS) or die("<br><br>Cannot connect to database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
    mysql_select_db(FOF_DB_DBNAME, $fof_connection) or die("<br><br>Cannot select database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
    fof_db_query("SET NAMES ".FOF_DB_CHARSET);
}

function fof_db_optimize()
{
    /* Может быть, проблемы из-за этого? */
    /*global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;*/
    /*fof_db_query("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE");*/
}

function fof_safe_query(/* $query, [$args...]*/)
{
    $args  = func_get_args();
    $query = array_shift($args);
    if(is_array($args[0])) $args = $args[0];
    $args  = array_map('mysql_real_escape_string', $args);
    $query = vsprintf($query, $args);

    return fof_db_query($query);
}

function fof_db_query($sql, $live=0)
{
    global $fof_connection;

    list($usec, $sec) = explode(" ", microtime());
    $t1 = (float)$sec + (float)$usec;

    $result = mysql_query($sql, $fof_connection);

    if(is_resource($result)) $num = mysql_num_rows($result);
    if($result) $affected = mysql_affected_rows($fof_connection);

    list($usec, $sec) = explode(" ", microtime());
    $t2 = (float)$sec + (float)$usec;
    $elapsed = $t2 - $t1;
    $logmessage = sprintf("%.3f: [%s] (%d / %d)", $elapsed, $sql, $num, $affected);
    fof_log($logmessage, "query");

    if($live)
    {
        return $result;
    }
    else
    {
        if(mysql_errno($fof_connection))
            fof_die_mysql_error("Cannot run query '$sql': ".mysql_errno($fof_connection).": ".mysql_error($fof_connection));
        return $result;
    }
}

function fof_die_mysql_error($error)
{
    ob_start();
    print_r(debug_backtrace());
    $trace = ob_get_contents();
    ob_end_clean();
    $error .= "\n".$trace;
    fof_log($error, 'error');
    $error = "SQL error. Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation?\n<pre>$error</pre>";
    die($error);
}

function fof_db_get_row($result)
{
    return mysql_fetch_array($result);
}

function fof_db_get_value($sql)
{
    if (!($result = fof_db_query($sql)) ||
        !($row = fof_db_get_row($result)))
        return NULL;
    return $row[0];
}

////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_mark_cached($feed_id)
{
    global $FOF_FEED_TABLE;

    $result = fof_safe_query("update $FOF_FEED_TABLE set feed_cache_date = %d where feed_id = %d", time(), $feed_id);
}

function fof_db_feed_mark_attempted_cache($feed_id)
{
    global $FOF_FEED_TABLE;

    $result = fof_safe_query("update $FOF_FEED_TABLE set feed_cache_attempt_date = %d where feed_id = %d", time(), $feed_id);
}

function fof_db_feed_update_metadata($feed_id, $url, $title, $link, $description, $image, $image_cache_date)
{
    global $FOF_FEED_TABLE;

    $sql = "update $FOF_FEED_TABLE set feed_url = '%s', feed_title = '%s', feed_link = '%s', feed_description = '%s'";
    $args = array($url, $title, $link, $description);

    if($image)
    {
        $sql .= ", feed_image = '%s' ";
        $args[] = $image;
    }
    else
    {
        $sql .= ", feed_image = NULL ";
    }

    $sql .= ", feed_image_cache_date = %d ";
    $args[] = $image_cache_date;

    $sql .= "where feed_id = %d";
    $args[] = $feed_id;

    $result = fof_safe_query($sql, $args);
}

function fof_db_get_latest_item_age($user_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TABLE;

    $result = fof_db_query("SELECT max( item_cached ) AS \"max_date\", $FOF_ITEM_TABLE.feed_id as \"id\" FROM $FOF_ITEM_TABLE GROUP BY $FOF_ITEM_TABLE.feed_id");
    return $result;
}

function fof_db_get_subscriptions($user_id)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;

    return fof_safe_query("select * from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = %d and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id order by feed_title", $user_id);
}

function fof_db_get_feeds()
{
    global $FOF_FEED_TABLE;

    return fof_db_query("select * from $FOF_FEED_TABLE order by feed_title");
}

function fof_db_get_item_by_id($item_id)
{
    global $FOF_ITEM_TABLE;
    if (($result = fof_db_query("select * from $FOF_ITEM_TABLE where item_id=".intval($item_id))) &&
        ($row = fof_db_get_row($result)))
        return $row;
    return NULL;
}

function fof_db_get_item_count($user_id)
{
    global $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE;

    return fof_safe_query("select straight_join count(*) as count, $FOF_ITEM_TABLE.feed_id as id".
        " from $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = %d".
        " and $FOF_ITEM_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id group by id", $user_id);
}

function fof_db_get_tagged_item_count($user_id, $tag_id)
{
    global $FOF_ITEM_TAG_TABLE;
    return fof_safe_query(
        "select count(*) as count, feed_id as id".
        " from $FOF_ITEM_TAG_TABLE where tag_id = %d and user_id = %d group by id",
        $tag_id, $user_id
    );
}

function fof_db_get_subscribed_users($feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;

    return fof_safe_query("select user_id from $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.feed_id = %d", $feed_id);
}

function fof_db_is_subscribed($user_id, $feed_url)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;

    $result = fof_safe_query("select $FOF_SUBSCRIPTION_TABLE.feed_id from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where feed_url='%s' and $FOF_SUBSCRIPTION_TABLE.feed_id = $FOF_FEED_TABLE.feed_id and $FOF_SUBSCRIPTION_TABLE.user_id = %d", $feed_url, $user_id);

    if(mysql_num_rows($result) == 0)
    {
        return false;
    }

    return true;
}

function fof_db_get_feed_by_url($feed_url)
{
    global $FOF_FEED_TABLE;

    $result = fof_safe_query("select * from $FOF_FEED_TABLE where feed_url='%s'", $feed_url);

    if (mysql_num_rows($result) == 0)
        return NULL;

    $row = mysql_fetch_array($result);

    return $row;
}

function fof_db_get_feed_by_id($feed_id, $user_id = false)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;

    $j = "";
    if ($user_id)
        $j = "JOIN $FOF_SUBSCRIPTION_TABLE s ON s.user_id=".intval($user_id)." AND s.feed_id=s.feed_id";
    $result = fof_safe_query("select * from $FOF_FEED_TABLE f $j where f.feed_id=%d", $feed_id);

    if (mysql_num_rows($result) == 0)
        return NULL;

    $row = mysql_fetch_array($result);

    return $row;
}

function fof_db_add_feed($url, $title, $link, $description)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

    fof_safe_query("insert into $FOF_FEED_TABLE (feed_url,feed_title,feed_link,feed_description) values ('%s', '%s', '%s', '%s')", $url, $title, $link, $description);

    return mysql_insert_id($fof_connection);
}

function fof_db_add_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;

    fof_safe_query("insert into $FOF_SUBSCRIPTION_TABLE (feed_id, user_id) values (%d, %d)", $feed_id, $user_id);
}

function fof_db_delete_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_ITEM_TABLE;

    fof_safe_query(
        "delete from s, it using $FOF_ITEM_TAG_TABLE it, $FOF_SUBSCRIPTION_TABLE s, $FOF_ITEM_TABLE i".
        " where i.item_id=it.item_id and s.feed_id=%d and i.feed_id=s.feed_id and it.user_id=%d and s.user_id=it.user_id",
        $feed_id, $user_id
    );
}

function fof_db_delete_feed($feed_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;

    fof_safe_query("delete from $FOF_ITEM_TABLE where feed_id = %d", $feed_id);
    fof_safe_query("delete from $FOF_FEED_TABLE where feed_id = %d", $feed_id);
}


////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

    $result = fof_safe_query("select item_id from $FOF_ITEM_TABLE where feed_id=%d and item_guid='%s'", $feed_id, $item_guid);
    $row = mysql_fetch_array($result);

    if (mysql_num_rows($result) == 0)
        return NULL;
    else
        return($row['item_id']);
}

$fof_db_item_fields = array('item_guid', 'item_link', 'item_title', 'item_author', 'item_content', 'item_cached', 'item_published', 'item_updated');
function fof_db_add_item($feed_id, $item)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection, $fof_db_item_fields;

    $sql = "insert into $FOF_ITEM_TABLE (feed_id, ".implode(",", $fof_db_item_fields).") values (".intval($feed_id);
    foreach ($fof_db_item_fields as $k)
        $sql .= ", '".mysql_real_escape_string($item[$k])."'";
    $sql .= ")";
    fof_db_query($sql);

    return mysql_insert_id($fof_connection);
}

function fof_db_get_items($user_id = 1, $feed = NULL, $what = "unread",
    $when = NULL, $start = NULL, $limit = NULL, $order = "desc", $search = NULL)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;

    $user_id = intval($user_id);
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    if ($prefs['dst'])
        $offset += date('I');

    if (!is_null($when) && $when != "")
    {
        if($when == "today")
            $whendate = fof_todays_date();
        else
            $whendate = $when;

        $whendate = explode("/", $whendate);
        $begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }

    if (is_numeric($start))
    {
        if (!is_numeric($limit))
            $limit = $prefs["howmany"];
        $limit_clause = " limit $start, $limit ";
    }

    $args = array();
    $select = "SELECT STRAIGHT_JOIN i.* , f.*, s.subscription_prefs ";
    $from = "$FOF_ITEM_TABLE i, $FOF_FEED_TABLE f, $FOF_SUBSCRIPTION_TABLE s ";
    $where = "WHERE s.user_id=$user_id AND s.feed_id=f.feed_id AND f.feed_id=i.feed_id";

    if (!is_null($feed) && $feed != "")
        $where .= sprintf(" AND f.feed_id = %d ", $feed);

    $pubdate = 'i.item_published';

    if ($what != "all")
    {
        $alltags = array();
        foreach (fof_db_get_tags($user_id) as $t) // returned from cache
        {
            $alltags[mb_strtolower($t['tag_name'])] = $t;
        }
        $min_tag = false;
        $min_i = false;
        $tags = preg_split("/[\s,]*,[\s,]*/", $what);
        foreach ($tags as $i => $tag)
        {
            $tag = mb_strtolower(trim($tag));
            if (isset($alltags[$tag]))
            {
                $from = "$FOF_ITEM_TAG_TABLE it$i, $from";
                // feed_id condition is redundant, used to speedup match by feed+tag(s)
                $where .= " AND it$i.feed_id=i.feed_id AND it$i.user_id=$user_id".
                    " AND it$i.item_id=i.item_id AND it$i.tag_id=%d";
                $args[] = $alltags[$tag]['tag_id'];
                if (!$min_tag || $min_tag['count'] > $alltags[$tag]['count'])
                {
                    // For several tags it's better to sort using the most rare one
                    $min_tag = $alltags[$tag];
                    $min_i = $i;
                }
            }
        }
        if ($min_i !== false)
        {
            $pubdate = "it$min_i.item_published";
        }
    }

    if (!is_null($when) && $when != "")
        $where .= sprintf(" AND $pubdate > %d AND $pubdate < %d", $begin, $end);

    if (!is_null($search) && $search != "")
    {
        $where .= " AND (i.item_title like '%%%s%%' OR i.item_content like '%%%s%%')";
        $args[] = $search;
        $args[] = $search;
    }

    $order_by = "order by $pubdate desc $limit_clause ";

    $query = "$select FROM $from $where $group $order_by";
    $result = fof_safe_query($query, $args);
    if (mysql_num_rows($result) == 0)
        return array();

    while ($row = mysql_fetch_assoc($result))
    {
        $row['prefs'] = unserialize($row['subscription_prefs']);
        $array[] = $row;
    }

    $array = fof_multi_sort($array, 'item_published', $order != "asc");

    foreach ($array as $i => $item)
    {
        $ids[] = $item['item_id'];
        $lookup[$item['item_id']] = $i;
        $array[$i]['tags'] = array();
    }

    $items = join($ids, ", ");

    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name, $FOF_ITEM_TAG_TABLE.item_id from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id in (%s) and $FOF_ITEM_TAG_TABLE.user_id = %d", $items, $user_id);
    while ($row = fof_db_get_row($result))
    {
        $item_id = $row['item_id'];
        $tag = $row['tag_name'];
        $array[$lookup[$item_id]]['tags'][] = $tag;
    }

    return $array;
}

function fof_db_get_item($user_id, $item_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;

    $query = "select $FOF_FEED_TABLE.feed_image as feed_image, $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_FEED_TABLE, $FOF_ITEM_TABLE where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_ITEM_TABLE.item_id = %d";

    $result = fof_safe_query($query, $item_id);

    $item = mysql_fetch_assoc($result);
    $item['tags'] = array();
    if($user_id)
    {
        $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = %d and $FOF_ITEM_TAG_TABLE.user_id = %d", $item_id, $user_id);
        while($row = fof_db_get_row($result))
            $item['tags'][] = $row['tag_name'];
    }

    return $item;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_subscription_to_tags()
{
    $r = array('filter' => array());
    global $FOF_SUBSCRIPTION_TABLE;
    $result = fof_safe_query("select * from $FOF_SUBSCRIPTION_TABLE");
    while($row = fof_db_get_row($result))
    {
        $prefs = unserialize($row['subscription_prefs']);
        $tags = $prefs['tags'];
        if(!is_array($r[$row['feed_id']]))
            $r[$row['feed_id']] = array();
        $r[$row['feed_id']][$row['user_id']] = $tags;
        if ($prefs['filter'])
        {
            if(!is_array($r['filter'][$row['feed_id']]))
                $r['filter'][$row['feed_id']] = array();
            $r['filter'][$row['feed_id']][$row['user_id']] = $prefs['filter'];
        }
    }

    return $r;
}

function fof_db_get_subscription_prefs($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    $result = fof_safe_query("select subscription_prefs from $FOF_SUBSCRIPTION_TABLE where feed_id = %d and user_id = %d", $feed_id, $user_id);
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    return $prefs;
}

function fof_db_set_subscription_prefs($user_id, $feed_id, $prefs)
{
    global $FOF_SUBSCRIPTION_TABLE;
    return fof_safe_query("update $FOF_SUBSCRIPTION_TABLE set subscription_prefs = '%s' where feed_id = %d and user_id = %d", serialize($prefs), $feed_id, $user_id);
}

// tag feed and all its items
function fof_db_tag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_ITEM_TAG_TABLE, $FOF_ITEM_TABLE;
    $prefs = fof_db_get_subscription_prefs($user_id, $feed_id);
    if(!is_array($prefs['tags']) || !in_array($tag_id, $prefs['tags']))
        $prefs['tags'][] = $tag_id;
    fof_db_set_subscription_prefs($user_id, $feed_id, $prefs);
    fof_safe_query(
        "insert into $FOF_ITEM_TAG_TABLE (tag_id, user_id, item_id, item_published, feed_id)".
        " select %d, %d, item_id, item_published, feed_id from $FOF_ITEM_TABLE where feed_id=%d".
        " on duplicate key update item_published=values(item_published)",
        $tag_id, $user_id, $feed_id
    );
}

function fof_db_set_feedprop($user_id, $feed_id, $prop, $value)
{
    $chg = false;
    $prefs = fof_db_get_subscription_prefs($user_id, $feed_id);
    if ($prefs[$prop] != $value)
    {
        $prefs[$prop] = $value;
        $chg = true;
    }
    fof_db_set_subscription_prefs($user_id, $feed_id, $prefs);
    return $chg;
}

// untag feed and all its items
function fof_db_untag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_ITEM_TAG_TABLE, $FOF_ITEM_TABLE;
    $prefs = fof_db_get_subscription_prefs($user_id, $feed_id);
    if(is_array($prefs['tags']))
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    fof_db_set_subscription_prefs($user_id, $feed_id, $prefs);
    fof_safe_query(
        "delete from it using $FOF_ITEM_TAG_TABLE it, $FOF_ITEM_TABLE i".
        " where it.item_id=i.item_id and it.user_id=%d and it.tag_id=%d and i.feed_id=%d".
        $user_id, $tag_id, $feed_id
    );
}

function fof_db_get_item_tags($user_id, $item_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;

    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = %d and $FOF_ITEM_TAG_TABLE.user_id = %d", $item_id, $user_id);

    return $result;
}

function fof_db_item_has_tags($item_id)
{
    global $FOF_ITEM_TAG_TABLE;

    $result = fof_safe_query("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where item_id=%d and tag_id > 2", $item_id);
    $row = mysql_fetch_array($result);

    return $row["count"];
}

function fof_db_get_unread_count($user_id)
{
    global $FOF_ITEM_TAG_TABLE;

    $result = fof_safe_query("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where tag_id = 1 and user_id = %d", $user_id);
    $row = mysql_fetch_array($result);

    return $row["count"];
}

function fof_db_get_tag_unread($user_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;

    $result = fof_safe_query(
        "select straight_join count(*) as count, it2.tag_id from $FOF_ITEM_TAG_TABLE it, $FOF_ITEM_TAG_TABLE it2 use index (item_id_user_id_tag_id)".
        " where it.item_id = it2.item_id and it.tag_id = 1 and it.user_id = %d and it2.user_id = %d group by it2.tag_id", $user_id, $user_id
    );

    $counts = array();
    while($row = fof_db_get_row($result))
    {
        $counts[$row['tag_id']] = $row['count'];
    }

    return $counts;
}

function fof_db_get_tags($user_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

    static $cache = array();
    if (isset($cache[$user_id]))
    {
        return $cache[$user_id];
    }

    $sql = "SELECT $FOF_TAG_TABLE.tag_id, $FOF_TAG_TABLE.tag_name, count($FOF_ITEM_TAG_TABLE.item_id) as count
        FROM $FOF_TAG_TABLE
        LEFT JOIN $FOF_ITEM_TAG_TABLE ON $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id
        WHERE $FOF_ITEM_TAG_TABLE.user_id = %d
        GROUP BY $FOF_TAG_TABLE.tag_id order by $FOF_TAG_TABLE.tag_name";

    $result = fof_safe_query($sql, $user_id);
    $tags = array();
    while ($row = fof_db_get_row($result))
    {
        $tags[$row['tag_id']] = $row;
    }

    $cache[$user_id] = $tags;

    return $tags;
}

function fof_db_get_tag_id_map()
{
    global $FOF_TAG_TABLE;

    $sql = "select * from $FOF_TAG_TABLE";

    $result = fof_safe_query($sql);

    $tags = array();

    while($row = fof_db_get_row($result))
    {
        $tags[$row['tag_id']] = $row['tag_name'];
    }

    return $tags;
}

function fof_db_create_tag($user_id, $tag)
{
    global $FOF_TAG_TABLE, $fof_connection;

    fof_safe_query("insert into $FOF_TAG_TABLE (tag_name) values ('%s')", $tag);

    return(mysql_insert_id($fof_connection));
}

function fof_db_get_tag_by_name($user_id, $tag)
{
    global $FOF_TAG_TABLE;

    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_id from $FOF_TAG_TABLE where $FOF_TAG_TABLE.tag_name = '%s'", $tag);

    if(mysql_num_rows($result) == 0)
    {
        return NULL;
    }

    $row = mysql_fetch_array($result);

    return $row['tag_id'];
}

function fof_db_mark_unread($user_id, $items)
{
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_read($user_id, $items)
{
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_delete_items($user_id, $items, $check_private)
{
    global $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE;
    if (!$items)
        return;
    if ($check_private)
    {
        // Check if items correspond to $user_id's private feed
        $result = fof_db_query(
            "SELECT i.item_id FROM $FOF_ITEM_TABLE i".
            " LEFT JOIN $FOF_SUBSCRIPTION_TABLE s ON s.feed_id=i.feed_id AND s.user_id != $user_id".
            " WHERE s.feed_id IS NULL AND i.item_id IN (".implode(',', array_map('intval', $items)).")"
        );
        $items = array();
        foreach ($result as $r)
            $items[] = $r['item_id'];
    }
    if (!$items)
        return;
    $items = implode(',', array_map('intval', $items));
    fof_db_query("DELETE FROM $FOF_ITEM_TABLE WHERE item_id IN ($items)");
}

function fof_db_mark_feed_read($user_id, $feed_id)
{
    fof_db_untag_feed($user_id, $feed_id, 1);
}

function fof_db_mark_feed_unread($user_id, $feed_id, $what)
{
    fof_db_tag_feed($user_id, $feed_id, 1);
}

function fof_db_mark_item_unread($id, $except_users = array())
{
    global $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    fof_safe_query(
        "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id, feed_id, item_published)".
        " select s.user_id, 1, i.item_id, i.feed_id, i.item_published from $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s".
        " where i.item_id=%d and i.feed_id=s.feed_id".
        ($except_users ? " and s.user_id not in (".implode(',', array_map('intval', $except_users)).")" : '').
        " on duplicate key update feed_id=values(feed_id), item_published=values(item_published)", $id
    );
}

function fof_db_tag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE, $FOF_ITEM_TABLE;
    if (!$items)
        return;

    $items = implode(',', (array)$items);
    $sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id, item_published, feed_id)".
        " select ".intval($user_id).", ".intval($tag_id).", item_id, item_published, feed_id".
        " from $FOF_ITEM_TABLE where item_id in ($items)".
        " on duplicate key update feed_id=values(feed_id), item_published=values(item_published)";

    fof_db_query($sql, 1);
}

function fof_db_untag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;

    if(!$items)
        return;
    if(!is_array($items))
        $items = array($items);

    $sql = "item_id IN (".implode(",", array_map('intval', $items)).")";
    $sql = "delete from $FOF_ITEM_TAG_TABLE where user_id = %d and tag_id = %d and $sql";

    fof_safe_query($sql, array($user_id, $tag_id));
}

////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_users()
{
    global $FOF_USER_TABLE;

    $result = fof_safe_query("select user_name, user_id, user_prefs from $FOF_USER_TABLE");

    while($row = fof_db_get_row($result))
    {
        $users[$row['user_id']['user_name']] = $row['user_name'];
        $users[$row['user_id']['user_prefs']] = unserialize($row['user_prefs']);
    }
}

function fof_db_add_user($username, $password)
{
    global $FOF_USER_TABLE;

    $password_hash = md5($password . $username);

    fof_safe_query("insert into $FOF_USER_TABLE (user_name, user_password_hash) values ('%s', '%s')", $username, $password_hash);
}

function fof_db_change_password($username, $password)
{
    global $FOF_USER_TABLE;

    $password_hash = md5($password . $username);

    fof_safe_query("update $FOF_USER_TABLE set user_password_hash = '%s' where user_name = '%s'", $password_hash, $username);
}

function fof_db_get_user($username, $userid = NULL)
{
    global $FOF_USER_TABLE;
    if ($username === NULL)
        $result = fof_safe_query("select * from $FOF_USER_TABLE where user_id = %d", $userid);
    else
        $result = fof_safe_query("select * from $FOF_USER_TABLE where user_name = '%s'", $username);
    $row = mysql_fetch_array($result);
    return $row;
}

function fof_update_user($user)
{
    global $FOF_USER_TABLE;
    if (!$user || !$user['user_id'])
        return;
    $values = array();
    $query = "";
    foreach (array('user_name', 'user_password_hash', 'user_level', 'user_prefs') as $k)
    {
        $query .= ($query ? ", " : "") . "`$k`='%s'";
        $values[] = $user[$k];
    }
    $query = "UPDATE $FOF_USER_TABLE SET $query WHERE user_id='%s'";
    $values[] = $user['user_id'];
    array_unshift($values, $query);
    call_user_func_array('fof_safe_query', $values);
}

function fof_db_delete_user($username)
{
    global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    $user = fof_db_get_user($username);
    $user_id = $user['user_id'];

    fof_safe_query("delete from $FOF_SUBSCRIPTION_TABLE where user_id = %d", $user_id);
    fof_safe_query("delete from $FOF_ITEM_TAG_TABLE where user_id = %d", $user_id);
    fof_safe_query("delete from $FOF_USER_TABLE where user_id = %d", $user_id);
}

function fof_db_save_prefs($user_id, $prefs)
{
    global $FOF_USER_TABLE;

    $prefs = serialize($prefs);

    fof_safe_query("update $FOF_USER_TABLE set user_prefs = '%s' where user_id = %d", $prefs, $user_id);
}

function fof_set_current_user($row)
{
    global $fof_user_id, $fof_user_name, $fof_user_level;
    $fof_user_name = $row['user_name'];
    $fof_user_id = $row['user_id'];
    $fof_user_level = $row['user_level'];
    return true;
}

function fof_db_authenticate($user_name, $user_password_hash)
{
    global $FOF_USER_TABLE;

    $result = fof_safe_query("select * from $FOF_USER_TABLE where user_name = '%s' and user_password_hash = '%s'", $user_name, $user_password_hash);

    if (mysql_num_rows($result) == 0)
        return false;

    $row = mysql_fetch_array($result);

    fof_set_current_user($row);

    return true;
}

function fof_db_get_is_duplicate_item($item_id, $item_guid, $content_md5)
{
    global $FOF_ITEM_TABLE, $FOF_USER_TABLE, $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
    $result = fof_safe_query("select * from $FOF_ITEM_TABLE where item_guid='%s' AND item_id!='%d'", $item_guid, $item_id);
    $dups = array();
    while ($row = fof_db_get_row($result))
        if (md5($row['item_content']) == $content_md5)
            $dups[] = intval($row['item_id']);
    if (!count($dups))
        return array();
    $result = fof_db_query(
        "SELECT DISTINCT $FOF_SUBSCRIPTION_TABLE.user_id FROM $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE".
        " WHERE item_id IN (".join(",",$dups).") AND $FOF_SUBSCRIPTION_TABLE.feed_id=$FOF_ITEM_TABLE.feed_id"
    );
    $users = array();
    while ($row = mysql_fetch_row($result))
        $users[] = $row[0];
    return $users;
}

function fof_db_set_feed_custom_title($user_id, $feed_id, $feed_title, $custom_title)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
    $prefs = fof_db_get_subscription_prefs($user_id, $feed_id);
    if (!$prefs)
        return false;
    if (!$custom_title || $feed_title == $custom_title)
        $r = $prefs['feed_title'] = '';
    else
        $r = $prefs['feed_title'] = $custom_title;
    fof_db_set_subscription_prefs($user_id, $feed_id, $prefs);
    return $r;
}

function fof_db_get_top_readers($days, $count = NULL)
{
    global $FOF_ITEM_TABLE, $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE;
    $result = fof_db_query(
"select u.*, count(i.item_id) posts from $FOF_USER_TABLE u
 join $FOF_SUBSCRIPTION_TABLE s on s.user_id=u.user_id
 join $FOF_ITEM_TABLE i on i.feed_id=s.feed_id
 join $FOF_TAG_TABLE t on t.tag_name='unread'
 left join $FOF_ITEM_TAG_TABLE ti on ti.tag_id=t.tag_id and ti.item_id=i.item_id and ti.user_id=u.user_id
 where i.item_published > ".(time()-intval($days*86400))." and ti.tag_id is null
 group by u.user_id order by posts desc
 ".(!is_null($count) ? "limit $count" : ""));
    $readers = array();
    while ($row = mysql_fetch_assoc($result))
        $readers[] = $row;
    return $readers;
}

function fof_db_get_most_popular_feeds($count = NULL)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE;
    $result = fof_db_query(
"select *, count(u.user_id) readers from $FOF_FEED_TABLE f join $FOF_SUBSCRIPTION_TABLE u on u.feed_id=f.feed_id
 left join $FOF_SUBSCRIPTION_TABLE s on s.feed_id=f.feed_id and s.user_id=".fof_current_user()."
 where s.feed_id is null and f.feed_url not regexp '^[a-z]+://[^/]+:[^/]*@'
 group by f.feed_id having readers>1 order by readers desc
 ".(!is_null($count) ? "limit $count" : ""));
    $feeds = array();
    while ($row = mysql_fetch_assoc($result))
        $feeds[] = $row;
    return $feeds;
}

function fof_db_get_feed_single_user($id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    $result = fof_safe_query("SELECT user_id, COUNT(user_id) FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id=%d GROUP BY feed_id", $id);
    $row = mysql_fetch_row($result);
    if (!$row || $row[1] > 1)
        return NULL;
    return $row[0];
}

function fof_cache_fn($key)
{
    if (preg_match('/[^a-z0-9_\-]/', $key))
        $key = 'md5_'.md5($key);
    return dirname(__FILE__).'/cache/'.$key;
}

function fof_cache_get($key)
{
    $file = fof_cache_fn($key);

    $fp = @fopen($file, "rb");
    if (!$fp)
        return NULL;

    $expire = intval(fgets($fp));
    $value = fread($fp, 1048576);
    fclose($fp);

    if (time() > $expire)
    {
        fof_cache_unset($key);
        return NULL;
    }

    return $value;
}

function fof_cache_set($key, $value, $ttl = 86400)
{
    $file = fof_cache_fn($key);
    if ($fp = fopen($file, "wb"))
    {
        fwrite($fp, (time()+$ttl)."\n".$value);
        fclose($fp);
        return true;
    }
    return false;
}

function fof_cache_unset($key)
{
    $file = fof_cache_fn($key);
    @unlink($file);
}
