<?php

namespace Cryonighter\GuzzleHttpLogger;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Cryonighter\HttpLogger\HttpLoggerInterface;

class GuzzleClientFactory
{
    /**
     * @param HttpLoggerInterface $httpLogger
     * @param HandlerStack        $stack
     * @param array               $config
     *
     * @return Client
     */
    public static function createClient(HttpLoggerInterface $httpLogger, HandlerStack $stack, array $config = []): Client
    {
        $stack->push(
            function (callable $handler) use ($httpLogger): callable {
                return function (Request $request, array $options) use ($handler, $httpLogger) {
                    $httpLogger->logRequest(
                        $request->getProtocolVersion(),
                        $request->getMethod(),
                        $request->getUri(),
                        $request->getHeaders(),
                        (string) $request->getBody()
                    );

                    return $handler($request, $options)->then(
                        function (Response $response) use ($httpLogger): Response {
                            $httpLogger->logResponse(
                                $response->getProtocolVersion(),
                                $response->getStatusCode(),
                                $response->getReasonPhrase(),
                                $response->getHeaders(),
                                (string) $response->getBody()
                            );

                            return $response;
                        },
                        function (TransferException $reason) use ($httpLogger): PromiseInterface {
                            if ($reason instanceof RequestException) {
                                if ($reason->hasResponse()) {
                                    $response = $reason->getResponse();

                                    $httpLogger->logResponse(
                                        $response->getProtocolVersion(),
                                        $response->getStatusCode(),
                                        $response->getReasonPhrase(),
                                        $response->getHeaders(),
                                        (string) $response->getBody()
                                    );
                                } else {
                                    $httpLogger->logError($reason->getMessage());
                                }
                            } else {
                                $httpLogger->logError($reason->getMessage());
                            }

                            return Create::rejectionFor($reason);
                        }
                    );
                };
            }
        );

        return new Client(array_merge($config, ['handler' => $stack]));
    }
}
