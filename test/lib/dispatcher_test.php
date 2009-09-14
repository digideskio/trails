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


/**
 * Testcase for Dispatcher.
 *
 * @package    trails
 * @subpackage test
 *
 * @author    mlunzena
 * @copyright (c) Authors
 * @version   $Id: dispatcher_test.php 6380 2007-10-24 16:17:19Z mlunzena $
 */

class InflectorTestCase extends UnitTestCase {

  function setUp() {
  }

  function tearDown() {
  }

  function inflector_strings() {
    $strings = array(
      'hello'               => 'Hello',
      'hello_world'         => 'HelloWorld',
      'h_e_l_l_o'           => 'HELLO',
      'hello123world'       => 'Hello123world',
      'hello/world'         => 'Hello_World',
      'hello/another_world' => 'Hello_AnotherWorld',
      );
    return $strings;
  }

  function test_camelize() {
    foreach ($this->inflector_strings() as $lower_case => $camelized)
      $this->assertEqual($camelized, Trails_Inflector::camelize($lower_case));
  }

  function test_underscore() {
    foreach ($this->inflector_strings() as $lower_case => $camelized)
      $this->assertEqual($lower_case, Trails_Inflector::underscore($camelized));
  }
}


class RoutingTestCase extends UnitTestCase {

  var $dispatcher;

  function setUp() {
    $this->dispatcher =& new Trails_Dispatcher(TRAILS_ROOT, '', 'default', 'index');
  }

  function tearDown() {
    $this->dispatcher = NULL;
  }

  function test_parse() {

    $paths = array(
      ''                    => NULL,
      'abc'                 => NULL,
      'abc/def'             => NULL,
      'abc/def/1/2'         => NULL,
      'bar'                 => array('bar', ''),
      'bar/show'            => array('bar', 'show'),
      'bar/show/1/2'        => array('bar', 'show/1/2'),
      'bar/show///1///2'     => array('bar', 'show///1///2'),
      'foo'                 => NULL,
      'foo/foobar'          => array('foo/foobar', ''),
      'foo/foobar/list'     => array('foo/foobar', 'list'),
      'foo/foobar/list/1/2' => array('foo/foobar', 'list/1/2'),
      '?x=42'               => NULL);

    foreach ($paths as $path => $expected) {

      try {

        $result = $this->dispatcher->parse($path);

        if (is_null($expected)) {
          var_dump($expected, $path, $result);

          $this->fail();
        }
        else {
          $this->assertEqual($expected, $result);
        }

      } catch (Trails_Exception $e) {

        if (isset($expected)) {
         var_dump($e->getMessage());
         $this->fail();
        }
      }
    }
  }
}


class DispatcherTestCase extends UnitTestCase {

  function setUp() {
    $this->setUpFS();
    $this->dispatcher = new PartialMockDispatcher();
    $this->dispatcher->__construct("var://app/", "http://trai.ls", "default");
  }

  function tearDown() {
    stream_wrapper_unregister("var");
    unset($this->dispatcher);
  }

  function setUpFS() {
    ArrayFileStream::set_filesystem(array(
      'app' => array(
        'controllers' => array(
          'foo.php' => '<?',
        ),
      ),
    ));
    stream_wrapper_register("var", "ArrayFileStream") or die("Failed to register protocol");
  }

  function test_should_instantiate_controller() {
    $controller = new FooController();
    $controller->__construct($this->dispatcher);

    # Dispatching to FooController#index_action won't set a response thus
    # provoking an error. By calling #render_nothing before dispatching we can
    # preclude this.
    $controller->render_nothing();

    $this->dispatcher->expectOnce('load_controller', array('foo'));
    $this->dispatcher->setReturnValue('load_controller', $controller);

    $result = $this->dispatcher->dispatch("/foo");
  }

  function test_should_display_error_on_framework_exception() {
    $exception = new Trails_Exception(500);
    $this->dispatcher->throwOn('load_controller', $exception);
    $this->dispatcher->expectOnce('trails_error', array($exception));
    $this->dispatcher->setReturnValue('trails_error', new Trails_Response());
    $result = $this->dispatcher->dispatch("/foo");
  }

  function test_should_rescue_app_exceptions_in_controller() {
    $controller = new FooController();
    $controller->__construct($this->dispatcher);

    $this->dispatcher->expectOnce('load_controller');
    $this->dispatcher->setReturnValue('load_controller', $controller);

    $exception = new Exception(__LINE__);
    $controller->throwOn('index_action', $exception);
    $controller->expectOnce('rescue', array($exception));
    $controller->setReturnValue('rescue', new Trails_Response());


    $this->dispatcher->dispatch("/foo");
  }
}
