# Adige Public API

Este documento consolida a baseline pública do Adige após:
- `0.0.1` estabilização do core
- `0.0.2` estabilização do ORM
- `0.0.3` cleanup do console legado

Objetivo:
- deixar explícito o que aplicações podem usar com expectativa razoável de compatibilidade
- separar API pública de detalhe interno
- consolidar o contrato estável do microframework em um único ponto

## Posicionamento atual

No estado atual, o Adige pode ser tratado como um microframework pequeno com:
- core HTTP/kernel/router estabilizado
- ORM estabilizado no recorte `ActiveRecord` / `Connection` / `Schema`
- fluxo de console consolidado sobre controller/route convencionais

Uso recomendado:
- APIs simples
- ferramentas internas
- aplicações pequenas ou controladas
- cenários onde previsibilidade e baixo acoplamento importam mais do que grande escopo de features

## Decisão arquitetural consolidada

- `Adige` é o entrypoint central do ciclo de execução
- `App` é o configurador/container responsável por handlers, bootstrap e normalização de response
- `Router` resolve rotas explícitas e autodiscovery por convenção
- controllers retornam resultados simples e `App` normaliza isso para `BaseResponse`
- o console usa o mesmo modelo de controller/action do fluxo web

## API pública do core

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

Garantias:
- `Adige::run()` continua sendo o entrypoint principal
- o ciclo passa por request, router, action e response
- o transporte final é normalizado por `App`

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

Garantias:
- rota explícita tem precedência sobre autodiscovery
- entre rotas explícitas compatíveis, segmentos estáticos têm precedência sobre dinâmicos
- `ALL` casa com qualquer método

### Controllers

Arquivo base:
- [src/core/controller/BaseController.php](/home/mathmpr/PhpstormProjects/adige/src/core/controller/BaseController.php)

Contrato:
- controllers expõem actions `action*`
- `beforeAction()` e `afterAction()` participam do ciclo
- actions podem retornar:
  - `BaseResponse`
  - `string`
  - `array`
  - `object`
  - `null`

Garantias:
- o retorno da action é sempre normalizado para `BaseResponse`
- route params têm precedência na injeção de parâmetros da action
- o mesmo modelo de action/retorno vale para web e console

### Request

Arquivos:
- [src/core/BaseRequest.php](/home/mathmpr/PhpstormProjects/adige/src/core/BaseRequest.php)
- [src/core/http/http/WebRequest.php](/home/mathmpr/PhpstormProjects/adige/src/core/http/http/WebRequest.php)
- [src/console/ConsoleRequest.php](/home/mathmpr/PhpstormProjects/adige/src/console/ConsoleRequest.php)

Contrato:
- `BaseRequest` expõe o contrato comum de URI, método e input
- `WebRequest` expõe query params, post body, raw body, files e headers
- `ConsoleRequest` expõe argumentos/opções CLI integrados ao `input()`

Garantias:
- `WebRequest::acceptsJson()` respeita o header `Accept`
- `fixUri()` não depende mais do path físico do projeto
- opções do console são integradas ao ciclo de resolução de actions

### Response

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

Garantias:
- `redirect()` retorna `self`
- headers e status code são manipulados de forma previsível
- `null` em console vira `ConsoleResponse` com exit code `0`
- `null` em web vira resposta vazia ou usa o buffer produzido

### Router e autodiscovery

Arquivos:
- [src/core/routing/Router.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/Router.php)
- [src/core/routing/BaseRoute.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/BaseRoute.php)

Contrato externo:
- o framework suporta rotas explícitas e autodiscovery por convenção
- autodiscovery resolve controller/action por namespaces configurados
- o default route/controller/action continua parte do comportamento suportado

Garantias:
- a geração de candidatos do autodiscovery é determinística
- console e web usam o mesmo núcleo de resolução
- falhas de rota geram exceções semânticas claras

### Tratamento de erros

Arquivo:
- [src/core/ExceptionHandler.php](/home/mathmpr/PhpstormProjects/adige/src/core/ExceptionHandler.php)

Contrato:
- exceções do ciclo principal são tratadas centralmente
- erros são mapeados para respostas HTTP ou saída de console coerentes com o transporte

Garantias:
- `RouteNotFound` mapeia para `404`
- `MethodNotAllowed` mapeia para `405`
- `NotImplemented` mapeia para `501`
- ambiente de produção usa mensagem genérica para falhas internas

## API pública do ORM

### `Connection`

Arquivo:
- [src/core/database/Connection.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/Connection.php)

Contrato:
- representa uma conexão nomeada com seleção explícita de driver
- suporta MySQL e SQLite sem fallback silencioso

Comportamentos públicos estabilizados:
- `getDefaultConnection()` retorna a conexão default ou falha explicitamente
- conexões duplicadas por nome falham explicitamente
- `query()` retorna `PDOStatement`
- `select()` retorna `array`
- `insert()` retorna `lastInsertId()`
- `update()` retorna linhas afetadas
- `delete()` retorna linhas afetadas

Política estabilizada:
- a primeira conexão pode virar default explicitamente
- conexões posteriores não roubam o status de default implicitamente
- erros de conexão são encapsulados por `CantConnectException`
- erros de query sobem como `PDOException`

### `Schema`

Arquivo:
- [src/core/database/Schema.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/Schema.php)

Contrato:
- leitura de schema é explícita e baseada no driver real da conexão
- cache de schema é configurável

Comportamentos públicos estabilizados:
- `getSchema()`
- `pkName()`
- `getFields()`
- `useMemoryCache()`
- `useFileCache()`
- `useCacheStore()`
- `clearCache()`
- `refreshSchema()`
- `refreshAll()`
- `saveCache()`

Garantias:
- leitura normal não grava `schema.json` implicitamente
- cache ausente não é tratado como erro
- atualização de cache depende de fluxo explícito

### `ActiveRecord`

Arquivo:
- [src/core/database/ActiveRecord.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/ActiveRecord.php)

Contrato:
- o modelo principal do ORM continua sendo `ActiveRecord`
- conexões explícitas podem ser passadas ao fluxo de consulta e persistência

Comportamentos públicos estabilizados:
- a conexão de runtime flui por `one()`, `all()`, `build()`, `execute()`, `save()` e `remove()`
- models hidratados preservam a conexão usada na consulta principal
- relações reutilizam a mesma conexão em eager e lazy loading
- `where()` usa grupos lógicos recursivos e operadores explícitos
- eager loading suporta caminhos aninhados por dot notation

## Console consolidado

Arquivos:
- [src/console/ConsoleRequest.php](/home/mathmpr/PhpstormProjects/adige/src/console/ConsoleRequest.php)
- [src/console/controllers/BaseController.php](/home/mathmpr/PhpstormProjects/adige/src/console/controllers/BaseController.php)
- [src/console/CommandCatalog.php](/home/mathmpr/PhpstormProjects/adige/src/console/CommandCatalog.php)

Contrato consolidado:
- comandos de console não são mais registrados por um registry CLI separado
- comandos são executados via controller/action convencionais
- help e sugestões de comando derivam do mesmo contrato de autodiscovery do router

Garantias:
- o console não mantém mais um segundo sistema de comandos legado
- sugestões de comandos aceitam `server/start` e `server:start` para matching
- o comportamento equivalente ao antigo `didYouSay()` foi preservado no fluxo atual

## Itens internos

Os itens abaixo não devem ser tratados como contrato estável de aplicação:

- [src/core/App.php](/home/mathmpr/PhpstormProjects/adige/src/core/App.php)
  - formato interno de `definitions` e `handlers`
  - resolução lazy e `instant => true`
- detalhes internos de reflexão e binding em [src/core/routing/BaseRoute.php](/home/mathmpr/PhpstormProjects/adige/src/core/routing/BaseRoute.php)
- formatação textual/HTML/JSON exata do [src/core/ExceptionHandler.php](/home/mathmpr/PhpstormProjects/adige/src/core/ExceptionHandler.php)
- dialetos internos e builders do ORM:
  - `DsnBuilder`
  - `QueryBuilder`
  - implementações específicas em `dialects/*`
- helpers globais em [src/helpers/functions.php](/home/mathmpr/PhpstormProjects/adige/src/helpers/functions.php)
  - podem ser usados, mas sua organização interna não deve ser tratada como contrato fechado

## Limites e resíduos conhecidos

Itens ainda presentes, mas que não invalidam a baseline estabilizada:

1. `Adige::$app` ainda existe como ponto global relevante.
2. O autodiscovery continua no núcleo do router e mantém acoplamento com convenções de aplicação.
3. O repositório ainda mistura framework e playground de aplicação em partes do layout.
4. O contrato público está estabilizado no recorte atual, mas isso não implica grande escopo de features.

## Compatibilidade esperada

A intenção é preservar:
- `Adige::run()`
- `Route::*()`
- contrato de retorno das actions
- contrato básico de `Request` e `Response`
- comportamento documentado de routing/autodiscovery
- baseline pública de `ActiveRecord`, `Connection` e `Schema`
- fluxo atual de console baseado em controller/action

Continuam mais livres para evolução:
- detalhes internos de `App`
- implementação interna do `ExceptionHandler`
- estrutura dos dialetos do ORM
- organização do playground da aplicação
