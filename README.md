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


## Details

* Running a couple of times (with some delay) recommended. Server can be down at this exact moment.
* Stats are stored in CSV file. New line is appended only once a day.
* In case of Wikifarm with one shared _extensions_ folder, we can create CSV files for all sites.


## SpecialPage

_Special:AdvancedUserStats_ - shows patrolled, canceled an reverted edits.


## Internationalization
This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.


## Authors and license

* [Josef Martiňák](https://bitbucket.org/josmart/)
* MIT License, Copyright (c) 2018 First Faculty of Medicine, Charles University




