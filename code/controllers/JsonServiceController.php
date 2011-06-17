<?php

/**
 * Controller designed to wrap around calls to defined services
 * 
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class JsonServiceController extends Controller {
	
	/**
	 * List of object -> json converter classes
	 *
	 * @var array
	 */
	protected $converters = array();
	
	public function init() {
		parent::init();
		$this->converters['DataObject'] = new DataObjectJsonConverter();
		$this->converters['DataObjectSet'] = new DataObjectSetJsonConverter();
	}

	public function handleRequest(SS_HTTPRequest $request) {
		try {
			if (!Member::currentUserID()) {
				$token = $request->getVar('token');
				if (!$token) {
					throw new WebServiceException(403, "Missing token parameter");
				}
				$user = singleton('TokenAuthenticator')->authenticate($token);
				if (!$user) {
					throw new WebServiceException(403, "Invalid user token");
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

		if ($svc && $svc instanceof JsonServiceable || method_exists($svc, 'webEnabledMethods')) {
			$allowedMethods = array();
			if (method_exists($svc, 'webEnabledMethods')) {
				$allowedMethods = $svc->webEnabledMethods();
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
					if (isset($allArgs[$refParm->getName()])) {
						$params[$refParm->getName()] = $allArgs[$refParm->getName()];
					} else if ($refParm->isOptional()) {
						$params[$refParm->getName()] = $refParm->getDefaultValue();
					} else {
						throw new WebServiceException(404, "Service method $method expects parameter " . $refParm->getName());
					}
				}
				$return = $refMeth->invokeArgs($svc, $params);
				
				if (is_null($return)) {
					return '{}';
				} else {
					
					if (is_object($return)) {
						$cls = get_class($return);
					
						if (isset($this->converters[$cls])) {
							return $this->converters->convert($retur);
						}

						// otherwise, check the hierarchy 
						$hierarchy = array_reverse(array_keys(ClassInfo::ancestry($cls)));

						foreach ($hierarchy as $cls) {
							if (isset($this->converters[$cls])) {
								return $this->converters[$cls]->convert($return);
							}
						}
					}
					return Convert::raw2json($return);
				}
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