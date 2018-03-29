# F2SF
Fler.cz to SuperFaktura.cz synchronization api. Synchronize your paid invoices to superfaktura from fler.cz 

## Introductions

1) Open connector.php
2) Fill informations for connect to MySQL database
3) Fill all tokens from fler.cz and superfaktura.cz for connect from your account on this sites
4) Open terminal and  run command:
```bash
composer install 
```
5) In terminal run 
```bash
cd /path/to/api && clear && php connector.php
```

## FAQ
* How to function?
	* It's so simple. This script is developed for automation CRON for webserver. This script automatically create database tables if don't exists, after read data from fler.cz and save only paid (UHRAZENA - status) to superfaktura
* How long of period I can use?
	* It's about you but we recommendet around min 1 hour max 24 hour, but you can use less time


[![Donate](https://img.shields.io/badge/Donate-PayPal-red.svg?style=plastic)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=U6FBLZZHWRS3J) [![GitHub license](https://img.shields.io/github/license/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/blob/master/LICENSE) [![GitHub issues](https://img.shields.io/github/issues/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/issues) [![GitHub forks](https://img.shields.io/github/forks/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/network) [![GitHub stars](https://img.shields.io/github/stars/KubisGroup/F2SF.svg?style=plastic)](https://github.com/KubisGroup/F2SF/stargazers) 