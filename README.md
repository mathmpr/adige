# Ádige
Project aimed at studying the PHP and MySQL languages mainly, which can cover JS, HTML, CSS. The objective is to create, step by step, a simple Framework based on the most popular ones on the market.

## About

Adige is the second-largest river in Italy, it was and still is one of the most important rivers in the country since the Middle Ages. The river gives its name to the Trentino-Alto Adige region. The name of the project is just a tribute to the memories of a trip that one of the collaborators (@mathmpr) took to this region of Italy.

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

## Project structure and startup

Composer.json was added to the root of the project to allow autoloading of the system classes we are going to build. Read the README.md inside the /src folder for more details.

To start the project with composer you need to download composer. To do this, enter the root folder of this project and then execute the commands below available [on this page](https://getcomposer.org/download/).

If everything goes well, the root of the project will have the file `composer.phar`.

Run the following commands in this order: `php composer.phar install` and then `php composer.phar dump-autoload`.
