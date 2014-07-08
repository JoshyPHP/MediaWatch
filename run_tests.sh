#!/bin/bash

phpunit > /tmp/log1.txt &
phpunit > /tmp/log2.txt &
phpunit > /tmp/log3.txt

wait

cat /tmp/log[123].txt

grep -L "^.[[30;42m]*OK" /tmp/log[123].txt > /dev/null || exit 1

exit 0