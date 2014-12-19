What is FeedOnFeeds?
--------------------

FeedOnFeeds is a lightweight server-based RSS aggregator and reader,
allowing you to keep up with syndicated content (blogs, comics, and so
forth) without having to keep track of what you've read. Being
server-based means all of your feeds and history are kept in one
place, and being lightweight means you can install it pretty much
anywhere without needing a fancy dedicated server or the like.

FeedOnFeeds 0.5 is originally written by Steve Minutillo.
This is a fork of FeedOnFeeds 0.5 by Vitaliy Filippov.

FeedOnFeeds is distributed under the terms of GNU GPL v2 license, see LICENSE.

New features in this version compared to the original 0.5
---------------------------------------------------------

* Performance of all queries is greatly improved, basically almost everything
  is fast even if you have lots of unread and/or tagged items (100000+)
* HTTP proxy support through standard environment variables http_proxy, no_proxy
* Password-protected feed support (HTTP basic/digest)
* Personal feed rename support (each user can rename feeds to his will)
* Possible to set tags for a feed when adding it
* The view is paged by default (you have to specify a big limit by hand to view all items)
* Mass feed tagging/untagging from the preferences page
* Per-feed and per-item collapse settings: you can set some feeds to show all items
  collapsed by default or you can configure a regular expression which will specify
  items that should be collapsed by default in each feed
* Most popular feed suggestions on the subscribe page
* Top reader statistics on the login page
* Very simple CSS-based mobile view
* Tables are using InnoDB, UTF-8 encoding, and foreign keys
* Code is cleaned of PHP warnings/notices and compatible with PHP 5.4+

TODO
----

* Implement safer authentication (sessions?) than current password-hash-in-cookie
* Replace SimplePie (not "simple" in any way) with something simpler and faster... (MagPie?)
* Dynamic feed update times, similar to https://github.com/RomanSixty/Feed-on-Feeds
* Use multi-cURL to download feeds in parallel

Requirements
------------

* A web server running PHP 5 or later (nginx + php5-fpm or Apache).
* PHP extensions: mysql/mysqlnd, XML, PCRE, cURL, Zlib, mbstring, iconv.
* MariaDB/MySQL 5 or later. MariaDB 5.5 or later with Barracuda storage format
  (innodb_file_format = barracuda) is recommended.

Installation
------------

* Download a snapshot or checkout code from git repository into installation directory.
* Create 'cache' directory inside installation directory and make it writable by the web server.
* Create a MySQL database and a user with full access to it. If MySQL server is on the
  same host it looks like:

        CREATE DATABASE feedonfeeds;
        GRANT ALL PRIVILEGES ON feedonfeeds.* TO feedonfeeds@localhost IDENTIFIED BY '<password>';
        FLUSH PRIVILEGES;

* Copy fof-config-sample.php to fof-config.php and edit FOF_DB_HOST, FOF_DB_USER, FOF_DB_PASS
  and FOF_DB_DBNAME as appropriate for your newly created database.
* Point your browser to `<FoF_URL>/install.php`.

Upgrade
-------

It is possible to upgrade an existing FeedOnFeeds 0.5 MySQL installation to this version.
Database will be converted automatically. Just overwrite all files in FoF installation
directory with this version and point your browser to `<FoF_URL>/install.php`.
