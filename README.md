# AdvancedUserStats

Mediawiki extension.

## Description

* _AdvancedUserStats_ displays user stats from logging table and reverts.
* Version 1.1.1

## Installation

* Make sure you have MediaWiki 1.39+ installed.
* Download and place the extension to your _/extensions/_ folder.
* Add the following code to your LocalSettings.php: `wfLoadExtension( 'AdvancedUserStats' )`;
* Set access in _LocalSettings.php_:

```php
$wgGroupPermissions['*']['advanceduserstats'] = false;
$wgGroupPermissions['user']['advanceduserstats'] = false;
$wgGroupPermissions['sysop']['advanceduserstats'] = true;
```

## Config

Set multiple stats sections in extension.json

```php

"AUSreports": {
	"value": [[7, 50], [30, 50], [0, 50]],
	"description": "First item: number of days (0=complete), second item: number of displayed users."
}

```

## SpecialPage

_Special:AdvancedUserStats_ - shows patrolled, canceled an reverted edits.

## Internationalization

This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.

## Release Notes

### 1.1

* Fix: "The constant DB_SLAVE/MASTER deprecated in 1.28. Use DB_REPLICA/PRIMARY instead.
* Database structure has changed. SQL selects rewritten for MW 1.36.
* Need rewrite after temp tables (revision_actor_temp, revision_comment_temp) become obsolete.

### 1.1.1

* Database structure has changed in MW 1.36. 
* Revision_actor_temp become obsolete.

## Authors and license

* [Josef Martiňák](https://www.wikiskripta.eu/w/User:Josmart)
* MIT License, Copyright (c) 2023 First Faculty of Medicine, Charles University
