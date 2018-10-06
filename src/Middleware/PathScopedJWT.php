<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 04/04/2018
 * Time: 19:58
 */

namespace GaeSlim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class PathScopedJWT {

    public function __construct() {

    }

    /**
     * Call the middleware
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param callable $next
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, callable $next) {
        $token = $request->getAttribute("token");
        $path = $request->getUri()->getPath();
        if (!isset($token->scope) || $path <> $token->scope) {

            if(is_null($token)){
                $message = "Permission denied, missing token or illegal token.";
            } else {
                $message = "Permission denied to $path, probably using wrong token.";
            }
            $payload = [
                "error" => [
                    "code" => 403,
                    "message" => $message,
                    "status" => "PERMISSION_DENIED",
                    "details" => [],
                ]
            ];
            syslog(LOG_INFO, "path.:" . $path);
            syslog(LOG_INFO, "scope:" . @$token->scope);
            return $response->withStatus(403)->withJson($payload);
        }
        return $next($request, $response);
    }
}