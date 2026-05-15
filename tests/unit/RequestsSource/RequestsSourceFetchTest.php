<?php

/**
 * This file contains the RequestsSourceFetchTest class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\RequestsSource;

/**
 * This class contains core fetch tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch() does not throw an error if the request was successful.
     */
    public function testFetchDoesNotThrowErrorIfRequestSuccessful(): void
    {
        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->logger->shouldReceive('error')
                     ->never();

        $config = [ 'url' => 'http://localhost/url' ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() returns the request result on success.
     */
    public function testFetchReturnsResultsOnSuccessfulRequest(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body = $json;

        $config = [ 'url' => 'http://localhost/url' ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() logs the URL on a successful request.
     */
    public function testFetchLogsOnSuccessfulRequest(): void
    {
        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body = '{}';

        $config = [ 'url' => 'http://localhost/url' ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() supports specifying URL parameters.
     */
    public function testFetchSupportsSpecifyingUrlParams(): void
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

        $this->response->body = $json;

        $config = [
            'url'    => 'http://localhost/url',
            'params' => [ 'param1' => 'value1' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() supports specifying Headers.
     */
    public function testFetchSupportsSpecifyingHeaders(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [ 'Header' => 'value' ], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body = $json;

        $config = [
            'url'     => 'http://localhost/url',
            'headers' => [ 'Header' => 'value' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() supports specifying the HTTP method to use.
     */
    public function testFetchSupportsSpecifyingHttpMethod(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [ 'param1' => 'value1' ], 'POST', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->body = $json;

        $config = [
            'url'    => 'http://localhost/url',
            'method' => 'POST',
            'params' => [ 'param1' => 'value1' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() uses only datetime params when params is not an array.
     */
    public function testFetchUsesOnlyDatetimeParamsWhenParamsNotArray(): void
    {
        $format = 'Y-m-d H:i:s';
        $tz     = 'Asia/Dubai';

        $fromDate = new DateTime('00:00:00', new DateTimeZone($tz));
        $toDate   = new DateTime('23:59:59', new DateTimeZone($tz));

        $expectedParams = [
            'FromDate' => $fromDate->format($format),
            'ToDate'   => $toDate->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], $expectedParams, 'POST', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once();

        $this->response->body = '{}';

        $config = [
            'url'             => 'http://localhost/url',
            'method'          => 'POST',
            'params'          => '{"some": "JSON"}',
            'datetime_params' => [
                'FromDate' => [
                    'value'    => '00:00:00',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
                'ToDate'   => [
                    'value'    => '23:59:59',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
            ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ '{}' ], $result);
    }

    /**
     * Test that fetch() supports specifying the configuration options for a request.
     */
    public function testFetchSupportsSpecifyingOptions(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [ 'param1' => 'value1' ], 'GET', [ 'key' => 'value' ])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url?param1=value1');

        $this->response->body = $json;

        $config = [
            'url'     => 'http://localhost/url',
            'params'  => [ 'param1' => 'value1' ],
            'options' => [ 'key' => 'value' ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

}

?>
