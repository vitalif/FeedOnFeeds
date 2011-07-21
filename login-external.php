<?php

/* (зачёркнуто) Bug 52453 - Авторегистрация пользователей из CustIS багзиллы */
/* Bug 63447 - Single Sign-On по багзилле */

require_once 'urandom.inc';

function fof_require_user_hook()
{
    /* CustIS Bug 63447 - Single Sign-On по багзилле */
    if (defined('FOF_GLOBALAUTH_URL') &&
        !$_COOKIE['logged_out'])
    {
        try
        {
            $data = globalauth(FOF_GLOBALAUTH_URL);
            foreach ($data['user_email_aliases'] as $email)
                if ($user = fof_db_get_user($email))
                    break;
            if (!$user)
            {
                /* регистрируем нового пользователя без пароля */
                fof_db_add_user($data['user_email'], NULL);
                $user = fof_db_get_user($data['user_email']);
                if (!$user)
                    die("database error");
                $adddefault = true;
            }
            /* обновляем данные пользователя, если только что приняли авторизацию */
            if ($_REQUEST['ga_id'])
            {
                $prefs = unserialize($user['user_prefs']);
                if (!$prefs)
                    $prefs = array();
                $prefs['globalauth'] = $data;
                $user['user_prefs'] = serialize($prefs);
                $user['user_name'] = $data['user_email'];
                fof_update_user($user);
                if ($adddefault)
                    fof_add_default_feeds_for_external($user);
                header("Location: ".globalauth_clean_uri());
                exit;
            }
            fof_set_current_user($user);
            return true;
        }
        catch (Exception $e)
        {
            fof_log("Global auth: $e");
        }
    }
    /* а если мы здесь, значит, и глобальная авторизация тоже не удалась */
    return false;
}

// обработка запросов к глобальной авторизации (клиентская сторона)
function globalauth_handle()
{
    $cookiename = 'globalauth';
    $id = $_REQUEST['ga_id'];
    if (!$id)
        $id = $_COOKIE[$cookiename];
    if ($id)
    {
        // получение данных авторизации от сервера
        $key = $_REQUEST['ga_key'];
        if ($key && $_REQUEST['ga_client'] && $key == fof_cache_get("ga-key-$id"))
        {
            fof_cache_unset("ga-key-$id");
            if ($_REQUEST['ga_nologin'])
                $d = 'nologin';
            else
                $d = $_REQUEST['ga_data'];
            if ($d)
            {
                fof_cache_set("ga-data-$id", $d);
                print "1";
                exit;
            }
        }
        // возвращаем данные для дальнейших действий
        elseif (!$key && ($d = fof_cache_get("ga-data-$id")))
        {
            if ($_COOKIE[$cookiename] != $id)
                setcookie($cookiename, $id, -1);
            if ($d && $d != 'nologin')
                $d = (array)@json_decode(utf8_decode($d));
            return $d;
        }
    }
}

function globalauth_clean_uri($params = array())
{
    $p = $_GET+$_POST;
    unset($p['ga_id']);
    unset($p['ga_client']);
    unset($p['ga_key']);
    unset($p['ga_data']);
    unset($p['ga_nologin']);
    unset($p['ga_res']);
    $params += $p;
    return 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
}

// глобальная авторизация
// если она что-то возвращает, а не падает на хрен и не делает exit, то это данные авторизации :)
// ещё она может бросить Exception с каким-нибудь текстом
function globalauth($url, $require = true)
{
    if ($authdata = globalauth_handle())
        return $authdata;
    if (!$url)
        throw new Exception(__FUNCTION__.": globalauth_url is unset");
    $id = unpack('H*', urandom(16));
    $id = $id[1];
    $key = unpack('H*', urandom(16));
    $key = $key[1];
    $url .= (strpos($url, '?') !== false ? '&' : '?');
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url."ga_id=$id&ga_key=$key");
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $content = curl_exec($curl);
    $r = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($content)
    {
        $return = globalauth_clean_uri(array('ga_client' => 1));
        fof_cache_set("ga-key-$id", $key);
        // Авторизуй меня, Большая Черепаха!!!
        header("Location: ${url}ga_id=$id&ga_url=".urlencode($return).($require ? "" : "&ga_check=1"));
        exit;
    }
    throw new Exception(__FUNCTION__.": error getting ${url}ga_id=$id&ga_key=$key: HTTP $r");
}

function fof_tag_subscribe($userid, $url, $tag)
{
    $id = fof_subscribe($userid, $url);
    if (preg_match('/<!-- (\d+) -->/is', $id, $m))
        fof_tag_feed($userid, 0+$m[1], $tag);
}

/* Добавление фидов для новых юзеров */
function fof_add_default_feeds_for_external($user)
{
    $fof_userid = $user['id'];
    $login = $user['user_name'];
    $primary = explode('@', $login, 2);
    $primary = $primary[0];
    /* Активность по своим багам */
    fof_tag_subscribe($fof_userid, 'http://bugs.office.custis.ru/bugs/rss-comments.cgi?ctype=rss&namedcmd=My%20Bugs&fof_sudo=1', 'Me');
    /* Свои коммиты за сегодня */
    fof_tag_subscribe($fof_userid, 'http://viewvc.office.custis.ru/viewvc.py/?view=query&who='.urlencode(preg_quote($primary)).'&who_match=exact&querysort=date&date=week&limit_changes=100&fof_sudo=1', 'Me');
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
