<?php

namespace Tests\Support;

use Adige\core\App;

class TestApp extends App
{
    public function init(): void
    {
        $this->definitions = [];
        $this->handlers = [];
    }
}
