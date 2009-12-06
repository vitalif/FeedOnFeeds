#!/bin/sh

export http_proxy="http://proxy.custis.ru:3128/"
export no_proxy=".office.custis.ru, 172.29.0.0/22, mail01.custis.ru"

if [ -z "`ps ax | grep php | grep update-quiet | head -n 1 | awk '{print $1}'`" ]; then
    cd `dirname $0` && /usr/bin/php update-quiet.php
fi
