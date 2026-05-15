<?php

/**
 * This file contains the RequestsSourceTestCase class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline\Tests\RequestsSource;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\RequestsSource;
use Psr\Log\LoggerInterface;
use Stiphle\Throttle\ThrottleInterface;
use WpOrg\Requests\Response;
use WpOrg\Requests\Session;

/**
 * This class contains common setup routines, providers
 * and shared attributes for testing the RequestsSource class.
 */
#[CoversClass(RequestsSource::class)]
abstract class RequestsSourceTestCase extends MockeryTestCase
{

    /**
     * Mock instance of a Logger class.
     * @var LoggerInterface&MockInterface
     */
    protected LoggerInterface&MockInterface $logger;

    /**
     * Mock instance of the Requests\Session class.
     * @var Session&MockInterface
     */
    protected Session&MockInterface $http;

    /**
     * Mock instance of the Requests\Response class.
     * @var Response&MockInterface
     */
    protected Response&MockInterface $response;

    /**
     * Mock instance of the rate limiter class.
     * @var ThrottleInterface&MockInterface
     */
    protected ThrottleInterface&MockInterface $limiter;

    /**
     * Instance of the tested class.
     * @var RequestsSource
     */
    protected RequestsSource $class;

    /**
     * TestCase Constructor.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->logger   = Mockery::mock(LoggerInterface::class);
        $this->http     = Mockery::mock(Session::class);
        $this->response = Mockery::mock(Response::class);
        $this->limiter  = Mockery::mock(ThrottleInterface::class);
        $this->class    = new RequestsSource($this->http, $this->logger, $this->limiter);
    }

}

?>
