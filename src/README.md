## Padrão da estrutura

Dentro dessa pasta podemos adicionar novas pasta, estas são as partes do sistema. Se quisermos criar uma classe para gerir as rotas.

`src/http/Router.php` teriamos o namespace `Adige\http` e nos arquivos PHP onde quisermos usar essa classe `Router` vamos colocar no topo do arquivo: `use Agide\http\Router`.

Sempre que uma nova classe for criada é bom executarmos na raiz do projeto: `php composer.phar dump-autoload`.