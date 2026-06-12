<?php

/**
 * This file contains the RequestsSourceFetchValidationTest class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\Common\Exceptions\InvalidConfigurationException;
use Pipeline\RequestsSource;

/**
 * This class contains config validation tests for the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
class RequestsSourceFetchValidationTest extends RequestsSourceTestCase
{

    /**
     * Test that fetch() throws InvalidConfigurationException for a missing url.
     */
    public function testFetchThrowsInvalidConfigurationForMissingUrl(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('RequestsSource: missing or invalid url');

        $config = [];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws InvalidConfigurationException for a missing timezone.
     */
    public function testFetchThrowsInvalidConfigurationForMissingTimezone(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('RequestsSource: invalid datetime_params entry, missing or invalid timezone/format');

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => [
                    'value'  => '00:00:00',
                    'format' => 'Y-m-d H:i:s',
                ],
            ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws InvalidConfigurationException for a non-array entry.
     */
    public function testFetchThrowsInvalidConfigurationForNonArrayEntry(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('RequestsSource: invalid datetime_params entry, missing or invalid timezone/format');

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => 'not-an-array',
            ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws InvalidConfigurationException for a missing value and range.
     */
    public function testFetchThrowsInvalidConfigurationForMissingValueAndRange(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('RequestsSource: invalid datetime_params entry, must have either value or from/to/range-operator');

        $config = [
            'url'             => 'http://localhost/url',
            'datetime_params' => [
                'FromDate' => [
                    'timezone' => 'Asia/Dubai',
                    'format'   => 'Y-m-d H:i:s',
                ],
            ],
        ];

        $this->class->fetch($config);
    }

    /**
     * Test that fetch() throws InvalidConfigurationException for a missing pagination header.
     */
    public function testFetchThrowsInvalidConfigurationForMissingPaginationHeader(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('RequestsSource: invalid pagination config, missing or invalid header');

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

        $config = [
            'url'        => 'http://localhost/url',
            'pagination' => [],
        ];

        $this->class->fetch($config);
    }

}

?>
