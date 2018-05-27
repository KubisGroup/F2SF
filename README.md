# F2SF
Fler.cz to SuperFaktura.cz synchronization api. Synchronize your paid invoices to superfaktura from fler.cz 

## Requiments
* web server with preffered linux server with LAMP
* GIT
* COMPOSER
* PHP 7.0 and UP
* PHP extentions: json, curl, pdo
* MySQL
* CRON or automation 

## How to install

1) Open terminal and  go to your webserver path

2) Clone GIT reposition
	```bash
	git clone https://github.com/KubisGroup/F2SF.git
	```
3) go to directory
	```bash
	cd ./F2SF
	```
4) Copy config file
	```bash
	cp config.dist.php config.php
5) Edit config file
	```bash
	nano config.php
	```
6) Fill informations for connect to MySQL database
7) Fill all tokens from fler.cz and superfaktura.cz for connect from your account on this sites
8) Run command:
	```bash
	composer install 
	```
9) In terminal run command... api is fully automatized.. Automatically install MySQL tables
	```bash
	cd /path/to/api && clear && php connector.php
	```
10) Install api into CRON hourly
	```bash
	echo "00 *    * * *   www-data php /path/to/api/connector.php" >> /etc/crontab
	```

## FAQ
* How to function?
	* It's so simple. This script is developed for automation CRON for webserver. This script automatically create database tables if don't exists, after read data from fler.cz and save only this statuses (UHRAZENA, VYRIZENA, ODMITNUTA) to superfaktura
* How long of period I can use?
	* It's about you but we recommendet around min 1 hour max 24 hour, but you can use less time
	* for install to crontab use run this code in terminal
```bash
echo "00 *    * * *   www-data php /path/to/api/connector.php" >> /etc/crontab
```
* How I can import orders manually?
	* Open url to your API 
	* Example: [URL]/connector_manual.php?=123456


[![Donate](https://img.shields.io/badge/Donate-PayPal-red.svg?style=plastic)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=U6FBLZZHWRS3J) [![GitHub license](https://img.shields.io/github/license/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/blob/master/LICENSE) [![GitHub issues](https://img.shields.io/github/issues/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/issues) [![GitHub forks](https://img.shields.io/github/forks/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/network) [![GitHub stars](https://img.shields.io/github/stars/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/stargazers) 
