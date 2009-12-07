#!/bin/sh

if [ -z "`ps ax | grep php | grep update-quiet | head -n 1 | awk '{print $1}'`" ]; then
    cd `dirname $0` && /usr/bin/php update-quiet.php
fi
