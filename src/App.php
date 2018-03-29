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

}

