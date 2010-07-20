<?php
 define('TRAILS_VERSION','0.7.0');class Trails_Dispatcher{public$trails_root;public$trails_uri;public$default_controller;function __construct($trails_root,$trails_uri,$default_controller){$this->trails_root=$trails_root;$this->trails_uri=$trails_uri;$this->default_controller=$default_controller;}function dispatch($uri){$old_handler=set_error_handler(array($this,'error_handler'),5888);ob_start();$level=ob_get_level();$this->mapUriToResponse($this->cleanRequestUri((string)$uri))->output();while(ob_get_level()>=$level){ob_end_flush();}if(isset($old_handler)){set_error_handler($old_handler);}}function mapUriToResponse($uri){try{if(''===$uri){if(!$this->fileExists($this->default_controller.'.php')){throw new Trails_MissingFile("Default controller '{$this->default_controller}' not found'");}$controller_path=$this->default_controller;$unconsumed=$uri;}else{list($controller_path,$unconsumed)=$this->parse($uri);}$controller=$this->loadController($controller_path);$response=$controller->perform($unconsumed);}catch(Exception$e){$response=isset($controller)?$controller->rescue($e):$this->trailsError($e);}return$response;}function trailsError($exception){ob_clean();$detailed=@$_SERVER['REMOTE_ADDR']==='127.0.0.1';$body=sprintf('<html><head><title>Trails Error</title></head>'.'<body><h1>%s</h1><pre>%s</pre></body></html>',htmlentities($exception->__toString()),$detailed?htmlentities($exception->getTraceAsString()):'');if($exception instanceof Trails_Exception){$response=new Trails_Response($body,$exception->headers,$exception->getCode(),$exception->getMessage());}else{$response=new Trails_Response($body,array(),500,$exception->getMessage());}return$response;}function cleanRequestUri($uri){if(FALSE!==($pos=strpos($uri,'?'))){$uri=substr($uri,0,$pos);}return ltrim($uri,'/');}function parse($unconsumed,$controller=NULL){list($head,$tail)=$this->splitOnFirstSlash($unconsumed);if(!preg_match('/^\w+$/',$head)){throw new Trails_RoutingError("No route matches '$head'");}$controller=(isset($controller)?$controller.'/':'').$head;if($this->fileExists($controller.'.php')){return array($controller,$tail);}else if($this->fileExists($controller)){return$this->parse($tail,$controller);}throw new Trails_RoutingError("No route matches '$head'");}function splitOnFirstSlash($str){preg_match(":([^/]*)(/+)?(.*):",$str,$matches);return array($matches[1],$matches[3]);}function fileExists($path){return file_exists("{$this->trails_root}/controllers/$path");}function loadController($controller){require_once"{$this->trails_root}/controllers/{$controller}.php";$class=Trails_Inflector::camelize($controller).'Controller';if(!class_exists($class)){throw new Trails_UnknownController("Controller missing: '$class'");}return new$class($this);}function error_handler($errno,$string,$file,$line,$context){throw new Trails_Exception(500,$string);}}class Trails_Response{public$body='',$status,$reason,$headers=array();function __construct($body='',$headers=array(),$status=NULL,$reason=NULL){$this->setBody($body);$this->headers=$headers;if(isset($status)){$this->setStatus($status,$reason);}}function setBody($body){$this->body=$body;return$this;}function setStatus($status,$reason=NULL){$this->status=$status;$this->reason=isset($reason)?$reason:$this->getReason($status);return$this;}function getReason($status){$reason=array(100=>'Continue','Switching Protocols',200=>'OK','Created','Accepted','Non-Authoritative Information','No Content','Reset Content','Partial Content',300=>'Multiple Choices','Moved Permanently','Found','See Other','Not Modified','Use Proxy','(Unused)','Temporary Redirect',400=>'Bad Request','Unauthorized','Payment Required','Forbidden','Not Found','Method Not Allowed','Not Acceptable','Proxy Authentication Required','Request Timeout','Conflict','Gone','Length Required','Precondition Failed','Request Entity Too Large','Request-URI Too Long','Unsupported Media Type','Requested Range Not Satisfiable','Expectation Failed',500=>'Internal Server Error','Not Implemented','Bad Gateway','Service Unavailable','Gateway Timeout','HTTP Version Not Supported');return isset($reason[$status])?$reason[$status]:'';}function addHeader($key,$value){$this->headers[$key]=$value;return$this;}function output(){if(isset($this->status)){$this->sendHeader(sprintf('HTTP/1.1 %d %s',$this->status,$this->reason),TRUE,$this->status);}foreach($this->headers as$k=>$v){$this->sendHeader("$k: $v");}echo$this->body;}function sendHeader($header,$replace=FALSE,$status=NULL){if(isset($status)){header($header,$replace,$status);}else{header($header,$replace);}}}class Trails_Controller{protected$dispatcher,$response,$performed,$layout;function __construct($dispatcher){$this->dispatcher=$dispatcher;$this->eraseResponse();}function eraseResponse(){$this->performed=FALSE;$this->response=new Trails_Response();}function getResponse(){return$this->response;}function perform($unconsumed){list($action,$args)=$this->extractActionAndArgs($unconsumed);$before_filter_result=$this->beforeFilter($action,$args);if(!(FALSE===$before_filter_result||$this->performed)){$mapped_action=$this->mapAction($action);if(method_exists($this,$mapped_action)){call_user_func_array(array(&$this,$mapped_action),$args);}else{$this->doesNotUnderstand($action,$args);}if(!$this->performed){$this->renderAction($action);}$this->afterFilter($action,$args);}return$this->response;}function extractActionAndArgs($string){if(''===$string){return array('index',array());}$args=explode('/',$string);$action=array_shift($args);return array($action,$args);}function mapAction($action){return Trails_Inflector::camelize($action).'Action';}function beforeFilter(&$action,&$args){}function afterFilter($action,$args){}function doesNotUnderstand($action,$args){throw new Trails_UnknownAction("No action responded to '$action'.");}function redirect($to){if($this->performed){throw new Trails_DoubleRenderError();}$this->performed=TRUE;$url=preg_match('#^(/|\w+://)#',$to)?$to:$this->urlFor($to);$this->response->addHeader('Location',$url)->setStatus(302);}function renderText($text=' '){if($this->performed){throw new Trails_DoubleRenderError();}$this->performed=TRUE;$this->response->setBody($text);}function renderNothing(){$this->renderText('');}function renderAction($action){$class=get_class($this);$controller_name=Trails_Inflector::underscore(substr($class,0,-10));$this->renderTemplate($controller_name.'/'.$action,$this->layout);}function renderTemplate($template_name,$layout=NULL){$factory=new Flexi_TemplateFactory($this->dispatcher->trails_root.'/views/');$template=$factory->open($template_name);switch(get_class($template)){case'Flexi_JsTemplate':$this->setContentType('text/javascript');break;}$template->set_attributes($this->getAssignedVariables());if(isset($layout)){$template->set_layout($layout);}$this->renderText($template->render());}function getAssignedVariables(){$assigns=array();$protected=get_class_vars(get_class($this));foreach(get_object_vars($this)as$var=>$value){if(!array_key_exists($var,$protected)){$assigns[$var]=&$this->$var;}}$assigns['controller']=$this;return$assigns;}function setLayout($layout){$this->layout=$layout;}function urlFor($to){$args=func_get_args();$args=array_map('urlencode',$args);$args[0]=$to;return$this->dispatcher->trails_uri.'/'.join('/',$args);}function setStatus($status,$reason_phrase=NULL){$this->response->setStatus($status,$reason_phrase);}function setContentType($type){$this->response->addHeader('Content-Type',$type);}function rescue($exception){return$this->dispatcher->trailsError($exception);}}class Trails_Inflector{static function camelize($word){$parts=explode('/',$word);foreach($parts as$key=>$part){$parts[$key]=str_replace(' ','',ucwords(str_replace('_',' ',$part)));}return join('_',$parts);}static function underscore($word){$parts=explode('_',$word);foreach($parts as$key=>$part){$parts[$key]=preg_replace('/(?<=\w)([A-Z])/','_\\1',$part);}return strtolower(join('/',$parts));}}class Trails_Flash implements ArrayAccess{public$flash=array(),$used=array();static function instance(){if(!isset($_SESSION)){throw new Trails_SessionRequiredException();}if(!isset($_SESSION['trails_flash'])){$_SESSION['trails_flash']=new Trails_Flash();}return$_SESSION['trails_flash'];}function offsetExists($offset){return isset($this->flash[$offset]);}function offsetGet($offset){return$this->get($offset);}function offsetSet($offset,$value){$this->set($offset,$value);}function offsetUnset($offset){unset($this->flash[$offset],$this->used[$offset]);}function _use($k=NULL,$v=TRUE){if($k){$this->used[$k]=$v;}else{foreach($this->used as$k=>$value){$this->_use($k,$v);}}}function discard($k=NULL){$this->_use($k);}function&get($k){$return=NULL;if(isset($this->flash[$k])){$return=&$this->flash[$k];}return$return;}function keep($k=NULL){$this->_use($k,FALSE);}function set($k,$v){$this->keep($k);$this->flash[$k]=$v;}function setRef($k,&$v){$this->keep($k);$this->flash[$k]=&$v;}function sweep(){foreach(array_keys($this->flash)as$k){if($this->used[$k]){unset($this->flash[$k],$this->used[$k]);}else{$this->_use($k);}}$fkeys=array_keys($this->flash);$ukeys=array_keys($this->used);foreach(array_diff($fkeys,$ukeys)as$k=>$v){unset($this->used[$k]);}}function __toString(){$values=array();foreach($this->flash as$k=>$v){$values[]=sprintf("'%s': [%s, '%s']",$k,var_export($v,TRUE),$this->used[$k]?"used":"unused");}return"{".join(", ",$values)."}\n";}function __sleep(){$this->sweep();return array('flash','used');}function __wakeUp(){$this->discard();}}class Trails_Exception extends Exception{public$headers;function __construct($status=500,$reason=NULL,$headers=array()){if($reason===NULL){$reason=Trails_Response::getReason($status);}parent::__construct($reason,$status);$this->headers=$headers;}function __toString(){return"{$this->code} {$this->message}";}}class Trails_DoubleRenderError extends Trails_Exception{function __construct(){$message="Render and/or redirect were called multiple times in this action. "."Please note that you may only call render OR redirect, and at most "."once per action.";parent::__construct(500,$message);}}class Trails_MissingFile extends Trails_Exception{function __construct($message){parent::__construct(500,$message);}}class Trails_RoutingError extends Trails_Exception{function __construct($message){parent::__construct(400,$message);}}class Trails_UnknownAction extends Trails_Exception{function __construct($message){parent::__construct(404,$message);}}class Trails_UnknownController extends Trails_Exception{function __construct($message){parent::__construct(404,$message);}}class Trails_SessionRequiredException extends Trails_Exception{function __construct(){$message="Tried to access a non existing session.";parent::__construct(500,$message);}}