<?php

/**
 * This file contains the RequestsSourceFetchPaginationTest class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\RequestsSource;

/**
 * This class contains pagination tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchPaginationTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch() handles pagination correctly.
     */
    public function testFetchWithPagination(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [ 'param1' => 'value1' ], 'GET', [])
                   ->andReturn($this->response);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url?page=2', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->twice();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?param1=value1');

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?page=2');

        $this->response->body    = $json;
        $this->response->headers = [
            'link' => '<http://localhost/url?page=2>; rel="next", <http://localhost/url?page=2>; rel="last"'
        ];

        $config = [
            'url'        => 'http://localhost/url',
            'params'     => [ 'param1' => 'value1' ],
            'pagination' => [ 'header' => 'link' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json, $json ], $result);
    }

    /**
     * Test that fetch() logs correctly with pagination.
     */
    public function testFetchLogsWithPagination(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [ 'param1' => 'value1' ], 'GET', [])
                   ->andReturn($this->response);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url?page=2', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->twice();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?param1=value1');

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?page=2');

        $this->response->body    = $json;
        $this->response->headers = [
            'link' => '<http://localhost/url?page=2>; rel="next", <http://localhost/url?page=2>; rel="last"'
        ];

        $config = [
            'url'        => 'http://localhost/url',
            'params'     => [ 'param1' => 'value1' ],
            'pagination' => [ 'header' => 'link' ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() stops pagination when the header value is not a string.
     */
    public function testFetchWithPaginationStopsWhenHeaderNotString(): void
    {
        $json = json_encode([ 'data' => 1 ]);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body    = $json;
        $this->response->headers = [ 'link' => NULL ];

        $config = [
            'url'        => 'http://localhost/url',
            'pagination' => [ 'header' => 'link' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() handles a malformed link entry without a semicolon.
     */
    public function testFetchWithPaginationSkipsMalformedLinkEntry(): void
    {
        $json = json_encode([ 'data' => 1 ]);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body    = $json;
        $this->response->headers = [ 'link' => 'malformed-no-semicolon' ];

        $config = [
            'url'        => 'http://localhost/url',
            'pagination' => [ 'header' => 'link' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() stops pagination when on the last page without a next link.
     */
    public function testFetchWithPaginationLastPageWithoutNext(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [ 'param1' => 'value1' ], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?param1=value1');

        $this->response->body    = $json;
        $this->response->headers = [ 'link' => '<http://localhost/url>; rel="last"' ];

        $config = [
            'url'        => 'http://localhost/url',
            'params'     => [ 'param1' => 'value1' ],
            'pagination' => [ 'header' => 'link' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

}

?>
