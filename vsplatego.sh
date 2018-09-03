#!/bin/bash

service mysqld start || service mysql start && mysql -u root -ptoor -e 'create database ecshop;';
mkdir /var/www/html/tmp;
wget -O /var/www/html/tmp/ECShop_3.6.0_UTF8_installed.zip http://test-1251457471.cosgz.myqcloud.com/ECShop_3.6.0_UTF8_vuln_Installed.zip && unzip -d /var/www/html/tmp /var/www/html/tmp/ECShop_3.6.0_UTF8_installed.zip && mv /var/www/html/tmp/ECShop_3.6.0_UTF8_installed/source/* /var/www/html/ && rm -rf /var/www/html/tmp;
chmod -R 777 /var/www/html;
