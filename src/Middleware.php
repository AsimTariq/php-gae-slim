<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 23/03/2018
 * Time: 01:47
 */

namespace GaeSlim;

use GaeUtil\JWT;
use GaeUtil\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\JwtAuthentication;

class Middleware {

    /**
     * @param $path
     * @return JwtAuthentication
     */
    static function JwtAuthInternal($path) {
        return new JwtAuthentication([
            "path" => $path,
            "logger"=> Logger::create(__FUNCTION__),
            "secret" => JWT::getInternalSecret(),
            "error" => function (Request $request, Response $response, $arguments) {
                $payload = [
                    "error" => [
                        "code" => 401,
                        "message" => $arguments["message"],
                        "status" => "UNAUTHENTICATED",
                        "details" => [],
                    ]
                ];
                return $response->withJson($payload, 401);
            }
        ]);
    }

    /**
     *
     * @param $path
     * @return JwtAuthentication
     */
    static function JwtAuthScoped($path) {
        return new JwtAuthentication([
            "path" => $path,
            "secret" => JWT::getScopedSecret(),
            "logger"=> Logger::create(__FUNCTION__),
            "error" => function (Request $request, Response $response, $arguments) {
                $payload = [
                    "error" => [
                        "code" => 401,
                        "message" => $arguments["message"],
                        "status" => "UNAUTHENTICATED",
                        "details" => [],
                    ]
                ];
                return $response->withJson($payload, 401);
            }
        ]);
    }
}