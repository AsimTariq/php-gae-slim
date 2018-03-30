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
}

