# Adige Public API

Este documento consolida a baseline pública do Adige após:
- `0.0.1` estabilização do core
- `0.0.2` estabilização do ORM
- `0.0.3` cleanup do console legado
- `0.0.4` events and lifecycle
- `0.0.5` views, migrations and validators

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
- `render()` delega para o componente de view configurado no app
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
- quando um componente `view` está configurado, controllers podem renderizar views por nome lógico

### Views

Arquivo base:
- [src/core/BaseView.php](/home/mathmpr/PhpstormProjects/adige/src/core/BaseView.php)

Configuração relacionada:
- `Adige::VIEW_HANDLER`

Contrato:
- `BaseView` é o componente público de renderização síncrona por arquivos PHP
- a resolução padrão usa um diretório base configurado por instância
- aliases explícitos podem ser registrados, por exemplo `@shared/partial`
- o formato do render continua sendo:
  - `render(string $view, array $params = []): string`

Comportamentos públicos estabilizados:
- `escape()` expõe escaping HTML básico
- `setViewDirectory()` e `getViewDirectory()` controlam o diretório base
- `registerAlias()` e `getAliases()` controlam aliases explícitos
- parâmetros da view são expostos por `extract(..., EXTR_SKIP)`

Garantias:
- o diretório de views é estado de instância, não global da classe
- renderizações consecutivas não vazam caminho resolvido entre chamadas
- nomes de views rejeitam path traversal e paths absolutos
- falhas durante o render limpam corretamente o output buffer

Eventos públicos de lifecycle:
- `BaseView::EVENT_BEFORE_RENDER`
- `BaseView::EVENT_AFTER_RENDER`
- `BaseView::EVENT_RENDER_ERROR`

Uso esperado:
- aplicações configuram o handler `view` no app/bootstrap
- controllers usam `render()` para delegar a composição ao `BaseView`
- aliases são explícitos e não dependem de convenções mágicas de raiz do projeto

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

Eventos públicos de lifecycle:
- `BaseRequest::EVENT_BEFORE_INIT`
- `BaseRequest::EVENT_AFTER_INIT`
- `BaseRequest::EVENT_BEFORE_FIX_URI`
- `BaseRequest::EVENT_AFTER_FIX_URI`

Uso esperado:
- listeners podem ser registrados por instância com `on()`
- listeners globais podem ser registrados por classe base via [`src/core/events/Event.php`](/home/mathmpr/PhpstormProjects/adige/src/core/events/Event.php)
- listeners globais registrados em `BaseRequest::class` alcançam subclasses como `WebRequest` e `ConsoleRequest`

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

Eventos públicos de lifecycle:
- `BaseResponse::EVENT_BEFORE_DISPATCH`
- `BaseResponse::EVENT_AFTER_DISPATCH`

Uso esperado:
- listeners globais registrados em `BaseResponse::class` alcançam `WebResponse`, `ConsoleResponse` e subclasses

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

Eventos públicos de lifecycle:
- `ExceptionHandler::EVENT_BEFORE_HANDLE_THROWABLE`
- `ExceptionHandler::EVENT_AFTER_HANDLE_THROWABLE`
- `ExceptionHandler::EVENT_BEFORE_RENDER_WEB_THROWABLE`
- `ExceptionHandler::EVENT_AFTER_RENDER_WEB_THROWABLE`

Uso esperado:
- esses eventos podem ser usados para logging, métricas e auditoria
- o listener recebe o `Throwable` e, quando aplicável, o `WebResponse`

### App lifecycle

Arquivo:
- [src/core/App.php](/home/mathmpr/PhpstormProjects/adige/src/core/App.php)

Contrato externo:
- `App` continua sendo o ponto de normalização de response e emissão final do transporte
- `App` continua sendo o ponto de resolução lazy de handlers configurados, incluindo `view` e `migrations`

Eventos públicos de lifecycle:
- `App::EVENT_BEFORE_NORMALIZE_RESPONSE`
- `App::EVENT_AFTER_NORMALIZE_RESPONSE`
- `App::EVENT_BEFORE_EMIT_RESPONSE`
- `App::EVENT_AFTER_EMIT_RESPONSE`

Uso esperado:
- listeners podem observar o resultado bruto da action antes da normalização
- listeners podem observar o `BaseResponse` final antes e depois do dispatch pelo app
- isso é útil para logs, tracing, métricas e integração transversal sem acoplar no kernel
- aplicações podem fornecer configuração explícita para:
  - `Adige::VIEW_HANDLER`
  - `Adige::MIGRATIONS_CONFIG`

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
- `save()` valida o model antes de persistir por default
- `save($connection, true)` permite skip explícito de validações
- `validate()` executa as rules declaradas no model
- erros de validação ficam disponíveis por `addError()`, `getErrors()`, `clearErrors()` e `hasErrors()`

Eventos públicos de lifecycle:
- `ActiveRecord::EVENT_BEFORE_INSERT`
- `ActiveRecord::EVENT_AFTER_INSERT`
- `ActiveRecord::EVENT_BEFORE_UPDATE`
- `ActiveRecord::EVENT_AFTER_UPDATE`
- `ActiveRecord::EVENT_BEFORE_DELETE`
- `ActiveRecord::EVENT_AFTER_DELETE`
- `ActiveRecord::EVENT_BEFORE_LOAD`
- `ActiveRecord::EVENT_AFTER_LOAD`
- `ActiveRecord::EVENT_BEFORE_HYDRATE`
- `ActiveRecord::EVENT_AFTER_HYDRATE`

Uso esperado:
- listeners podem ser registrados por instância com `on()`
- listeners globais podem ser registrados por classe base ou concreta via [`src/core/events/Event.php`](/home/mathmpr/PhpstormProjects/adige/src/core/events/Event.php)
- isso permite validar, auditar, transformar estado e produzir side effects ao redor do lifecycle do model

### `ActiveRecord` validators

Arquivos:
- [src/core/database/validators/ValidatorInterface.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/validators/ValidatorInterface.php)
- [src/core/database/validators/AbstractValidator.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/validators/AbstractValidator.php)
- [src/core/database/validators](/home/mathmpr/PhpstormProjects/adige/src/core/database/validators)

Contrato:
- models podem declarar `rules(): array`
- cada rule segue o formato geral:
  - `[['field'], 'validator']`
  - `[['field'], 'validator', 'namedParam' => 'value']`
  - `[['field'], CustomValidator::class, ...$args]`
- validators built-in e customizados compartilham o mesmo fluxo por `ValidatorInterface`

Built-ins públicos estabilizados:
- `required`
- `boolean`
- `integer`
- `number`
- `string`
- `minLength`
- `maxLength`
- `in`
- `compare`
- `date`
- `url`
- `mask`
- `email`
- `unique`

Aliases públicos suportados:
- `bool`
- `int`
- `float`
- `double`
- `decimal`
- `min_length`
- `max_length`
- `range`
- `match`
- `regex`
- `regexp`
- `datetime`

Garantias:
- validadores recebem o model, os fields, os params normalizados e a conexão opcional
- `unique` suporta unicidade simples e composta
- o formato composto de `targetAttribute` faz parte do contrato suportado, por exemplo:

```php
[['date', 'app'], 'unique', 'targetAttribute' => ['date', 'app'], 'message' => 'The combination of Date and App has already been taken.']
```

Uso esperado:
- regras de negócio locais ao model devem viver em `rules()`
- aplicações podem fornecer validators customizados por classe, desde que implementem `ValidatorInterface`

### `Migration`

Arquivos:
- [src/core/database/Migration.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/Migration.php)
- [src/core/database/MigrationField.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/MigrationField.php)
- [src/core/database/MigrationIndex.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/MigrationIndex.php)
- [src/core/database/MigrationDialect.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/MigrationDialect.php)

Dialetos públicos de suporte:
- [src/core/database/dialects/mysql/MysqlMigration.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/mysql/MysqlMigration.php)
- [src/core/database/dialects/sqlite/SqliteMigration.php](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/sqlite/SqliteMigration.php)

Contrato:
- migrations são arquivos descobertos por filename
- o filename é a identidade canônica da migration
- um arquivo de migration deve retornar uma instância de `Migration`
- a conexão pode ser injetada após a construção via `setConnection()`

Comportamentos públicos estabilizados:
- `createTable()`
- `dropTable()`
- `addColumn()`
- `dropField()`
- `field()` para criar `MigrationField`
- `index()` para criar `MigrationIndex`
- `executeUp()`
- `executeDown()`
- `MigrationField` expõe um builder fluente com:
  - `type()`
  - `integer()`
  - `string()`
  - `text()`
  - `boolean()`
  - `timestamp()`
  - `nullable()`
  - `notNull()`
  - `default()`
  - `primary()`
  - `unique()`
  - `autoIncrement()`
- `MigrationIndex` expõe um builder fluente com:
  - `columns()`
  - `name()`
  - `unique()`

Garantias:
- a tabela de metadata `migrations` é garantida antes da execução
- a metadata inclui:
  - `name`
  - `batch`
  - `created_at`
- `executeUp()` roda migrations pendentes dentro do fluxo transacional do framework
- `executeDown()` reverte pelo mesmo fluxo
- o framework suporta MySQL e SQLite sem fallback silencioso também para migrations
- `createTable()` aceita `MigrationField` e `MigrationIndex` no mesmo array de definição
- índices simples e compostos são compilados de forma explícita para MySQL e SQLite

Uso esperado:
- migrações novas usam o formato:

```php
use Adige\core\database\Migration;

return new class extends Migration {
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
```

- aplicações podem configurar o path de discovery por `Adige::MIGRATIONS_CONFIG`
- migrations podem definir índices, por exemplo:

```php
$this->createTable('posts', [
    $this->field('id')->integer()->autoIncrement(),
    $this->field('title')->string(120)->notNull(),
    $this->index('title'),
    $this->index(['title', 'published'], 'posts_title_published_unique')->unique(),
]);
```

### Console migrations

Arquivo:
- [src/console/controllers/MigrateController.php](/home/mathmpr/PhpstormProjects/adige/src/console/controllers/MigrateController.php)

Contrato:
- o console expõe um fluxo público mínimo de migrations sobre o modelo controller/action

Comandos públicos estabilizados:
- `migrate/create --name=...`
- `migrate/up`
- `migrate/down`
- `migrate/down --steps=2`

Garantias:
- `migrate/up` aplica todas as pendentes em um único novo batch
- `migrate/down` sem `steps` reverte o último batch
- `migrate/down --steps=N` reverte os `N` batches mais recentes
- o diretório default de migrations é `ROOT/migrations` quando nenhuma configuração explícita é fornecida

## Sistema de eventos

Arquivos:
- [src/core/events/Observable.php](/home/mathmpr/PhpstormProjects/adige/src/core/events/Observable.php)
- [src/core/events/Event.php](/home/mathmpr/PhpstormProjects/adige/src/core/events/Event.php)

Contrato consolidado:
- objetos observáveis expõem `on()` e `trigger()`
- listeners podem ser registrados por instância
- listeners globais podem ser registrados por classe com `Event::on()`
- listeners globais registrados em uma classe base alcançam subclasses

Garantias:
- o primeiro argumento do listener é o objeto emissor
- argumentos extras do trigger são repassados ao listener
- `Event::clear()` existe para limpeza explícita de listeners globais

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
  - os eventos do lifecycle são públicos, mas o layout exato da saída de erro não é
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
