#!/bin/bash
set -e

rm -rf /run/httpd
mkdir -p /run/httpd

/usr/sbin/crond

/usr/sbin/httpd $*

