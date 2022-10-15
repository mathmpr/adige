<?php

namespace peoples\matheus\exercises;

use Adige\cli\Output;

class FirstProgram {

    static function init():void
    {
        Output::cyan("Hello World\n", true);
    }

}
