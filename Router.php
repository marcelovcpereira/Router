<?php
namespace router;

class Router
{
	
	protected $serverProtocol; //http,https
	protected $serverProtocolVersion; //1.1
	protected $serverName; //localhost, marcelovcpereira.com
	protected $pathInfo;
	protected $queryString;
	protected $serverPort; //80, 443
	protected $requestUrl;
	protected $requestUri;
	protected $requestMethod;
	protected $routes;
	protected $isInitialized = false;

	public function __construct()
	{
		$this->initialize();
	}

	public function initialize()
	{
		$this->getRequestUrl();
		$this->initRoutes();
		$this->isInitialized = true;
	}

	public function initRoutes()
	{
		$this->routes = array(
			"GET" => array(),
			"POST" => array(),
			"PUT" => array(),
			"DELETE" => array()
		);
	}

	public function get($pattern,$function)
	{
		if (!$this->isInitialized) {
			$this->initialize();
		}

		$route = array($pattern, $function);
		$this->routes["GET"][] = $route;
	}

	public function parseRoute()
	{
		$matchedRoute = null;
		foreach($this->routes[$this->getRequestMethod()] as $route) {
			list($pattern,$target) = $route;
			if(preg_match("/^" . str_replace( "/" , "\\/" , $pattern ) . "$/", $this->getPathInfo(),$matches)) {
				var_dump($matches);
				/**
				 * Found the route!
				 */
				$matchedRoute = $route;
				if (is_string($target) && function_exists($target)) {
					call_user_func($target);
				} elseif (is_callable($target)) {
					$target();
				} elseif (is_string($target)) {
					$isMethod = strpos($target, "@");
					if ($isMethod !== false) {
						list($class,$method) = explode("@", $target);
						if(class_exists($class) && method_exists($class, $method)) {
							$var = new $class;
							$var->$method();
						} else {
							throw new \Exception("Undefined class or method: $class@$method");
						}
					} else {
						throw new \Exception("Bad Route format: not callable, not function and not controller@method.");
					}
				}
			}
		}
		//If no route was found...
		if(is_null($matchedRoute)) {
			header('HTTP/1.0 404 Not Found');
			print "Route not found!";
			exit();
		}
	}

	public function getRequestMethod()
	{
		if (!isset($this->requestMethod)) {
			$this->requestMethod = AddSlashes(StripSlashes(strip_tags($_SERVER["REQUEST_METHOD"]))); 
		}

		return $this->requestMethod;
	}

	public function getPathInfo()
	{
		if (!isset($this->pathInfo)) {			
		 	$this->pathInfo = rtrim(AddSlashes(StripSlashes(strip_tags($_SERVER["PATH_INFO"]))),"/"); 
		}

		return $this->pathInfo;
	}

	public function getQueryString()
	{
		if (!isset($this->queryString)) {
			$this->queryString = AddSlashes(StripSlashes(strip_tags($_SERVER["QUERY_STRING"])));
		}

		return $this->queryString;
	}

	public function getScriptName()
	{
		if (!isset($this->scriptName)) {			
			$this->scriptName = AddSlashes(StripSlashes(strip_tags($_SERVER["SCRIPT_NAME"])));
		}

		return $this->scriptName;
	}

	public function getRequestUri()
	{
		if (!isset($this->requestUri)) {
			$this->requestUri = $this->getScriptName() . $this->getPathInfo();
			if(strlen($this->getQueryString())) {
				$this->requestUri .= "?" . $this->getQueryString();
			}
		}

		return $this->requestUri;
	}

	public function getRequestUrl()
	{
		if (!isset($this->requestUrl)) {
			$this->requestUrl = $this->getServerProtocol() . "://" . $this->getServerName();
			if( ($this->isHTTP() && $this->getServerPort() != "80") ||
				($this->isHTTPS() && $this->getServerPort() != "443") ) {
					$this->requestUrl .= ":" . $this->getServerPort();
			}
			$this->requestUrl .= $this->getRequestUri();
		}

		return $this->requestUrl;
	}

	public function getServerPort()
	{
		if(!isset($this->serverPort)) {
			$this->serverPort = $_SERVER['SERVER_PORT'];
		}

		return $this->serverPort;
	}

	/**
	 * Protocol used in the request: Http or Https, empty if Cli Mode
	 * 
	 * @return string Request protocol
	 */
	public function getServerProtocol()
	{
		if (!isset($this->serverProtocol)) {
			if($this->isCli()) {
				$this->serverProtocol = "";
			} else {
				list($this->serverProtocol,$this->serverProtocolVersion) = explode("/", $_SERVER['SERVER_PROTOCOL']);
				$this->serverProtocol = strtolower($this->serverProtocol);

				if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
					$this->serverProtocol = "https";
				}
			}
		}

		return $this->serverProtocol;
	}

	/**
	 * Checks if the request was made using HTTPS
	 * 
	 * @return boolean True if request is https
	 */
	public function isHTTPS()
	{
		return $this->serverProtocol == "https";
	}

	/**
	 * Checks if the request was made using HTTP
	 * 
	 * @return boolean True if request is http
	 */
	public function isHTTP()
	{
		return $this->serverProtocol == "http";
	}

	/**
	 * Check if the script is being called from a
	 * Command Line Interface.
	 * 
	 * @return boolean True if is running on cli
	 */
	public function isCli()
	{
		return php_sapi_name() == 'cli';
	}


	/**
	 * Returns the Server Name
	 *
	 * Recommended using HTTP_HOST, and falling back on SERVER_NAME only if HTTP_HOST was not set. 
	 * SERVER_NAME could be unreliable on the server for a variety of reasons, including:
	 * 1)no DNS support
	 * 2)misconfigured (it depends on SERVER configuration)
	 * 3)behind load balancing software
	 * Source: http://discussion.dreamhost.com/thread-4388.html
	 * 
	 * @return string Name of host
	 */
	public function getServerName()
	{
		if(!isset($this->serverName)) {
			if(isset($_SERVER['HTTP_HOST'])) {
				//$_SERVER[HTTP_HOST] can hold the port number if it's not the default value
				$colon = strpos(':',$_SERVER['HTTP_HOST']);

				if($colon !== false) {
					$this->serverName = substr($_SERVER['HTTP_HOST'],0,$colon);
				} else {
					$this->serverName = $_SERVER['HTTP_HOST'];
				}
			} else {
				$this->serverName = $_SERVER['SERVER_NAME'];
			}
		}
		return $this->serverName;
	}
}