<?php

/**
 * Controller designed to wrap around calls to defined services
 * 
 * To call a service, use jsonservice/servicename/methodname
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WebServiceController extends Controller {
	/**
	 * List of object -> json converter classes
	 *
	 * @var array
	 */
	protected $converters = array();
	
	protected $format = 'json';
	
	private static $dependencies = array(
		'webserviceAuthenticator'	=> '%$WebserviceAuthenticator',
		'injector'					=> '%$Injector',
	);

	/**
	 * @var WebserviceAuthenticator 
	 */
	public $webserviceAuthenticator;
	
	/**
	 * @var Injector
	 */
	public $injector;

	public function init() {
		
		$this->converters['json'] = array(
			'DataObject'		=> new DataObjectJsonConverter(),
			'DataObjectSet'		=> new DataObjectSetJsonConverter(),
			'DataList'			=> new DataObjectSetJsonConverter(),
			'ArrayList'			=> new DataObjectSetJsonConverter(),
			'Array'				=> new ArrayJsonConverter(),
			'ScalarItem'		=> new ScalarJsonConverter(),
			'stdClass'			=> new ScalarJsonConverter(),
			'FinalConverter'	=> new FinalJsonConverter()
		);

		$this->converters['xml'] = array(
			'DataObject'		=> new DataObjectXmlConverter(),
			'DataObjectSet'		=> new DataObjectSetXmlConverter(),
			'DataList'			=> new DataObjectSetXmlConverter(),
			'ArrayList'			=> new DataObjectSetXmlConverter(),
			'Array'				=> new ArrayXmlConverter(),
			'ScalarItem'		=> new ScalarXmlConverter(),
			'stdClass'			=> new ScalarXmlConverter(),
			'FinalConverter'	=> new FinalXmlConverter()
		);

		if (strpos($this->request->getURL(), 'xmlservice') === 0) {
			$this->format = 'xml';
		}
	}

	public function handleRequest(SS_HTTPRequest $request, DataModel $model) {
		try {
			$this->pushCurrent();
			
			$auth = $this->webserviceAuthenticator->authenticate($request);
			
			if (!$auth) {
				throw new WebServiceException(403, 'User not found');
			}
			
			// borrowed from Controller
			$this->urlParams = $request->allParams();
			$this->request = $request;
			$this->response = new SS_HTTPResponse();
			$this->setDataModel($model);

			$this->extend('onBeforeInit');

			$this->init();

			$this->extend('onAfterInit');
			
			if($this->response->isFinished()) {
				$this->popCurrent();
				return $this->response;
			}

			$response = $this->handleService($request, $model);
			
			if (self::has_curr()) {
				$this->popCurrent();
			}
			
			if ($response instanceof SS_HTTPResponse) {
				$response->addHeader('Content-Type', 'application/'.$this->format);
			}
			HTTP::add_cache_headers($this->response);
			
			return $response;
		} catch (WebServiceException $exception) {
			$this->response = new SS_HTTPResponse();
			$this->response->setStatusCode($exception->status);
			$this->response->setBody($this->ajaxResponse($exception->getMessage(), $exception->status));
		} catch (SS_HTTPResponse_Exception $e) {
			$this->response = $e->getResponse();
			$this->response->setBody($this->ajaxResponse($e->getMessage(), $e->getCode()));
		} catch (Exception $exception) {
			$code = 500;
			// check type explicitly in case the Restricted Objects module isn't installed
			if (class_exists('PermissionDeniedException') && $exception instanceof PermissionDeniedException) {
				$code = 403;
			}

			$this->response = new SS_HTTPResponse();
			$this->response->setStatusCode($code);
			$this->response->setBody($this->ajaxResponse($exception->getMessage(), $code));
		}
		
		return $this->response;
	}
	
	/**
	 * Calls to webservices are routed through here and converted
	 * from url params to method calls. 
	 * 
	 * @return mixed
	 */
	public function handleService() {
		$service = ucfirst($this->request->param('Service')) . 'Service';
		$method = $this->request->param('Method');

		$body = $this->request->getBody();
		$requestType = strlen($body) > 0 ? 'POST' : $this->request->httpMethod(); // (count($this->request->postVars()) > 0 ? 'POST' : 'GET');

		$svc = $this->injector->get($service);
		
		$response = '';

		if ($svc && ($svc instanceof WebServiceable || method_exists($svc, 'webEnabledMethods'))) {
			$allowedMethods = array();
			if (method_exists($svc, 'webEnabledMethods')) {
				$allowedMethods = $svc->webEnabledMethods();
			}

			// if we have a list of methods, lets use those to restrict
			if (count($allowedMethods)) {
				$this->checkMethods($method, $allowedMethods, $requestType);
			} else {
				// we only allow 'read only' requests so we wrap everything
				// in a readonly transaction so that any database request
				// disallows write() calls
				// @TODO
			}

			if (!Member::currentUserID()) {
				// require service to explicitly state that the method is allowed
				if (method_exists($svc, 'publicWebMethods')) {
					$publicMethods = $svc->publicWebMethods();
					if (!isset($publicMethods[$method])) {
						throw new WebServiceException(403, "Public method $method not allowed");
					}
				} else {
					throw new WebServiceException(403, "Method $method not allowed; no public methods defined");
				}
			}

			$refObj = new ReflectionObject($svc);
			$refMeth = $refObj->getMethod($method);
			/* @var $refMeth ReflectionMethod */
			if ($refMeth) {
				
				$allArgs = $this->getRequestArgs($requestType);

				$refParams = $refMeth->getParameters();
				$params = array();

				foreach ($refParams as $refParm) {
					/* @var $refParm ReflectionParameter */
					$paramClass = $refParm->getClass();
					// if we're after a dataobject, we'll try and find one using
					// this name with ID and Type parameters
					if ($paramClass && ($paramClass->getName() == 'DataObject' || $paramClass->isSubclassOf('DataObject'))) {
						$idArg = $refParm->getName().'ID';
						$typeArg = $refParm->getName().'Type';
						
						if (isset($allArgs[$idArg]) && isset($allArgs[$typeArg]) && class_exists($allArgs[$typeArg])) {
							$object = null;
							if (class_exists('DataService')) {
								$object = $this->injector->DataService->byId($allArgs[$typeArg], $allArgs[$idArg]);
							} else {
								$object = DataObject::get_by_id($allArgs[$typeArg], $allArgs[$idArg]);
								if (!$object->canView()) {
									$object = null;
								}
							}
							if ($object) {
								$params[$refParm->getName()] = $object;
							}
						}
					} else if (isset($allArgs[$refParm->getName()])) {
						$params[$refParm->getName()] = $allArgs[$refParm->getName()];
					} else if ($refParm->getName() == 'file' && $requestType == 'POST') {
						// special case of a binary file upload
						$params['file'] = $body;
					} else if ($refParm->isOptional()) {
						$params[$refParm->getName()] = $refParm->getDefaultValue();
					} else {
						throw new WebServiceException(500, "Service method $method expects parameter " . $refParm->getName());
					}
				}
				
				$return = $refMeth->invokeArgs($svc, $params);
				
				$responseItem = $this->convertResponse($return);
				
				$response = $this->converters[$this->format]['FinalConverter']->convert($responseItem);
			}
		}
		
		$this->response->setBody($response);
		return $this->response;
	}
	
	/**
	 * Process a request URL + body to get all parameters for a request
	 * 
	 * @param string $requestType
	 * @return array
	 */
	protected function getRequestArgs($requestType = 'GET') {
		if ($requestType == 'GET') {
			$allArgs = $this->request->getVars();
		} else {
			$allArgs = $this->request->postVars();
		}
		
		unset($allArgs['url']);

		$contentType = strtolower($this->request->getHeader('Content-Type'));

		if (strpos($contentType, 'application/json') !== false && !count($allArgs) && strlen($this->request->getBody())) {
			// decode the body to a params array
			$bodyParams = Convert::json2array($this->request->getBody());
			if (isset($bodyParams['params'])) {
				$allArgs = $bodyParams['params'];
			} else {
				$allArgs = $bodyParams;
			}
		}

		// see if there's any other URL bits to chew up
		$remaining = $this->request->remaining();
		$bits = explode('/', $remaining);
		
		for ($i = 0, $c = count($bits); $i < $c; ) {
			$key = $bits[$i];
			$val = isset($bits[$i + 1]) ? $bits[$i + 1] : null;
			if ($val) {
				$allArgs[$key] = $val;
			}
			$i += 2;
		}
		
		return $allArgs;
	}
	
	/**
	 * Check the allowed methods for access rights
	 * 
	 * @param array $allowedMethods
	 * @throws WebServiceException 
	 */
	protected function checkMethods($method, $allowedMethods, $requestType) {
		if (!isset($allowedMethods[$method])) {
			throw new WebServiceException(403, "You do not have permission to $method");
		}

		$info = $allowedMethods[$method];
		$allowedType = $info;
		if (is_array($info)) {
			$allowedType = isset($info['type']) ? $info['type'] : '';
			
			if (isset($info['perm'])) {
				if (!Permission::check($info['perm'])) {
					throw new WebServiceException(403, "You do not have permission to $method");
				}
			}
		}
		
		// otherwise it might be the wrong request type
		if ($requestType != $allowedType) {
			throw new WebServiceException(405, "$method does not support $requestType");
		}
	}

	/**
	 * Converts the given object to something appropriate for a response
	 */
	public function convertResponse($return) {
		if (is_object($return)) {
			$cls = get_class($return);
		} else if (is_array($return)) {
			$cls = 'Array';
		} else {
			$cls = 'ScalarItem';
		}

		if (isset($this->converters[$this->format][$cls])) {
			return $this->converters[$this->format][$cls]->convert($return, $this);
		}

		// otherwise, check the hierarchy if the class actually exists
		if (class_exists($cls)) {
			$hierarchy = array_reverse(array_keys(ClassInfo::ancestry($cls)));
			foreach ($hierarchy as $cls) {
				if (isset($this->converters[$this->format][$cls])) {
					return $this->converters[$this->format][$cls]->convert($return, $this);
				}
			}
		}

		return $return;
	}
	
	/**
	 * Indicate whether public users can access web services in general
	 *
	 * @param boolean $value 
	 */
	public static function set_allow_public($value) {
		self::$allow_public_access = $value;
	}
	
	protected function ajaxResponse($message, $status) {
		return Convert::raw2json(array(
			'message' => $message,
			'status' => $status,
		));
	}
	
}

class WebServiceException extends Exception {
	public $status;
	
	public function __construct($status=403, $message='', $code=null, $previous=null) {
		$this->status = $status;
		parent::__construct($message, $code, $previous);
	}
}

class ScalarJsonConverter {
	public function convert($value) {
		return Convert::raw2json($value);
	}
}

class ScalarXmlConverter {
	public function convert($value) {
		return '<value>' . Convert::raw2xml($value) . '</value>';
	}
}

class FinalJsonConverter {
	public function convert($value) {
		$return = '{"response": '.$value . '}';
		return $return;
	}
}

class FinalXmlConverter {
	public function convert($value) {
		$return = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . '<response>'.$value.'</response>';
		return $return;
	}
}