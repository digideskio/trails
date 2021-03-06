<?php

# Copyright (c)  2007 - Marcus Lunzenauer <mlunzena@uos.de>
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.


error_reporting(E_ALL);

# define root
$trails_root = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app';
$trails_uri = sprintf('http%s://%s%s%s',
                      isset($_SERVER['HTTPS'])
                        ? 's' : '',
                      $_SERVER['SERVER_NAME'],
                      $_SERVER['SERVER_PORT'] == 80
                        ? '' : ':' . $_SERVER['SERVER_PORT'],
                      $_SERVER['SCRIPT_NAME']);

# load trails
# require_once $trails_root . '/../vendor/trails/trails-unabridged.php';
require_once $trails_root . '/../vendor/trails/src/dispatcher.php';
require_once $trails_root . '/../vendor/trails/src/response.php';
require_once $trails_root . '/../vendor/trails/src/controller.php';
require_once $trails_root . '/../vendor/trails/src/inflector.php';
require_once $trails_root . '/../vendor/trails/src/flash.php';
require_once $trails_root . '/../vendor/trails/src/exception.php';


# load flexi
require_once $trails_root . '/../vendor/flexi/lib/flexi.php';

# dispatch
$request_uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$default_controller = 'example';

$dispatcher = new Trails_Dispatcher($trails_root, $trails_uri, $default_controller);
$dispatcher->dispatch($request_uri);
