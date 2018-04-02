<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 23/03/2018
 * Time: 01:47
 */

namespace GaeSlim;

use GaeUtil\Util;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class App extends \Slim\App {

    public function __construct($container = null) {
        $container = new Container([
            'settings' => [
                'displayErrorDetails' => Util::isDevServer(),
            ],
        ]);
        /**
         * Autohandling error reporting.
         */
        if (Util::isDevServer()) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
        /**
         * Setting default Respons header to text/plain
         */
        $this->add(function (Request $request, Response $response, $next) {
            $newResponse = $response->withHeader('Content-type', 'text/plain;charset=utf-8');
            return $next($request, $newResponse);
        });

        parent::__construct($container);
    }

    public function removeTrailingSlashes() {
        $this->get('/{path:.*}/', function (Request $request, Response $response) {
            $path = $request->getUri()->getPath();
            $new_path = rtrim($path, "/");
            $new_uri = $request->getUri()->withPath($new_path);
            if ($new_uri->getPort() == 80) {
                $new_uri = $new_uri->withPort(null);
            }
            return $response->withRedirect($new_uri);
        });
    }

    public function simpleRedirect($from, $to) {
        $this->get($from, function (Request $request, Response $response) use ($to) {
            return $response->withRedirect($to);
        });
    }

    public function jsonErrors() {
        // add error handler
        $container = $this->getContainer();
        $container['notFoundHandler'] = function ($container) {
            /** @noinspection PhpUnusedParameterInspection */
            /** @noinspection PhpDocSignatureInspection */
            return function (Request $request, Response $response) {
                $payload = [
                    "error" => [
                        "code" => 404,
                        "message" => 'Not Found',
                        "status" => "NOT_FOUND",
                        "details" => [],
                    ]
                ];
                return $response
                    ->withStatus(404)
                    ->withJson($payload);
            };
        };
        $container['notAllowedHandler'] = function ($container) {
            /** @noinspection PhpUnusedParameterInspection */
            /** @noinspection PhpDocSignatureInspection */
            return function (Request $request, Response $response, array $methods) {
                $payload = [
                    "error" => [
                        "code" => "405",
                        "message" => 'Method must be one of: ' . implode(', ', $methods),
                        "status" => "METHOD_NOT_ALLOWED",
                        "details" => [],
                    ]
                ];
                return $response
                    ->withStatus(405)
                    ->withHeader('Allow', implode(', ', $methods))
                    ->withJson($payload);
            };
        };
        $container['errorHandler'] = function ($container) {
            /** @noinspection PhpUnusedParameterInspection */
            /** @noinspection PhpDocSignatureInspection */
            return function (Request $request, Response $response, \Exception $exception) use ($container) {

                $payload = [
                    "error" => [
                        "code" => "500",
                        "message" => $exception->getMessage(),
                        "status" => "UNKNOWN",
                        "details" => [],
                    ]
                ];
                // if debugging enabled add trace to response
                if ($container['settings']['displayErrorDetails'] === true) {
                    $payload['trace'] = $exception->getTrace();
                }
                return $response
                    ->withStatus(500)
                    ->withJson($payload);
            };
        };
        //define an error handler

        $container['phpErrorHandler'] = function ($container) {
            /** @noinspection PhpUnusedParameterInspection */
            /** @noinspection PhpDocSignatureInspection */
            return function (Request $request, Response $response, \Exception $exception) use ($container) {
                $payload = [
                    "error" => [
                        "code" => "500",
                        "message" => $exception->getMessage(),
                        "status" => "UNKNOWN",
                        "details" => [],
                    ]
                ];
                // if debugging enabled add trace to response
                if ($container['settings']['displayErrorDetails'] === true) {
                    $payload['trace'] = $exception->getTrace();
                }
                return $response
                    ->withStatus(500)
                    ->withJson($payload);
            };
        };

        register_shutdown_function(function () use ($container) {
            $errors = array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR);
            $error = error_get_last();
            if ($error != null && in_array($error['type'], $errors, true)) {
                @ob_end_clean();
                $message = "The system was halted because an error occurred. code: {$error['type']}, message: {$error['message']}";
                $payload = [
                    "error" => [
                        "code" => "500",
                        "message" => $message,
                        "status" => "INTERNAL",
                        "details" => [
                            "file" => $error['file'],
                            "line" => $error['line'],
                        ],
                    ]
                ];
                if ($container['settings']['displayErrorDetails'] === true) {
                    $payload["error"]["details"]['file'] = $error['file'];
                    $payload["error"]["details"]['line'] = $error['line'];
                }
                http_response_code(500);
                //header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                header("Content-Type: application/json; charset=utf-8'", true);
                exit(json_encode($payload));
            }
        });

    }

}

