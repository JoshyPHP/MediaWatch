#!/bin/bash

phpunit > /tmp/log1.txt &
REVERSE=1 phpunit > /tmp/log2.txt

wait

cat /tmp/log[12].txt

grep -L "^.[[30;42m]*OK" /tmp/log[12].txt > /dev/null || exit 1

exit 0