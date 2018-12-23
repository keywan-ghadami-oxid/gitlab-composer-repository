# Gitlab Composer Repository

Small script that loops through all branches and tags of all projects in a Gitlab installation
and if it contains a `composer.json`, adds it to an index.

This is very similar to the behaviour of Packagist.org / packagist.com


## Requirements
 * Php
 * Apache Web Server
 * Gitlab (can be hosted on a different domain)
 
## Installation

 1. Run `composer.phar install`
 2. Copy `confs/samples/gitlab.ini` into `confs/gitlab.ini`, following instructions in comments
 3. Ensure `cache` directory is writable
 
https://www.codefactor.io/repository/github/signalr/signalr/badge?style=plastic

## Quality 
* You are reading the documentation

* messurement is never perfect but important to improve
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/4c3f8944eb064dc99a15715dde4e55c1)](https://app.codacy.com/app/keywan.ghadami/gitlab-composer-repository?utm_source=github.com&utm_medium=referral&utm_content=keywan-ghadami-oxid/gitlab-composer-repository&utm_campaign=Badge_Grade_Settings)
[![CodeFactor](https://www.codefactor.io/repository/github/keywan-ghadami-oxid/gitlab-composer-repository/badge)](https://www.codefactor.io/repository/github/keywan-ghadami-oxid/gitlab-composer-repository)

* efficiency by using longliving caches (for happy users and our environment)

* security (how to proove? audits and ideas welcome)


## Usage

Simply include a composer.json in your project, all branches and tags respecting 
the [formats for versions](http://getcomposer.org/doc/04-schema.md#version) will be detected.

Then, to use your repository, add this in the `composer.json` of your project:
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://your_gitlab_composer_repo_url"
        }
    ],
    "config": {
        "gitlab-domains" : [
            "your_gitlab_composer_repo_url"
        ]
    },
}
```
now you require every package that is hosted on the gitlab server.

### Authentication
On the first usage of composer it will ask for you credentials. 
With that composer will require a outh token that will be stored in your auth.json for all following requests.
In case you have activated two-factor login in your gitlab account, you have to create a gitlab token and store it in you auth.json manually.

You can use the following command template by replacing 
- [composer-repository.yourcompany.com] with the domain for your repository (this software running on a domain)
- [yourtoken] with the token generated within gitlab
```
composer config -g gitlab-oauth.[composer-repository.yourcompany.com] [yourtoken]
```

Ater first run file `packages.json` in `cache` directory must exist. Also for ech group/repository an own directory.


#### CI
If your CI needs access to the repository you add inject an environment varible for composer
```
COMPOSER_AUTH='{"gitlab-oauth": {"[composer-repository.yourcompany.com]": "[citoken]"}}'
```

### Adding new Package Version
The Gitlab Composer Repository takes care for adding webhooks to be informed about new tags, so adding a new version will automatically and immediately be reflected. Deleting a tag and by that a package versions requires a cache clear.


### Local Development
In case you like to develop on this software and run the service locally you may want to add 
"secure-http": false and 127.0.0.1 in the gitlab-domain section. E.g:
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://your_gitlab_composer_repo_url"
        }
    ],
    "config": {
        "secure-http": false,
        "gitlab-domains" : [
            "your_gitlab_composer_repo_url",
            "127.0.0.1"
        ]
    },
}
```


## Caveats (missing features and known bugs)
 * there is no frontend other then gitlab itself to manage users (if have to manage customers you may need to connect your CRM to gitlab) If you face this issue, detailed feature request are welcome.

## Changelog

 * 0.05 update docu
 * 0.04 fix: download archives 
 using now gitlab API instead of frontend url because that is not supported in new gitlab version 
 * 0.03 fix: making packigename case insensitive
 * 0.02 refactoring, remove static repository feature, proxy dist downloads,   
 * 0.01 initial release

## Author
 * [Keywan Ghadami]
 * [Tobias Merkl]
 * [Sébastien Lavoie](http://blog.lavoie.sl/2013/08/composer-repository-for-gitlab-projects.html)
 * [WeMakeCustom](http://www.wemakecustom.com)

