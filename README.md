# Ádige
Projeto destinado a estudos da linguagem PHP e MySQL principalmente, podendo abranger JS, HTML, CSS. O objetivo é criar passo a passo um simples Framework baseado nos mais populares do mercado.

## Sobre

Ádige é o segundo maior rio da Ítalia, foi e ainda é um dos rios mais importantes do pais desde a idade média. O rio da nome da a região Trentino-Alto Ádige. O nome do projeto é apenas uma homenagem as lembranças de uma viagem que um dos colaborades (@mathmpr) fez a essa região Ítalia. 

## Git rules

Vamos tentar usar o git de forma profissional, para isso vamos estabelecer algumas regras.
- Nunca fazer commit e push direto para a branch **master** nem **develop**.
- Os modelos de branchs devem seguir um padrão:
    - `adjust` - para ajustes de gerais. EX: `adjust/<component-name>/fix-number-of-params-for-bind-value`
    - `hotfix` - quando um bug esta em master, podemos fazer uma branch diretamente de master e então fazer o pull request diretamente para master, apenas para arrumar algum bug "urgente". EX: `hotfix/<component-name>/fix-regex-for-identify-routes`
    - `feature` - quando vamos subir a feature pela primeira vez. EX: `feature/<component-name>`
    - `enhancement` - quando vamos fazer uma melhoria ou refatoração de código. EX: `enhancement/<component-name>/new-router-system`
- Ao fazer o commit, sempre digitar uma mensagem do que tem dentro dele de forma reduzida.
- Ao fazer o pull request, sempre apontamos a nossa branch para `develop` (e não ser que seja um hotfix), e em determinado dia da semana ou mês passamos tudo de develop para `master`.
- Os pull requests devem ter uma descrição do que foi feito nos commits contidos nela.
- O pull request não pode ser **mergeado** na branch alvo enquanto não houver pelo menos um **approve** no pull request e todas as conversas do pull request estiverem resolvidas. 
    
## Objetivos
 - [x] Declarando váriaveis em PHP, tipagem, conceito de refrência e cópia
 - [x] Lexica, semântica e sintaxe (visão geral sobre a linguagem)
 - [x] Criar diretório pessoal para realizar exercicios e arquivos privados
 - [x] Operadores matemáticos
 - [ ] Arrays (listas) e funções para array
 - [ ] Funções, callbacks
 - [ ] Include, require, include once e require once
 - [ ] Namespace de forma detalhada
 - [ ] Estudar conceitos de OO.
    - [ ] O que é um objeto e o que é uma classe.
    - [ ] Entendendo os níveis de acesso `public`, `private` e `protected`.
    - [ ] Diferenças entre métodos estáticos e não estáticos. Entender as propriedades também.
    - [ ] Como funciona o conceito de herança.
    - [ ] Como funciona o conceito de interface.
    - [ ] Como funciona o conceito para classes abstratas.
 - [ ] Estudar os conceitos de [DDD](https://engsoftmoderna.info/artigos/ddd.html).
 - [ ] Criar um componente para **router** para permitir chamadas dos métodos HTTP: GET; POST; OPTIONS; PUT; DELETE.
   - [ ] Estudar o método HTTP.
   - [ ] Entender como funciona um **router**. Estudar referências [laravel](https://laravel.com/docs/9.x/routing), [yii2](https://www.yiiframework.com/doc/guide/2.0/en/runtime-routing) e [slim](https://www.slimframework.com/docs/v4/objects/routing.html).
   - [ ] Implementar algo similar ao básico do **slim** permitindo grupos de rotas.
   - [ ] Permitir **auto discover** route baseado na URI.
   - [ ] Implementar middleware.
 - [ ] Criar componente base para fazer operações basicas e dinamicas no banco de dados MySQL.
    - [ ] A entrada deve ser a **query** e o **array** com os dados para qualquer operação.
    - [ ] Estudar como funciona um **query builder** [ORM](https://www.treinaweb.com.br/blog/o-que-e-orm)   
    - [ ] Implementar um query builder.

## Estrutura do projeto e inicialização

Foi adicionado o composer.json na raiz do projeto para permitir o autoload das classes do sistema que vamos construir. Leia o README.md dentro da pasta /src para mais detalhes.

Para iniciar o projeto com o composer é necessário baixar o composer. Para isso entre na pasta raiz desse projeto e depois execute os comandos abaixo disponíveis [nesta página](https://getcomposer.org/download/).

Se tudo der certo na raiz do projeto terá o arquivo `composer.phar`.

Execute os seguintes comandos nessa ordem: `php composer.phar install` e de seguida `php composer.phar dump-autoload`.
