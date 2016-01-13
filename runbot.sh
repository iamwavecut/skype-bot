#!/usr/bin/env bash
DIR=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
pgrep skype > /dev/null
#if [[ $? != '0' ]]; then
    echo "Skype not running, waiting 20 secs"
    /bin/sleep 20
#else
#    echo "Skype already running, waiting 5 secs"
#    /bin/sleep 5
#fi
echo "Continuing"
while (true)
do
    DISPLAY=:0 /usr/bin/php ${DIR}/bot.php
    /bin/sleep 5
done

$SHELL
