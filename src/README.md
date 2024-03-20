## Structure pattern

Within this folder we can add new folders, these are the parts of the system. If we want to create a class to manage routes.

`src/http/Router.php` we would have the namespace `Adige\http` and in the PHP files where we want to use this `Router` class we will put it at the top of the file: `use Agide\http\Router`.

Whenever a new class is created, it is a good idea to run it at the root of the project: `php composer.phar dump-autoload`.