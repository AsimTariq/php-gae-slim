<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 23/03/2018
 * Time: 01:47
 */

namespace GaeSlim;

use GaeUtil\Auth;
use GaeUtil\Conf;
use GaeUtil\State;
use GaeUtil\Util;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class App extends \Slim\App {

    public function __construct($container = null) {

        /**
         * Allows for overriding.
         */
        if (is_null($container)) {
            $container = new Container([
                'settings' => [],
            ]);
        }
        $settings = $container->get("settings");
        $settings->replace([
            'displayErrorDetails' => Util::isDevServer(),
            'debug' => Util::isDevServer(),
            'routerCacheFile'=> false,
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
         * Setting default Response header to text/plain
         */
        $this->add(function (Request $request, Response $response, $next) {
            $newResponse = $response->withHeader('Content-type', 'text/plain;charset=utf-8');
            return $next($request, $newResponse);
        });

        parent::__construct($container);
    }

    public function addSetting($key, $val) {
        $settings = $this->getContainer()->get("settings");
        $settings->replace([
            $key => $val
        ]);
    }

    public function addInternalJwtAuth($path) {
        $this->add(Middleware::JwtAuthInternal($path));
    }

    public function addScopedJwtAuth($path) {
        $this->add(Middleware::JwtAuthScoped($path));
    }

    public function addStatRoute($route,$links=[]){
        $this->get($route, function (Request $request, Response $response) {
            $data = State::status($links);
            return $response->withJson($data);
        });
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
                            "trace" => debug_backtrace()
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

    public function createAuthEndpoint($route, $redirect_after = "/") {
        Conf::getInstance()->set("auth_callback_url", $route);
        Conf::getInstance()->set("frontend_url", $redirect_after);
        $this->get($route, function (Request $request, Response $response) {
            $auth_code = $request->getQueryParam("code", false);
            if ($auth_code) {
                $user_data = Auth::fetchAndSaveTokenByCode($auth_code);
                if ($user_data) {
                    $frontend_url = Conf::get("frontend_url");
                    return $response->withRedirect($frontend_url);
                } else {
                    throw new \Exception("Error saving token");
                }
            } elseif (isset($get_request["error"])) {
                throw new \Exception($get_request["error"]);
            } else {
                $email = Auth::getCurrentUserEmail();
                $url = Auth::getAuthRedirectUrl($email);
                return $response->withRedirect($url);
            }
        });
    }
}

