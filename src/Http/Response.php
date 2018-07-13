<?php

namespace MakeWeb\WordpressTestEnvironment\Http;

use PHPUnit\Framework\Assert;
use Illuminate\Support\Str;

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

        return $this;
    }

    public function assertSee($string)
    {
        Assert::assertTrue(
            Str::contains($this->body, $string),
            "Failed asserting that string \"$string\" can be found in page body. \n\n{$this->body}"
        );
    }

    /**
     * Assert that the response is a superset of the given JSON.
     *
     * @param  array  $data
     * @param  bool  $strict
     * @return $this
     */
    public function assertJson(array $data, $strict = false)
    {
        Assert::assertArraySubset(
            $data,
            $this->decodeResponseJson(),
            $strict,
            $this->assertJsonMessage($data)
        );
    }

    /**
     * @param  string|null  $key
     * @return mixed
     */
    public function decodeResponseJson($key = null)
    {
        $decodedResponse = json_decode($this->getContent(), true);
        if (is_null($decodedResponse) || $decodedResponse === false) {
            if ($this->exception) {
                throw $this->exception;
            } else {
                Assert::fail('Invalid JSON was returned from the route.');
            }
        }

        return data_get($decodedResponse, $key);
    }

    public function getContent()
    {
        return $this->body;
    }

    /**
     * Get the assertion message for assertJson.
     *
     * @param  array  $data
     * @return string
     */
    protected function assertJsonMessage(array $data)
    {
        $expected = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $actual = json_encode($this->decodeResponseJson(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return 'Unable to find JSON: '.PHP_EOL.PHP_EOL.
            "[{$expected}]".PHP_EOL.PHP_EOL.
            'within response JSON:'.PHP_EOL.PHP_EOL.
            "[{$actual}].".PHP_EOL.PHP_EOL;
    }
}
