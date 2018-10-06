<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 04/08/2018
 * Time: 16:45
 */

namespace GaeSlim;
use GaeUtil\Util;

class Extend {
    public static function errorReporting(\Slim\App $app){
        /**
         * Autohandling error reporting.
         */
        if (Util::isDevServer()) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
    }
    public static function jsonErrors(\Slim\App $app){

    }
}