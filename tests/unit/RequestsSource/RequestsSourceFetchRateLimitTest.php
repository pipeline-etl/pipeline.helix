<?php

/**
 * This file contains the RequestsSourceFetchRateLimitTest class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\Common\Exceptions\SourceException;
use Pipeline\RequestsSource;

/**
 * This class contains rate limiting tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchRateLimitTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch() applies rate limiting correctly.
     */
    public function testFetchWithRateLimit(): void
    {
        $output = [
            'param1' => 1,
            'param2' => 2,
        ];

        $json = json_encode($output);

        $this->limiter->shouldReceive('throttle')
                      ->once()
                      ->with('localhost', 5, 1000);

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
            'url'        => 'http://localhost/url',
            'params'     => [ 'param1' => 'value1' ],
            'rate-limit' => [
                'requests'  => 5,
                'timeframe' => 1000,
            ],
        ];

        $result = $this->class->fetch($config);

        $this->assertEquals([ $json ], $result);
    }

    /**
     * Test that fetch() throws an exception if rate limiting is configured, but no rate limiter is set.
     */
    public function testFetchWithRateLimitFailsWhenNoRateLimiterSet(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Source has rate limiting configured, but no rate limiter is available!');

        $class = new RequestsSource($this->http, $this->logger, NULL);

        $this->http->shouldReceive('request')
                   ->never();

        $config = [
            'url'        => 'http://localhost/url',
            'params'     => [ 'param1' => 'value1' ],
            'rate-limit' => [
                'requests'  => 5,
                'timeframe' => 1000,
            ],
        ];

        $class->fetch($config);
    }

}

?>
