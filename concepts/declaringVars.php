<?php

/* para declarar variáveis em PHP num contexto normal (fora de uma classe) precisamos sempre adicionar o $ na frente. */

$primeraVariavel = null;

/* as variáveis podem ser de qualquer tipo e trocam de tipo automaticamente, pois o PHP é uma linguagem fracamente tipada */
/* isso vem mudando com o tempo (vem funcionando desde a versão 7.4 do PHP), hoje podemos utilizar tipos e isso é fortemente recomendado */

/* de forma geral as variáveis podém ter qualquer nome, desde que não incluam caractres especiais ou espaços nem começem com um número */

/*
 * $1errado = "isso vai dar erro";
 * $#errado = "isso vai dar erro";
 * $erra do = "isso vai dar erro";
 */

$primeraVariavel = "mudei para o tipo string";

$primeraVariavel = 123;

/* tudo acima vai fucionar normalmente */
/* uma variável pode ser do tipo cópia ou referência */

$euCopioOValorDe = $primeraVariavel;

$euFacoReferenciaA = &$primeraVariavel;

echo $euCopioOValorDe;

/* mostra 123 como resultado */

echo $euFacoReferenciaA;

/* mostra 123 como resultado */

$euFacoReferenciaA = 321;

echo $primeraVariavel;

/* mostra 321 como resultado */

/*
 * Porque o valor de $primeraVariavel mudou de 123 para 321 sendo que em nenhum momento fizemos tal atribuição?
 * Isso aconteceu porque $euFacoReferenciaA é uma referência direta para $primeraVariavel, ou seja,
 * A referência foi criada na linha 27 usando o operado & logo após o = sendo assim,
 * $euFacoReferenciaA indica o mesmo endereço de mémoria que $primeraVariavel
 */

echo $euCopioOValorDe;

/* mostra 123 como resultado */

/*
 * Acima o valor mostrado ainda é 123, pois na linha 25 fizemos a cópia do valor atual em $primeraVariavel
 * para dentro de $euCopioOValorDe e agora o valor de $primeraVariavel ainda era 123, se isso acontece
 * após a linha 37, o valor de $euCopioOValorDe seria 321
 */

/*
 * De forma geral isso é tudo sobre declaração de variáveis em PHP e isso já da base de como outras linguagens funcionam também
 * no PHP existem algumas funções auxiliaes que devolvem booleanos para testar o que é uma variável
 * is_string(), is_bool(), is_null(), is_int() são algumas delas. Outra devolve explicitamente o tipo da variável gettype()
 */