# Ádige

Adige is a small PHP framework with a focused core around:
- HTTP and console request handling
- explicit routing with controller autodiscovery
- a lightweight `ActiveRecord` ORM
- file-based views
- migrations and model validation

## About

Adige is the second-largest river in Italy, it was and still is one of the most important rivers in the country since the Middle Ages. The river gives its name to the Trentino-Alto Adige region. The name of the project is just a tribute to the memories of a trip that one of the collaborators (@mathmpr) took to this region of Italy.

## Release target

The current release target is `1.0.0-alpha`.

At this stage, Adige can be described as a stabilized microframework for the current line:
- small and focused
- tested around its HTTP core, console flow, ORM, migrations and validation layer
- suitable for small, controlled and serious projects

Important scope note:
- Adige is not positioned as a general replacement for large, battle-tested frameworks
- the current scope is best suited for small applications, internal tools, simple APIs and controlled production environments
- the package baseline is now aligned for the `1.0.0-alpha` distribution model

Public API and compatibility notes for this target are documented in:
- [PUBLIC_API.md](/home/mathmpr/PhpstormProjects/adige/PUBLIC_API.md)

## Git rules

Let's try to use git in a professional way, for this we will establish some rules.
- Try to never commit and push directly to the branch **master** or **develop**.
- Branch models must follow a standard:
    - `adjust` - for general adjustments. EX: `adjust/<component-name>/fix-number-of-params-for-bind-value`
    - `hotfix` - When a bug is in master, we can make a branch directly from master and then make a pull request directly to master, just to fix some "urgent" bug. EX: `hotfix/<component-name>/fix-regex-for-identify-routes`
    - `feature` - when we upload the feature for the first time. EX: `feature/<component-name>`
    - `enhancement` - when we are going to make a code improvement or refactoring. EX: `enhancement/<component-name>/new-router-system`
- When committing, always type a message about what is inside it in a reduced form.
- When making a pull request, we always point our branch to `develop` (and unless it is a hotfix), and on a certain day of the week or month we move everything from develop to `master`.
- Pull requests must have a description of what was done in the commits contained in it.
- The pull request cannot be **merged** into the target branch until there is at least one **approve** on the pull request and all pull request conversations are resolved.
    
## Goals
 - [x] Study OO concepts.
   - [x] What is an object and what is a class.
   - [x] Understanding the `public`, `private` and `protected` access levels.
   - [x] Differences between static and non-static methods. Understand the properties too.
   - [x] How the concept of inheritance works.
   - [x] How the interface concept works.
   - [x] How the concept for abstract classes works.
 - [x] Study the concepts of [DDD](https://engsoftmoderna.info/artigos/ddd.html).
 - [x] Create a component for **router** to allow calls to the HTTP: GET methods; POST; OPTIONS; PUT; DELETE.
   - [x] Study the HTTP method.
   - [x] Understand how a **router** works. Study references [laravel](https://laravel.com/docs/9.x/routing), [yii2](https://www.yiiframework.com/doc/guide/2.0/en/runtime-routing) and [slim](https://www.slimframework.com/docs/v4/objects/routing.html).
   - [x] Implement something similar to the basics of **slim** allowing groups of routes.
   - [x] Allow **auto discover** route based on URI.
   - [x] Implement middleware.
- [x] Create base component to perform basic and dynamic operations on the MySQL database.
   - [x] The input must be the **query** and the **array** with the data for any operation.
   - [x] Study how a **query builder** [ORM](https://www.treinaweb.com.br/blog/o-que-e-orm) works
   - [x] Implement a query builder.

## Installation

Install the package in a consumer project with Composer:

```bash
composer require trentino-alto/adige
```

The package exposes its console entrypoint through Composer bin proxies:

```bash
vendor/bin/adige
```

This is the main command entrypoint of the framework.

## Package consumption

Adige is now distributed as a Composer library.

The important runtime paths are:
- `package root`: where the framework code itself lives
- `vendor dir`: the consumer project's Composer `vendor/`
- `basePath`: the consumer project root

The console launcher already passes the project root automatically:

```php
Adige::run(null, getcwd());
```

If you start the framework manually, pass the consumer base path explicitly:

```php
use Adige\core\Adige;

Adige::run(null, __DIR__);
```

## Minimal web consumer

Example project structure:

```text
my-app/
├── bootstrap.php
├── public/
│   └── index.php
├── controllers/
│   └── IndexController.php
└── composer.json
```

Example `composer.json` for the consumer project:

```json
{
  "require": {
    "trentino-alto/adige": "1.0.0-alpha"
  },
  "autoload": {
    "psr-4": {
      "App\\": ""
    }
  }
}
```

Example `bootstrap.php`:

```php
<?php

use Adige\core\Adige;
use Adige\core\App;
use Adige\core\BaseView;

return [
    Adige::VIEW_HANDLER => [
        'class' => BaseView::class,
        '__construct()' => [
            __DIR__ . '/views',
        ],
    ],
    Adige::ROUTER_HANDLER => [
        'class' => Adige\core\routing\Router::class,
        '__construct()' => [
            '@request',
            '@response',
            true,
            'index',
        ],
        'controllerNamespaces' => [
            'App\\controllers',
        ],
    ],
];
```

Example `public/index.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Adige\core\Adige;

Adige::run(null, dirname(__DIR__));
```

Example `controllers/IndexController.php`:

```php
<?php

namespace App\controllers;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex(): string
    {
        return 'Hello from Adige';
    }
}
```

## Minimal console consumer

Once the package is installed, console commands run from the project root:

```bash
vendor/bin/adige
vendor/bin/adige migrate/create --name=create-users-table
vendor/bin/adige migrate/up
```

The recommended command flow is:
- use `vendor/bin/adige` as the primary framework command
- use it to start the built-in PHP server
- use it to create and run migrations
- use it to generate the optional project-root launcher

Examples:

```bash
vendor/bin/adige server/start
vendor/bin/adige migrate/create --name=create-users-table
vendor/bin/adige migrate/up
vendor/bin/adige install/index
```

If you want a project-root launcher, generate one after installation:

```bash
vendor/bin/adige install/index
```

That command creates `./adige` in the consumer project root and the generated file resolves:
- `./vendor/autoload.php`
- `Adige::run(null, __DIR__)`

The generated `./adige` file is an optional shortcut.
It does not replace `vendor/bin/adige` as the official package entrypoint.

## Configuration notes

The current package defaults are intentionally small:
- console controllers default to `Adige\console\controllers`
- web controller autodiscovery should be configured explicitly through `controllerNamespaces`
- bootstrap files are discovered from the consumer `basePath`
- migrations default to `<basePath>/migrations`
- `.env` is loaded from the consumer `basePath` first

## Tests

The core test suite is based on PHPUnit.

Run the full unit suite:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Unit
```

Run a single test file:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Unit/Routing/RouterFindRouteTest.php
```

Current tests focus on framework contracts such as:
- router matching and autodiscovery precedence
- route parameter resolution
- middleware short-circuit and failure handling
- response normalization
- HTTP request/response behavior without a real web server
- migrations, validators and package/runtime path behavior
