# Limit Logins

<p>
  <a href="https://github.com/lipemat/limit-logins/releases/latest">
    <img alt="Version" src="https://img.shields.io/packagist/v/lipemat/limit-logins.svg?label=version" />
  </a>
  <img alt="WordPress" src="https://img.shields.io/badge/wordpress->=6.4.0-green.svg">
  <img alt="PHP" src="https://img.shields.io/packagist/php-v/lipemat/limit-logins.svg?color=brown" />
  <img alt="License" src="https://img.shields.io/packagist/l/lipemat/limit-logins.svg">
</p>

WordPress plugin that limits the number of concurrent logins for a user.

If you really want to prevent brute force attacks and are not concerned with annoying your legitimate users, this plugin may be for you.

### Tracks

- User ID
- IP Address

If the same IP or username fails to login more than 5 times then neither the user nor the IP will be able to login for 12 hours.

### Purpose

I had been using other limit login attempts plugins for a long time. Every time an attacker was able to find a way to attempt more logins than the set number, I wrote another extension and unit tests. While writing around something like 30 tests, I realized that no third-party plugin was ever going to provide the desired level of security. There is simply too much concern over affecting legitimate users.

This plugin is the combination of every extension and unit test I wrote for the other plugins.

Sorry attackers, but I'm over you. :-p


### Installation
``` sh 
composer require lipemat/limit-logins
```
### Usage

``` php
require __DIR__ . '/vendor/autoload.php'
```

### Notes

This plugin is intended to be used within an [OnPoint Plugins](https://onpointplugins.com) project. It is likely going to have a lot of assumptions that are specific to our projects. 
