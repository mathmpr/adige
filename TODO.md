# TODO: Estabilizar o ORM do Adige para 0.0.2

Este documento define o plano de estabilização `0.0.2`, agora focado na camada ORM.

Escopo desta estabilização:
- estabilizar `ActiveRecord`, `Connection` e `Schema`
- fechar contratos públicos mínimos da camada ORM
- reduzir comportamento implícito e efeitos colaterais inesperados
- cobrir a camada ORM com testes automatizados
- preservar o núcleo HTTP/kernel/router já estabilizado em `0.0.1`

Fora de escopo por enquanto:
- novos recursos grandes de framework fora da camada ORM
- integrações avançadas de migração/seeding
- novas features de HTTP que não sejam exigidas pelo ORM

## Resumo atual

Estado geral estimado:
- progresso aproximado: `10% a 20%`

Ponto de partida do ORM:
- [`src/core/database/ActiveRecord.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/ActiveRecord.php)
- [`src/core/database/Connection.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/Connection.php)
- [`src/core/database/Schema.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/Schema.php)

Principais gaps atuais:
- o contrato público do ORM ainda não está documentado formalmente
- `ActiveRecord` ainda mistura muitas responsabilidades
- `Schema` ainda persiste em arquivo local de forma implícita
- a política de transação/auto-commit ainda precisa de revisão
- praticamente não há suíte de testes dedicada à camada ORM

---

## Fase 0: Congelamento e diagnóstico do ORM

Objetivo:
- estabilizar a direção da camada ORM antes de adicionar comportamento novo

Status atual:
- pendente

Tarefas:
- [ ] Definir a versão alvo de estabilização como `0.0.2`.
- [ ] Documentar a API pública pretendida do ORM:
  - [ ] `ActiveRecord`
  - [ ] `Connection`
  - [ ] `Schema`
- [ ] Classificar o que é API pública e o que é detalhe interno do ORM.
- [ ] Listar comportamentos atuais conhecidos como bug, limitação ou acoplamento.

Critério de aceite:
- existe uma linha clara entre o que aplicações podem usar e o que ainda é detalhe interno do ORM

---

## Fase 1: Definir o contrato de `Connection`

Objetivo:
- tornar previsível a criação, seleção e uso de conexões

Status atual:
- pendente

Tarefas:
- [ ] Revisar o contrato de `Connection::__construct()`.
- [ ] Definir contrato explícito para conexão default.
- [ ] Revisar `Connection::getDefaultConnection()` e seu modo de falha.
- [ ] Revisar política de exceptions de conexão.
- [ ] Revisar política de `autoCommit` e transações.
- [ ] Definir comportamento esperado de:
  - [ ] `query()`
  - [ ] `select()`
  - [ ] `insert()`
  - [ ] `update()`

Critério de aceite:
- abrir, reutilizar e falhar conexões é previsível e documentado

---

## Fase 2: Estabilizar `Schema`

Objetivo:
- tornar a leitura e o cache de schema explícitos e seguros

Status atual:
- pendente

Tarefas:
- [ ] Revisar persistência automática em `schema.json`.
- [ ] Decidir se cache de schema será:
  - [ ] por arquivo
  - [ ] em memória
  - [ ] configurável
- [ ] Definir comportamento quando o schema estiver ausente ou desatualizado.
- [ ] Revisar contrato de:
  - [ ] `getSchema()`
  - [ ] `pkName()`
  - [ ] `getFields()`
- [ ] Remover efeitos colaterais inesperados de leitura/escrita automática quando necessário.

Critério de aceite:
- a camada de schema pode ser entendida e testada sem comportamento implícito excessivo

---

## Fase 3: Estabilizar `ActiveRecord`

Objetivo:
- reduzir ambiguidade na API principal do ORM

Status atual:
- pendente

Tarefas:
- [ ] Documentar o ciclo principal de um model:
  - [ ] `load()`
  - [ ] `save()`
  - [ ] `remove()`
- [ ] Revisar diferenciação entre atributos carregados, novos e alterados.
- [ ] Revisar uso de `__get()` e `__set()` para relations e atributos.
- [ ] Definir contrato de `tableName()` e primary key.
- [ ] Rever comportamento de `save()` em insert vs update.
- [ ] Rever tratamento de erro em `save()` e `remove()`.

Critério de aceite:
- operações básicas de persistência têm comportamento claro, previsível e documentado

---

## Fase 4: Estabilizar query builder embutido

Objetivo:
- garantir previsibilidade na construção de queries

Status atual:
- pendente

Tarefas:
- [ ] Revisar contrato de:
  - [ ] `select()`
  - [ ] `where()`
  - [ ] `andWhere()`
  - [ ] `orWhere()`
  - [ ] `whereIn()`
  - [ ] `join()` / `innerJoin()` / `leftJoin()` / `rightJoin()`
- [ ] Garantir composição previsível de SQL e params.
- [ ] Revisar geração de `rawSql`.
- [ ] Definir limites do query builder para `0.0.2`.

Critério de aceite:
- a construção de queries é previsível, testável e sem efeitos implícitos difíceis de rastrear

---

## Fase 5: Revisar serialização e relations

Objetivo:
- tornar previsível o materializado de models e relations

Status atual:
- pendente

Tarefas:
- [ ] Revisar `toArray()` e materialização de relations.
- [ ] Definir política para campos ocultos (`hidden`).
- [ ] Revisar comportamento de eager loading com `with`.
- [ ] Definir comportamento de `Collection` com models do ORM.
- [ ] Evitar loops ou materializações inesperadas ao serializar.

Critério de aceite:
- models e relations podem ser serializados com previsibilidade suficiente para uso prático

---

## Fase 6: Criar suíte de testes do ORM

Objetivo:
- permitir refatoração segura da camada ORM

Status atual:
- pendente

Tarefas:
- [ ] Criar fixtures mínimas para models e conexões de teste.
- [ ] Cobrir primeiro os componentes críticos:
  - [ ] `Connection`
  - [ ] `Schema`
  - [ ] `ActiveRecord`
- [ ] Criar testes de contrato, não só de implementação.
- [ ] Cobrir ao menos:
  - [ ] conexão default
  - [ ] falha de conexão
  - [ ] leitura de schema
  - [ ] `save()` insert
  - [ ] `save()` update
  - [ ] `remove()`
  - [ ] `toArray()`

Critério de aceite:
- a camada ORM tem cobertura suficiente para estabilização e refatoração com segurança

---

## Prioridades imediatas

Próximos passos com melhor custo/benefício:
- [ ] documentar a API pública mínima do ORM para `0.0.2`
- [ ] revisar e simplificar o contrato de `Connection`
- [ ] decidir a política de cache/persistência de `Schema`
- [ ] começar a suíte de testes da camada ORM
