<?php

/**
 * This file contains the RequestsSourceFetchDatetimeTest class.
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
 * This class contains datetime parameter tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchDatetimeTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch constructs FromDate and ToDate correctly when using today.
     */
    public function testRequestsSourceConstructsTheDatesCorrectly(): void
    {
        $format = 'Y-m-d H:i:s';
        $tz     = 'Asia/Dubai';

        $config = [
            'url'             => 'http://localhost/url',
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

        $fromDate = new DateTime('00:00:00', new DateTimeZone($tz));
        $toDate   = new DateTime('23:59:59', new DateTimeZone($tz));

        $this->assertNotEquals($fromDate->format($format), $toDate->format($format));

        $expectedParams = [
            'FromDate' => $fromDate->format($format),
            'ToDate'   => $toDate->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], $expectedParams, 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->response->body = '{}';

        $this->logger->shouldReceive('info')
                     ->once();

        $this->class->fetch($config);
    }

    /**
     * Test that fetch constructs date range parameters correctly.
     */
    public function testRequestsSourceConstructsDateRangeCorrectly(): void
    {
        $format = 'Y-m-d H:i:s';
        $tz     = 'Europe/Amsterdam';

        $from = new DateTime('00:00:00', new DateTimeZone($tz));
        $to   = new DateTime('23:59:59', new DateTimeZone($tz));

        $expectedParams = [
            'DateRange' => $from->format($format) . '..' . $to->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], $expectedParams, 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->response->body = '{}';

        $this->logger->shouldReceive('info')
                     ->once();

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'DateRange' => [
                    'from'     => '00:00:00',
                    'to'       => '23:59:59',
                    'timezone' => $tz,
                    'format'   => $format,
                ],
            ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch constructs date range parameters with a custom range operator.
     */
    public function testRequestsSourceConstructsDateRangeWithCustomOperator(): void
    {
        $format = 'Y-m-d H:i:s';
        $tz     = 'Europe/Amsterdam';

        $from = new DateTime('00:00:00', new DateTimeZone($tz));
        $to   = new DateTime('23:59:59', new DateTimeZone($tz));

        $expectedParams = [
            'DateRange' => $from->format($format) . '/' . $to->format($format),
        ];

        $this->http->shouldReceive('request')
                   ->once()
                   ->with('http://localhost/url', [], $expectedParams, 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->response->body = '{}';

        $this->logger->shouldReceive('info')
                     ->once();

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'DateRange' => [
                    'from'           => '00:00:00',
                    'to'             => '23:59:59',
                    'range-operator' => '/',
                    'timezone'       => $tz,
                    'format'         => $format,
                ],
            ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch constructs FromDate and ToDate correctly when using +1 day.
     */
    public function testRequestsSourceHandlesRelativeDates(): void
    {
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
                   ->with('http://localhost/url', [], $expectedParams, 'GET', [])
                   ->andReturn($this->response);

        $this->response->shouldReceive('throw_for_status')
                       ->once();

        $this->response->body = '{}';

        $this->logger->shouldReceive('info')
                     ->once();

        $this->class->fetch($config);
    }

}

?>
