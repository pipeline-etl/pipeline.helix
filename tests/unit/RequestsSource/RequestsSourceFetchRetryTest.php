<?php

/**
 * This file contains the RequestsSourceFetchRetryTest class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use DateTime;
use DateTimeZone;
use phpmock\mockery\PHPMockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\Common\Exceptions\SourceException;
use Pipeline\RequestsSource;
use WpOrg\Requests\Exception as RequestsException;
use WpOrg\Requests\Exception\Http\Status400 as RequestsExceptionHTTP400;

/**
 * This class contains error handling and retry tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchRetryTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch() throws an error if the request had an error.
     */
    public function testFetchThrowsErrorIfRequestHadError(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Pipeline source request to http://localhost/url failed: 400 Bad Request');

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once()
                       ->andThrow(new RequestsExceptionHTTP400(NULL, $this->response));

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->status_code = 400; // phpcs:ignore Lunr.NamingConventions.CamelCapsVariableName
        $this->response->url         = 'http://localhost/url';

        $config = [ 'url' => 'http://localhost/url' ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws an error if the request failed.
     */
    public function testFetchThrowsErrorIfRequestFailed(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Pipeline source request to http://localhost/url failed: cURL error 0001: Network error');

        $format = 'Y-m-d H:i:s';
        $tz     = 'Asia/Dubai';

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => [
                    'value'    => '00:00:00 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
                'ToDate'   => [
                    'value'    => '23:59:59 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
            ],
        ];

        $fromDate = new DateTime('00:00:00 +1 day', new DateTimeZone($tz));
        $toDate   = new DateTime('23:59:59 +1 day', new DateTimeZone($tz));

        $expectedParams = [
            'FromDate' => $fromDate->format($format),
            'ToDate'   => $toDate->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->once()
                   ->with($config['url'], [], $expectedParams, 'GET', [])
                   ->andThrow(new RequestsException('cURL error 0001: Network error', 'curlerror', NULL));

        $this->response->shouldReceive('throw_for_status')
                       ->never();

        $this->logger->shouldReceive('info')
                     ->once();

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() returns an empty result if there was a request error.
     */
    public function testFetchReturnsEmptyResultOnRequestError(): void
    {
        $this->expectException(SourceException::class);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once()
                       ->andThrow(new RequestsExceptionHTTP400(NULL, $this->response));

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $this->response->status_code = 400; // phpcs:ignore Lunr.NamingConventions.CamelCapsVariableName
        $this->response->url         = 'http://localhost/url';

        $config = [ 'url' => 'http://localhost/url' ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() returns an empty result if there was a failed request.
     */
    public function testFetchReturnsEmptyResultOnRequestFailure(): void
    {
        $this->expectException(SourceException::class);

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], [], 'GET', [])
                   ->andThrow(new RequestsException('cURL error 0001: Network error', 'curlerror', NULL));

        $this->response->shouldReceive('throw_for_status')
                       ->never();

        $this->logger->shouldReceive('info')
                     ->once()
                     ->with('Fetch data from http://localhost/url');

        $config = [ 'url' => 'http://localhost/url' ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws an error if the request failed after three retries if retry-on is set.
     */
    public function testFetchThrowsErrorIfRequestFailedAfterThreeRetries(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Pipeline source request to http://localhost/url failed: cURL error 0001: Network error');

        PHPMockery::mock('Pipeline', 'curl_errno')
                  ->times(3)
                  ->andReturn(1);

        PHPMockery::mock('Pipeline', 'sleep')
                  ->times(2)
                  ->andReturn(0);

        $curl = curl_init();

        $format = 'Y-m-d H:i:s';
        $tz     = 'Asia/Dubai';

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => [
                    'value'    => '00:00:00 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
                'ToDate'   => [
                    'value'    => '23:59:59 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
            ],
            'retry-on' => [
                'curl' => [ 1 ],
            ],
        ];

        $fromDate = new DateTime('00:00:00 +1 day', new DateTimeZone($tz));
        $toDate   = new DateTime('23:59:59 +1 day', new DateTimeZone($tz));

        $expectedParams = [
            'FromDate' => $fromDate->format($format),
            'ToDate'   => $toDate->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->times(3)
                   ->with($config['url'], [], $expectedParams, 'GET', [])
                   ->andThrow(new RequestsException('cURL error 0001: Network error', 'curlerror', $curl));

        $this->response->shouldReceive('throw_for_status')
                       ->never();

        $context = [
            'url'     => 'http://localhost/url',
            'message' => 'cURL error 0001: Network error'
        ];

        $this->logger->shouldReceive('info')
                     ->once();

        $this->logger->shouldReceive('warning')
                     ->times(2)
                     ->with('Pipeline source request to {url} failed: {message}. Retrying ...', $context);

        $this->class->fetch($config);

        curl_close($curl);
    }

    /**
     * Test that fetch() throws an Http error if the request failed after three retries if retry-on is set.
     */
    public function testFetchThrowsHttpErrorIfRequestFailedAfterThreeRetries(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Pipeline source request to http://localhost/url failed: 400 Bad Request');

        PHPMockery::mock('Pipeline', 'sleep')
                  ->times(2)
                  ->andReturn(0);

        $format = 'Y-m-d H:i:s';
        $tz     = 'Asia/Dubai';

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => [
                    'value'    => '00:00:00 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
                'ToDate'   => [
                    'value'    => '23:59:59 +1 day',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
            ],
            'retry-on' => [
                'http' => [ 400 ],
            ],
        ];

        $fromDate = new DateTime('00:00:00 +1 day', new DateTimeZone($tz));
        $toDate   = new DateTime('23:59:59 +1 day', new DateTimeZone($tz));

        $expectedParams = [
            'FromDate' => $fromDate->format($format),
            'ToDate'   => $toDate->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->times(3)
                   ->with($config['url'], [], $expectedParams, 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->times(3)
                       ->andThrow(new RequestsExceptionHTTP400(NULL, $this->response));

        $this->response->status_code = 400; // phpcs:ignore Lunr.NamingConventions.CamelCapsVariableName
        $this->response->url         = 'http://localhost/url';

        $context = [
            'url'     => 'http://localhost/url',
            'message' => '400 Bad Request'
        ];

        $this->logger->shouldReceive('info')
                     ->once();

        $this->logger->shouldReceive('warning')
                     ->times(2)
                     ->with('Pipeline source request to {url} failed: {message}. Retrying ...', $context);

        $this->class->fetch($config);
    }

}

?>
