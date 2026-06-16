# Adige Public API 0.0.1

Este documento define o contrato público pretendido para a estabilização `0.0.1`.

Objetivo:
- deixar explícito o que aplicações podem usar com expectativa razoável de compatibilidade
- separar API pública de detalhe interno
- registrar comportamentos conhecidos que ainda não são considerados “fechados”

## Versão alvo

- estabilização alvo: `0.0.1`

## Posicionamento de uso

Na linha `0.0.1`, o Adige já pode ser tratado como um microframework estabilizado.

Uso recomendado:
- projetos pequenos
- ferramentas internas
- APIs simples
- cenários controlados em produção

Ressalvas:
- não é o objetivo desta versão competir em escopo com frameworks grandes e amplamente consolidados
- a camada HTTP/kernel/router está mais madura do que a camada ORM

## Decisão arquitetural

- `Adige` permanece como ponto central do ciclo de execução
- `Adige` faz o papel de kernel do framework, mantendo esse nome
- `App` é o container/configurador responsável por handlers, bootstrap e normalização de response

## API pública

### `Adige::run()`

Arquivo:
- [src/core/Adige.php](/home/mathmpr/PhpstormProjects/adige/src/core/Adige.php)

Contrato:
- inicializa o ambiente comum
- instancia a aplicação configurada
- executa o ciclo principal
- trata exceções via `ExceptionHandler`

Uso esperado:

```php
use app\core\Adige;

Adige::run();
```

Garantias de `0.0.1`:
- `Adige::run()` continua sendo o entrypoint principal
- o ciclo de execução passa por request, router, action e response

### `Route::*()`

Arquivo:
- [src/core/routing/Route.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/Route.php)

API pública:
- `Route::get()`
- `Route::post()`
- `Route::put()`
- `Route::delete()`
- `Route::patch()`
- `Route::options()`
- `Route::head()`
- `Route::all()`
- `Route::group()`

Contrato:
- registram rotas explícitas
- aceitam `callable` ou controller/action
- `group()` aplica prefixo e middleware de grupo

Garantias de `0.0.1`:
- rota explícita tem precedência sobre autodiscover
- entre rotas explícitas compatíveis, segmentos estáticos têm precedência sobre dinâmicos
- `ALL` casa com qualquer método HTTP

### `Controller`

Arquivo base:
- [src/core/controller/BaseController.php](/home/mathmpr/PhpstormProjects/adige/src/core/controller/BaseController.php)

Contrato:
- controllers podem implementar `action*`
- `beforeAction()` e `afterAction()` participam do ciclo
- `respond()` cria `BaseResponse` coerente com o transporte atual

Retornos aceitos de actions em `0.0.1`:
- `BaseResponse`
- `string`
- `array`
- `object`
- `null`

Observação:
- actions web e console compartilham o mesmo modelo de retorno flexível
- a normalização final para `BaseResponse` é responsabilidade de `App`

### `Request`

Arquivos:
- [src/core/BaseRequest.php](/home/mathmpr/PhpstormProjects/adige/src/core/BaseRequest.php)
- [src/core/http/http/WebRequest.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/WebRequest.php)
- [src/console/ConsoleRequest.php](/home/mathmpr/PhpstormProjects/adige/src/console/ConsoleRequest.php)

Contrato:
- `BaseRequest` expõe `getUri()`, `getUriParts()`, `getMethod()`, `input()`
- `WebRequest` expõe query, post, raw body, files e headers
- `ConsoleRequest` expõe argumentos/opções CLI integrados ao `input()`

Garantias de `0.0.1`:
- route params têm precedência absoluta na injeção de parâmetros de action
- `WebRequest::acceptsJson()` respeita `Accept` de forma case-insensitive
- `fixUri()` não depende mais do path físico do projeto

### `Response`

Arquivos:
- [src/core/BaseResponse.php](/home/mathmpr/PhpstormProjects/adige/src/core/BaseResponse.php)
- [src/core/http/http/WebResponse.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/WebResponse.php)
- [src/console/ConsoleResponse.php](/home/mathmpr/PhpstormProjects/adige/src/console/ConsoleResponse.php)

Especializações:
- [src/core/http/http/JsonResponse.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/JsonResponse.php)
- [src/core/http/http/FileResponse.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/FileResponse.php)
- [src/core/http/http/RedirectResponse.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/RedirectResponse.php)

Contrato:
- toda emissão final passa por um `BaseResponse`
- `WebResponse` lida com status, headers e body
- `ConsoleResponse` lida com `stdout`, `stderr` e `exitCode`

Garantias de `0.0.1`:
- `redirect()` retorna `self`
- `Content-Type` é resolvido de forma previsível
- headers são emitidos em ordem determinística
- `JsonResponse` respeita `Content-Type` explícito quando fornecido

### `ActiveRecord`

Arquivo base:
- [src/core/database/ActiveRecord.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/ActiveRecord.php)

Status em `0.0.1`:
- faz parte da superfície pública existente
- mas não é foco principal desta estabilização

Garantias limitadas:
- o core HTTP/router/response é prioridade de compatibilidade
- a camada ORM ainda pode sofrer ajustes mais frequentes do que o núcleo HTTP

## Detalhes internos

Os itens abaixo não devem ser tratados como contrato estável de aplicação:

- [src/core/App.php](/home/mathmpr/PhpstormProjects/adige/src/core/App.php)
  - formato interno de definitions/handlers
  - resolução lazy e `instant => true`
- [src/core/ExceptionHandler.php](/home/mathmpr/PhpstormProjects/adige/src/core/ExceptionHandler.php)
  - detalhes de payload HTML/JSON
  - formatação textual de erro
- [src/core/routing/Router.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/Router.php)
  - helpers internos de matching/specificity
- [src/core/routing/BaseRoute.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/BaseRoute.php)
  - detalhes de reflexão e injeção interna
- `helpers` globais em [src/helpers/functions.php](/home/mathmpr/PhpstormProjects/adige/src/helpers/functions.php)
  - permanecem utilizáveis, mas sua estrutura interna não deve ser tomada como API estável além do comportamento já coberto em testes

## Comportamentos conhecidos e limites atuais

Itens conhecidos em `0.0.1`:

1. `Adige::$app` ainda é ponto global forte.
2. O autodiscover continua no núcleo e aumenta o acoplamento do router com convenções de aplicação.
3. A precedência do autodiscover está documentada, mas a decisão de mantê-lo no núcleo ainda pode ser revista depois de `0.0.1`.
4. A cobertura de `WebRequest` ainda é menor do que a de router/response.
5. `ActiveRecord` ainda não recebeu o mesmo nível de estabilização formal do núcleo HTTP.
6. O playground da aplicação (`app/`, entrypoints e exemplos) ainda convive no mesmo repositório do core, então a separação entre pacote distribuível e app de desenvolvimento ainda não está completamente limpa.

## Compatibilidade esperada

Durante a linha `0.0.1`, a intenção é preservar:
- `Adige::run()`
- `Route::*()`
- contrato de retorno das actions
- contrato básico de `Request` e `Response`
- comportamento documentado do roteador

Mudanças ainda mais livres:
- detalhes internos de `App`
- implementação do `ExceptionHandler`
- refinamentos da camada ORM
- organização do playground de desenvolvimento
