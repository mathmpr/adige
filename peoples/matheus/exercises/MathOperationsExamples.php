<?php

namespace peoples\matheus\exercises;

use Adige\cli\OutputLn;

class MathOperationsExamples {

    static function init():void {

        $positiveNumberA = 5;
        $positiveNumberB = 9;
        $negativeNumberA = -9;
        $negativeNumberB = -12;

        OutputLn::cyan("resultado da soma $positiveNumberA + $positiveNumberB: " . $positiveNumberA + $positiveNumberB);

        OutputLn::cyan("resultado da subtração $positiveNumberA - $positiveNumberB: " . $positiveNumberA - $positiveNumberB);

        OutputLn::cyan("resultado da multiplicação $positiveNumberA * $positiveNumberB: " . $positiveNumberA * $positiveNumberB);

        OutputLn::cyan("resultado da divisão $positiveNumberA / $positiveNumberB: " . $positiveNumberA / $positiveNumberB);

        OutputLn::yellow("\n*************************\n");

        OutputLn::cyan("resultado da soma $negativeNumberA + $negativeNumberB: " . $negativeNumberA + $negativeNumberB);

        OutputLn::cyan("resultado da subtração $negativeNumberA - $negativeNumberB: " . $negativeNumberA - $negativeNumberB);

        OutputLn::cyan("resultado da multiplicação $negativeNumberA * $negativeNumberB: " . $negativeNumberA * $negativeNumberB);

        OutputLn::cyan("resultado da divisão $negativeNumberA / $negativeNumberB: " . $negativeNumberA / $negativeNumberB);

        OutputLn::yellow("\n*************************\n");

        OutputLn::cyan("resultado da potenciação $positiveNumberA ** $positiveNumberA: " . $positiveNumberA ** $positiveNumberA);

        OutputLn::cyan("resultado do resto da divisão entre $positiveNumberB e % $positiveNumberA: " . $positiveNumberB % $positiveNumberA);

    }

}
