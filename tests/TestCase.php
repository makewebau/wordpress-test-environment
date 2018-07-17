<?php

namespace Tests;

use Illuminate\Container\Container;

class TestCase extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->container = new Container;
    }

    public function make($class)
    {
        return $this->container->make($class);
    }    
}
