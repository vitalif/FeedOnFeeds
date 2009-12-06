<?php

/* Bug 52453 */
/* Авторегистрация пользователей из CustIS багзиллы */

require_once 'sha256.inc';

/* Багзильное хеширование пароля */
function bz_crypt($password, $salt)
{
    $algorithm = '';
    if (preg_match('/{([^}]+)}$/', $salt, $m))
        $algorithm = $m[1];

    if (!$algorithm)
        return crypt($password, $salt);
    elseif (strtolower($algorithm) == 'sha-256')
    {
        $salt = substr($salt, 0, 8);
        return $salt . substr(base64_encode(pack('H*',sha256($password . $salt))), 0, -1) . '{' . $algorithm . '}';
    }
    return NULL;
}

/* Внешняя аутентификация */
function fof_authenticate_external($login, $password)
{
    if (defined('FOF_EXTERN_DB_DBNAME') &&
        ($extdb = mysql_pconnect(FOF_EXTERN_DB_HOST, FOF_EXTERN_DB_USER, FOF_EXTERN_DB_PASS)) &&
        mysql_select_db(FOF_EXTERN_DB_DBNAME, $extdb))
    {
        mysql_query("SET NAMES ".FOF_DB_CHARSET, $extdb);
        if (($r = mysql_query("SELECT cryptpassword FROM profiles WHERE login_name='".mysql_real_escape_string($login)."' AND disabledtext=''", $extdb)) &&
            ($r = mysql_fetch_row($r)) &&
            (bz_crypt($password, $r[0]) == $r[0]))
            return true;
    }
    return false;
}

function fof_tag_subscribe($userid, $url, $tag)
{
    $id = fof_subscribe($userid, $url);
    if (preg_match('/<!-- (\d+) -->/is', $id, $m))
        fof_tag_feed($userid, 0+$m[1], $tag);
}

/* Добавление фидов для новых юзеров */
function fof_add_default_feeds_for_external($login, $password)
{
    $fof_userid = fof_db_get_user_id($login);
    /* Активность по своим багам */
    fof_tag_subscribe($fof_userid, 'http://'.$login.':'.$password.'@bugs.office.custis.ru/bugs/rss-comments.cgi?ctype=rss&namedcmd=My%20Bugs', 'Me');
    /* Свои коммиты за сегодня */
    if (($extdb = mysql_pconnect(FOF_EXTERN_DB_HOST, FOF_EXTERN_DB_USER, FOF_EXTERN_DB_PASS)) &&
        mysql_select_db(FOF_EXTERN_DB_DBNAME, $extdb))
    {
        mysql_query("SET NAMES ".FOF_DB_CHARSET, $extdb);
        if (($r = mysql_query("SELECT e.address FROM emailin_aliases e, profiles p WHERE p.login_name='".mysql_real_escape_string($login)."' AND e.userid=p.userid AND e.isprimary=1", $extdb)) &&
            ($r = mysql_fetch_row($r)))
        {
            $primary = explode('@', $r[0], 2);
            $primary = preg_quote($primary[0]);
            fof_tag_subscribe($fof_userid, 'http://'.urlencode($primary).':'.urlencode($password).'@viewvc.office.custis.ru/viewvc.py/?view=query&who='.urlencode($primary).'&who_match=exact&querysort=date&date=week&limit_changes=100', 'Me');
        }
    }
    /* IT_Crowd: Новости CustisWiki */
    fof_tag_subscribe($fof_userid, 'http://wiki.office.custis.ru/wiki/rss/Новости_CustisWiki.rss', 'IT_Crowd');
    /* IT_Crowd: Новости TechTools */
    fof_tag_subscribe($fof_userid, 'http://wiki.office.custis.ru/wiki/index.php?title=%D0%91%D0%BB%D0%BE%D0%B3:TechTools&feed=rss', 'IT_Crowd');
    /* IT_Crowd: Новости Cis-Forms */
    fof_tag_subscribe($fof_userid, 'http://wiki.office.custis.ru/wiki/rss/Новости_CustIS_Forms.rss', 'IT_Crowd');
    /* Fun: XKCD */
    fof_tag_subscribe($fof_userid, 'http://www.xkcd.ru/feeds/xkcd/', 'Fun');
    /* Fun: Dilbert */
    fof_tag_subscribe($fof_userid, 'http://dilbertru.blogspot.com/feeds/posts/default', 'Fun');
    /* CustIS: team.custis.ru - корпоративный блог */
    fof_tag_subscribe($fof_userid, 'http://team.custis.ru/feeds/posts/default?alt=rss', 'CustIS');
    /* Ещё, наверное, сюда добавится "Блог Медведева" :) */
    fof_tag_subscribe($fof_userid, 'http://wiki.office.custis.ru/wiki/index.php?title=%D0%91%D0%BB%D0%BE%D0%B3:%D0%92%D0%BE%D0%BB%D0%BE%D0%B4%D1%8F_%D0%A0%D0%B0%D1%85%D1%82%D0%B5%D0%B5%D0%BD%D0%BA%D0%BE&feed=rss', 'CustIS');
}

?>
