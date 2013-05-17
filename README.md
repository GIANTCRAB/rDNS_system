rDNS_system
===========

rDNS system for Limestone Networks' SolusVM clients. 

This system is only for Limestone Networks clients who are using SolusVM. 

Disclaimer
-------------
* CSRF, XSS and SQL injection protection are at best effort with testing done. 
* rDNS system's developer(s) will fix CSRF, XSS SQL (or any) exploits and (any) bugs that are reported or are found. 
* rDNS system's developer(s) will NOT hold any responsibility for CSRF, XSS, SQL exploits due to WHMCS product's incompentency. 
* rDNS system is STILL using MySQL (deprecated) because WHMCS only supports MySQL and does not support PDO or MySQLi.

Requirements
-------------
* Minimum PHP 5.1
* PHP CURL
* PHP MySQL
* WHMCS user
* SolusVM master url
* SolusVM master API id
* SolusVM master API key
* Limestone Networks API key

Features
-------------
* CSRF protection
* XSS protection
* SQL injection protection

Installation
-------------
1. Place the files into your WHMCS folder.
2. Edit `rdns_system_config.php` to your settings.
3. Make sure that you have enabled API access for your WHMCS server's IP address to Limestone Networks and your SolusVM server.
4. ????
5. Profit!!!11oneoneleven

Tested and worked fine on WHMCS version 5.1.3, SolusVM version 1.13.03, 23 March 2013 Limestone Networks API.
