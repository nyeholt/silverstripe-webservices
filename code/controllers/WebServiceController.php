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

	public function init() {
		parent::init();
		$this->converters['json'] = array(
			'DataObject' => new DataObjectJsonConverter(),
			'DataObjectSet' => new DataObjectSetJsonConverter(),
			'ScalarItem'	=> new ScalarJsonConverter(),
		);
		
		if (strpos($this->request->getURL(), 'xmlservice') === 0) {
			$this->format = 'xml';
		}
	}

	public function handleRequest(SS_HTTPRequest $request) {
		try {
			if (!Member::currentUserID()) {
				$token = $request->requestVar('token');
				if (!$token) {
					throw new WebServiceException(403, "Missing token parameter");
				}
				$user = singleton('TokenAuthenticator')->authenticate($token);
				if (!$user) {
					throw new WebServiceException(403, "Invalid user token");
				}
			} else {
				// we check the SecurityID parameter
				$securityID = $request->requestVar('SecurityID');
				if ($securityID != SecurityToken::inst()->getValue()) {
					throw new WebServiceException(403, "Invalid security ID");
				}
			}
			return parent::handleRequest($request);
		} catch (WebServiceException $exception) {
			$this->response = new SS_HTTPResponse();
			$this->response->setStatusCode($exception->status);
			$this->response->setBody($this->ajaxResponse($exception->getMessage(), $exception->status));
		} catch (SS_HTTPResponse_Exception $e) {
			$this->response = $e->getResponse();
			$this->response->setBody($this->ajaxResponse($e->getMessage(), $e->getCode()));
		} catch (Exception $exception) {
			$this->response = new SS_HTTPResponse();
			$this->response->setStatusCode(500);
			$this->response->setBody($this->ajaxResponse($exception->getMessage(), 500));
		}

		return $this->response;
	}

	public function index() {
		$service = ucfirst($this->request->param('Service')) . 'Service';
		$method = $this->request->param('Method');

		$svc = singleton($service);

		if ($svc && ($svc instanceof JsonServiceable || method_exists($svc, 'webEnabledMethods'))) {
			$allowedMethods = array();
			if (method_exists($svc, 'webEnabledMethods')) {
				$allowedMethods = $svc->webEnabledMethods();
			}
			
			// if we have a list of methods, lets use those to restrict
			if (count($allowedMethods) && !in_array($method, $allowedMethods)) {
				throw new WebServiceException(403, "You do not have permission to $method");
			}

			$refObj = new ReflectionObject($svc);
			$refMeth = $refObj->getMethod($method);
			/* @var $refMeth ReflectionMethod */
			if ($refMeth) {
				$refParams = $refMeth->getParameters();
				$params = array();
				$allArgs = $this->request->requestVars();
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
								$object = singleton('DataService')->byId($allArgs[$typeArg], $allArgs[$idArg]);
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
					} else if ($refParm->isOptional()) {
						$params[$refParm->getName()] = $refParm->getDefaultValue();
					} else {
						throw new WebServiceException(404, "Service method $method expects parameter " . $refParm->getName());
					}
				}
				
				$return = $refMeth->invokeArgs($svc, $params);
				
				if (is_object($return)) {
					$cls = get_class($return);
				} else {
					$cls = 'ScalarItem';
				}

				if (isset($this->converters[$this->format][$cls])) {
					return $this->converters[$this->format][$cls]->convert($return);
				}

				// otherwise, check the hierarchy if the class actually exists
				if (class_exists($cls)) {
					$hierarchy = array_reverse(array_keys(ClassInfo::ancestry($cls)));
					foreach ($hierarchy as $cls) {
						if (isset($this->converters[$this->format][$cls])) {
							return $this->converters[$this->format][$cls]->convert($return);
						}
					}
				}
				return $this->converters[$this->format]['ScalarItem']->convert($return);
			}
		}
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
		$return = array('return' => $value);
		return Convert::raw2json($return);
	}
}

class ScalarXmlConverter {
	public function convert($value) {
		$return = '<return>'.Convert::raw2xml($value).'</return>';
		return $return;
	}
}