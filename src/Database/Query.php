<?php

namespace MakeWeb\WordpressTestEnvironment\Database;

class Query
{
    public $string;

    public $parameters;

    public function __construct($string, $parameters = [])
    {
        $this->string = $string;

        $this->parameters = $parameters;
    }
}
