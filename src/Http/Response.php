<?php

namespace MakeWeb\WordpressTestEnvironment\Http;

use PHPUnit\Framework\Assert;

class Response
{
    protected $statusCode;

    protected $body;

    public function __construct($body, $statusCode)
    {
        $this->body = $body;

        $this->statusCode = $statusCode;
    }

    public function assertSuccessful()
    {
        Assert::assertEquals(200, $this->statusCode);
    }
}
