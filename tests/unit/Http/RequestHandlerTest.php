<?php

namespace Tests\Unit\Http;

use Tests\TestCase;
use MakeWeb\WordpressTestEnvironment\Http\RequestHandler;

class RequestHandlerTest extends TestCase
{
    /** @test */
    public function extract_query_parameters_method_returns_array_for_uri()
    {
        $this->assertEquals(
            ['foo' => 'true', 'bar' => 'false'],
            $this->make(RequestHandler::class)->extractQueryParameters('/test-uri?foo=true&bar=false')
        );
    }

    /** @test */
    public function extract_query_string_method_returns_only_portion_of_uri_after_question_mark()
    {
        $this->assertEquals(
            'foo=true&bar=false',
            $this->make(RequestHandler::class)->extractQueryString('/test-uri?foo=true&bar=false')
        );
    }

    /** @test */
    public function build_uri_method_returns_original_uri_for_uri_without_parameters()
    {
        $this->assertEquals('one/two', $this->make(RequestHandler::class)->buildUri('one/two'));
    }

    /** @test */
    public function build_uri_method_returns_expected_result_for_uri_with_parameters()
    {
        // $this->assertEquals(
        //     '/test-uri?foo=true&bar=false', $this->make(RequestHandler::class)->buildUri('/test-uri?foo=true', ['bar' => 'false'])
        // ); 

        // $this->assertEquals(
        //     'test-uri?foo=true&bar=false', $this->make(RequestHandler::class)->buildUri('test-uri?foo=true', ['bar' => 'false'])
        // ); 

        $this->assertEquals(
            "/makeweb-eddsl-deployer/deploy?token=773d7e20052b2bfc93777ccb290b902e0630d80c7910feb807d4370093207704&filename=test-file.zip&version=1.0.0",
            $this->make(RequestHandler::class)->buildUri(
                "/makeweb-eddsl-deployer/deploy",
                [
                    "token" => "773d7e20052b2bfc93777ccb290b902e0630d80c7910feb807d4370093207704",
                    "filename" => "test-file.zip",
                    "version" => "1.0.0"
                ]
            )
        );
    }
}