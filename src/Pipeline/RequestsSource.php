<?php

/**
 * This file contains the RequestsSource class.
 *
 * SPDX-FileCopyrightText: Copyright 2026 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

namespace Pipeline;

use CurlHandle;
use DateTime;
use DateTimeZone;
use Pipeline\Common\Exceptions\InvalidConfigurationException;
use Pipeline\Common\Exceptions\SourceException;
use Pipeline\Common\Node;
use Pipeline\Common\SourceInterface;
use Psr\Log\LoggerInterface;
use Stiphle\Throttle\ThrottleInterface;
use WpOrg\Requests\Exception as RequestsException;
use WpOrg\Requests\Exception\Http as RequestsExceptionHTTP;
use WpOrg\Requests\Response;
use WpOrg\Requests\Session;

/**
 * Http/Https Pipeline Source.
 *
 * @phpstan-import-type Item from Node
 * @phpstan-import-type FetchedData from SourceInterface
 * @phpstan-import-type SourceConfig from SourceInterface
 * @phpstan-type PaginationConfig array{
 *     header: string,
 * }
 * @phpstan-type DateParamConfig array{
 *     value: string,
 *     timezone: string,
 *     format: string,
 * }
 * @phpstan-type DateRangeParamConfig array{
 *     from: string,
 *     to: string,
 *     range-operator?: string,
 *     timezone: string,
 *     format: string,
 * }
 */
class RequestsSource extends Node implements SourceInterface
{

    /**
     * Retry amount.
     * @var int
     */
    public const RETRY_AMOUNT = 3;

    /**
     * Shared instance of the Requests\Session class.
     * @var Session
     */
    protected readonly Session $http;

    /**
     * Shared instance of a Logger class.
     * @var LoggerInterface
     */
    protected readonly LoggerInterface $logger;

    /**
     * Shared instance of a rate limiter class.
     * @var ThrottleInterface|null
     */
    protected readonly ?ThrottleInterface $limiter;

    /**
     * Constructor.
     *
     * @param Session                $http    Shared instance of the Curl class.
     * @param LoggerInterface        $logger  Shared instance of a Logger class.
     * @param ThrottleInterface|null $limiter Shared instance of a rate limiter class
     */
    public function __construct(Session $http, LoggerInterface $logger, ?ThrottleInterface $limiter)
    {
        $this->http    = $http;
        $this->logger  = $logger;
        $this->limiter = $limiter;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        // no-op
    }

    /**
     * Retrieve source data to process in the pipeline.
     *
     * @param SourceConfig $config Configuration parameters necessary to retrieve the data
     *
     * @return FetchedData Array of results fetched from the source
     */
    public function fetch(array $config): array
    {
        if (!isset($config['url']) || !is_string($config['url']))
        {
            throw new InvalidConfigurationException('RequestsSource: missing or invalid url');
        }

        $url           = $config['url'];
        $headers       = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : [];
        $method        = isset($config['method']) && is_string($config['method']) ? strtoupper($config['method']) : 'GET';
        $options       = isset($config['options']) && is_array($config['options']) ? $config['options'] : [];
        $rawDateParams = isset($config['datetime_params']) && is_array($config['datetime_params']) ? $config['datetime_params'] : [];

        foreach ($rawDateParams as $entry)
        {
            if (!is_array($entry) || !isset($entry['timezone']) || !is_string($entry['timezone'])
                || !isset($entry['format']) || !is_string($entry['format'])
            )
            {
                throw new InvalidConfigurationException('RequestsSource: invalid datetime_params entry, missing or invalid timezone/format');
            }

            $hasRange = isset($entry['from']) && is_string($entry['from'])
                && isset($entry['to']) && is_string($entry['to']);

            $hasValue = isset($entry['value']) && is_string($entry['value']);

            if (!$hasRange && !$hasValue)
            {
                $message = 'RequestsSource: invalid datetime_params entry, must have either value or from/to/range-operator';
                throw new InvalidConfigurationException($message);
            }
        }

        /** @var array<string, DateParamConfig|DateRangeParamConfig> $rawDateParams */
        $dateParams = $rawDateParams;

        $retryOn       = isset($config['retry-on']) && is_array($config['retry-on']) ? $config['retry-on'] : [];
        $retryWaitTime = isset($config['retry-waittime']) && is_int($config['retry-waittime']) ? $config['retry-waittime'] : 1;

        $rawParams = $config['params'] ?? [];

        if (is_array($rawParams))
        {
            $params = array_merge($rawParams, $this->prepareDatetimeParameters($dateParams));
        }
        else
        {
            $params = $this->prepareDatetimeParameters($dateParams);
        }

        $result = [];

        $parsedHost  = parse_url($url, PHP_URL_HOST);
        $domain      = is_string($parsedHost) ? $parsedHost : '';
        $nextRequest = FALSE;

        do
        {
            if (isset($config['rate-limit']) && is_array($config['rate-limit']))
            {
                if ($this->limiter === NULL)
                {
                    throw new SourceException('Source has rate limiting configured, but no rate limiter is available!');
                }

                $rateLimit = $config['rate-limit'];

                $requests  = isset($rateLimit['requests']) && is_int($rateLimit['requests']) ? $rateLimit['requests'] : 0;
                $timeframe = isset($rateLimit['timeframe']) && is_int($rateLimit['timeframe']) ? $rateLimit['timeframe'] : 0;

                $this->limiter->throttle($domain, $requests, $timeframe);
            }

            $logMessageUrl = $url;

            if ($method === 'GET' && !empty($params))
            {
                $logMessageUrl .= '?' . http_build_query($params);
            }

            $this->logger->info('Fetch data from ' . $logMessageUrl);

            $response = $this->fetchData($url, $headers, $params, $method, $options, $retryOn, $retryWaitTime);

            $result[] = $response->body;

            if (isset($config['pagination']) && is_array($config['pagination']))
            {
                $rawPagination = $config['pagination'];

                if (!isset($rawPagination['header']) || !is_string($rawPagination['header']))
                {
                    throw new InvalidConfigurationException('RequestsSource: invalid pagination config, missing or invalid header');
                }

                /** @var PaginationConfig $rawPagination */
                $nextRequest = $this->pagination($response, $rawPagination, $url, $params);
            }

            unset($response);
        }
        while ($nextRequest === TRUE);

        return $result;
    }

    /**
     * Prepare 'datetime_params' entries into actual parameters.
     *
     * @param array<string, DateParamConfig|DateRangeParamConfig> $dateParams An array of datetime parameters
     *
     * @return array<string, string> An associative array of prepared parameters
     */
    protected function prepareDatetimeParameters(array $dateParams): array
    {
        $return = [];
        foreach ($dateParams as $key => $value)
        {
            if (array_key_exists('from', $value))
            {
                $from         = new DateTime($value['from'], new DateTimeZone($value['timezone']));
                $to           = new DateTime($value['to'], new DateTimeZone($value['timezone']));
                $return[$key] = $from->format($value['format']) . ($value['range-operator'] ?? '..') . $to->format($value['format']);
            }
            else
            {
                $datetime     = new DateTime($value['value'], new DateTimeZone($value['timezone']));
                $return[$key] = $datetime->format($value['format']);
            }
        }

        return $return;
    }

    /**
     * Return TRUE if a new request should be made to get more items from the paginated data
     * It modifies url and params to prepare them for the next request if needed
     *
     * @param Response             $response         Response object for the performed request
     * @param PaginationConfig     $paginationConfig Configuration of the pagination
     * @param string               $url              Request url
     * @param array<string, mixed> $params           Request parameters
     *
     * @return bool
     */
    protected function pagination(Response $response, array $paginationConfig, string &$url, array &$params): bool
    {
        $headerValue = $response->headers[$paginationConfig['header']];

        if (!is_string($headerValue))
        {
            return FALSE;
        }

        $info = [];

        foreach (explode(', ', $headerValue) as $link)
        {
            $semicolonPos = strpos($link, ';');
            if ($semicolonPos === FALSE)
            {
                continue;
            }

            $key        = substr($link, $semicolonPos + 2);
            $key        = trim(str_replace('rel=', '', $key), '"');
            $info[$key] = trim(substr($link, 0, $semicolonPos), '<>');
        }

        if (array_key_exists('next', $info) && array_key_exists('last', $info) && $url !== $info['last'])
        {
            $url = $info['next'];

            // The next link is given with parameters already included, so don't use them separately again
            $params = [];

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Retrieve source data to process in the pipeline.
     *
     * @param string               $url           URL for the request
     * @param array<string, mixed> $headers       HTTP headers to be included in the request
     * @param array<string, mixed> $params        URL query parameters to be included in the request
     * @param string               $method        HTTP method the request should use
     * @param array<string, mixed> $options       Configuration options for the request
     * @param array<string, mixed> $retryOn       List of curl error codes and http statuses to retry the requests
     * @param int                  $retryWaitTime Seconds to wait for the next retry
     *
     * @throws SourceException HTTP request failed.
     *
     * @return Response Response object for the performed request
     */
    protected function fetchData(
        string $url,
        array $headers,
        array $params,
        string $method,
        array $options,
        array $retryOn,
        int $retryWaitTime
    ): Response
    {
        $lastUrl     = $url;
        $lastMessage = '';

        for ($retryCount = 1; $retryCount <= self::RETRY_AMOUNT; $retryCount++)
        {
            try
            {
                $response = $this->http->request($url, $headers, $params, $method, $options);

                $response->throw_for_status();

                return $response;
            }
            catch (RequestsExceptionHTTP $e)
            {
                /**
                 * Requests always returns a Response object with the RequestsExceptionHTTP
                 * @var Response $response
                 */
                $response    = $e->getData();
                $httpRetryOn = isset($retryOn['http']) && is_array($retryOn['http']) ? $retryOn['http'] : [];
                $lastMessage = $e->getMessage();

                $rawStatusCode = $response->status_code; // phpcs:ignore Lunr.NamingConventions.CamelCapsVariableName
                $statusCode    = is_int($rawStatusCode) ? $rawStatusCode : NULL;
                $lastUrl       = $response->url;

                if (!$this->shouldRetry($retryCount, $lastUrl, $lastMessage, $statusCode, $httpRetryOn))
                {
                    break;
                }
            }
            catch (RequestsException $e)
            {
                $errorNumber = NULL;
                $errData     = $e->getData();
                $lastMessage = $e->getMessage();

                if ($errData instanceof CurlHandle)
                {
                    $errorNumber = curl_errno($errData);
                }

                $curlRetryOn = isset($retryOn['curl']) && is_array($retryOn['curl']) ? $retryOn['curl'] : [];

                if (!$this->shouldRetry($retryCount, $url, $lastMessage, $errorNumber, $curlRetryOn))
                {
                    break;
                }
            }

            sleep($retryWaitTime);
        }

        throw new SourceException("Pipeline source request to {$lastUrl} failed: {$lastMessage}");
    }

    /**
     * Check whether a failed request should be retried. Logs a warning if retrying.
     *
     * @param int                     $retryCount   The retry count
     * @param string                  $url          The url of the request
     * @param string                  $errorMessage The error message of the error
     * @param int|null                $errorNumber  The error number of the error or NULL if no error number
     * @param array<array-key, mixed> $retryOn      List of error codes to retry the requests
     *
     * @return bool TRUE if the request should be retried, FALSE otherwise
     */
    private function shouldRetry(int $retryCount, string $url, string $errorMessage, ?int $errorNumber, array $retryOn): bool
    {
        if ($retryCount >= self::RETRY_AMOUNT || is_null($errorNumber) || !in_array($errorNumber, $retryOn))
        {
            return FALSE;
        }

        $context = [
            'url'     => $url,
            'message' => $errorMessage
        ];

        $this->logger->warning('Pipeline source request to {url} failed: {message}. Retrying ...', $context);

        return TRUE;
    }

}

?>
