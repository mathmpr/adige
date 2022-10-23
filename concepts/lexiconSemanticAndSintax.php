<?php

/*
 * Assim como em outras linguagens, o PHP possui a parte lexica, semantica e sintaxe.
 *
 * lexica: conjunto de palavras reservadas disponiveis dentro da linguagem
 * semantica: sentido que as palavras têm dentro da linguagem
 * sintaxe: análisa a forma com que as plavras conectam entre si
 */

/*
 * em PHP sempre usamos ponto e virgula no fim da linha, exceto quando declaramos um array (lista)
 */

$variavel = 'Hello world';

/* lista em multiplas linhas */
$lista = [
    11,
    12,
    13
];

/* lista em uma linha */

$lista2 = [11, 12, 13];

/* lista associativa */

$lista3 = ['a' => 1, 'b' => 2];

/* acessando a posição de uma lista */

echo $lista3['a']; // mostrará 1
echo $lista2[0];   // mostrará 2

/* declarando uma função */

function minhaFuncao()
{

}

/* declarando uma função com um parametro obrigatório */

function minhaFuncao2($valor)
{

}

/* declarando uma função com parametros sendo o último com um valor default */

function minhaFuncao3($valor, $valor2 = 5)
{

}

/* declarando uma função com parametros tipados e com retorno definido por tipo sendo o último parametro declarado com um valor default */

function somar(int $valor, int $valor2 = 5): int
{
    return $valor + $valor2;
}

/* atribuindo uma função sem nome a uma váriavel (nesse caso precisamos do ; apos fechar } ) */

$callback = function (string $valor) {

};

/* interface, classe abstrata e classe */

interface Calculadora
{
    public function pegaInput();
}

abstract class InputDaCalculadora implements Calculadora
{

    protected string $input1;
    protected string $input2;

    public function soma(int $valor1, int $valor2)
    {
        return $valor1 + $valor2;
    }

}

class MinhaCalculadora extends InputDaCalculadora
{
    public function pegaInput()
    {
        $this->input1 = readline('Digite o valor 1: ');
        $this->input2 = readline('Digite o valor 2: ');
    }
}