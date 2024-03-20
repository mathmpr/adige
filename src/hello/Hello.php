<?php

namespace Adige\hello;

use Adige\cli\Output;

class Hello
{
    public static function hello():void {
        Output::yellow("\nHello guys, console works fine\n\n")
            ->bgBlue()
            ->output();
    }

}
