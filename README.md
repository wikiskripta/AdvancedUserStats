# AdvancedUserStats

Mediawiki extension.

## Description

_AdvancedUserStats_ displays user stats from logging table and reverts.


## Installation

* Make sure you have MediaWiki 1.29+ installed.
* Download and place the extension to your _/extensions/_ folder.
* Add the following code to your LocalSettings.php: `wfLoadExtension( 'AdvancedUserStats' )`;
* Set access in _LocalSettings.php_:
```
$wgGroupPermissions['*']['advanceduserstats'] = false;
$wgGroupPermissions['user']['advanceduserstats'] = false;
$wgGroupPermissions['sysop']['advanceduserstats'] = true;
```


## Config

Set multiple stats sections in extension.json
```
"AUSreports": {
	"value": [[7, 50], [30, 50], [0, 50]],
	"description": "First item: number of days (0=complete), second item: number of displayed users."
}
```

## SpecialPage

_Special:AdvancedUserStats_ - shows patrolled, canceled an reverted edits.


## Internationalization
This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.


## Authors and license

* [Josef Martiňák](https://bitbucket.org/josmart/)
* MIT License, Copyright (c) 2018 First Faculty of Medicine, Charles University




