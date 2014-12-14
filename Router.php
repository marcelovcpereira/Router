<?php
namespace Router;

/**
 * MVC Routing class.
 * This class performs an action depending on the HTTP Request.
 *
 * @author  Marcelo Pereira <marcelo.pereira@grupofolha.com.br>
 * @since  2014-12-11
 */
class Router
{
    /**
     * Request protocol.
     *
     * @var string
     * @example http
     * @example https
     *
     */
    protected $serverProtocol;

    /**
     * Version of the HTTP protocol
     * @var string
     * @example 1.1
     */
    protected $serverProtocolVersion;

    /**
     * Hostname
     *
     * @var string
     * @example localhost
     * @example google.com
     */
    protected $serverName; //localhost, marcelovcpereira.com

    /**
     * The path that indicates the correct route.
     * This is the part of the url that comes after the script name and
     * is used to match the defined routes.
     *
     * @var string
     * @example /user/2
     */
    protected $pathInfo;

    /**
     * The query string in the request
     *
     * @var string
     * @example var=Val&var2=val2
     */
    protected $queryString;

    /**
     * The port of host that the request was sent to
     *
     * @var string
     * @example 80
     * @example 443
     */
    protected $serverPort;

    /**
     * The HTTP method of the request
     *
     * @var string
     * @example GET
     * @example POST
     */
    protected $requestMethod;

    /**
     * Full URL containing protocol,hostname, port, script name,
     * path info and query string.
     *
     * @var string
     * @example http://domain.com/user/10
     */
    protected $requestUrl;

    /**
     * URL part containing the script name, path info and query string
     *
     * @var string
     * @example /public/index.php/user/100
     */
    protected $requestUri;

    /**
     * Array of rules for each parameter in the route
     * @var array
     * @example array( "id" => "numeric" , "name" => "letters" )
     */
    protected $rules;

    /**
     * Array of routes to match the request against
     *
     * @var array
     */
    protected $routes;

    /**
     * The delimiter used to separate the class name from the method name in
     * the route definition.
     *
     * @example "\Controllers\HomeController@displayHome"
     */
    const METHOD_DELIMITER = "@";

    /**
     * Regex rules constants
     */
    const PATTERN_ALFANUMERIC = "[0-9a-zA-Z]";
    const PATTERN_ALFANUMERIC_UNDERSCORE = "[0-9a-zA-Z_]";
    const PATTERN_ALFANUMERIC_FULL = "[0-9a-zA-Z_\-\+]";
    const PATTERN_NUMERIC = "[0-9]";
    const PATTERN_LETTERS = "[a-zA-Z]";

    /**
     * HTTP methods constants
     */
    const HTTP_GET = "GET";
    const HTTP_POST = "POST";
    const HTTP_PUT = "PUT";
    const HTTP_DELETE = "DELETE";

    /**
     * Array of HTTP methods
     *
     * @var array
     */
    protected $httpMethods = array(
        self::HTTP_GET,
        self::HTTP_POST,
        self::HTTP_PUT,
        self::HTTP_DELETE
    );


    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialized the router extracting the info from the
     * HTTP request.
     *
     * @return void
     */
    public function initialize()
    {
        $this->getRequestUrl();
        $this->initRoutes();
        $this->initRules();
    }

    /**
     * Initializes and empty array of routes
     *
     * @return void
     */
    public function initRoutes()
    {
        foreach ($this->httpMethods as $method) {
            $this->routes[$method] = array();
        }
    }

    /**
     * Initializes and empty array of rules
     *
     * @return void
     */
    public function initRules()
    {
        foreach ($this->httpMethods as $method) {
            $this->rules[$method] = array();
        }
    }

    /**
     * Adds a HTTP GET route.
     *
     * @param  string $pattern  Pattern of this route.
     * @param  string|closure $function Closure function to be called when the route matches,
     * a string containing the name of the function to be called or a class@method combination to
     * execute a controller method.
     *
     * @return void
     * @example $router->get('/users',function(){ return Repository::findAll('User'); });
     * @example $router->get('/users', 'search_all_users');
     * @example $router->get('/users','\Controllers\UserController@findAll');
     */
    public function get($pattern, $function, $rules = array())
    {
        $route = array($pattern, $function);
        $this->routes[self::HTTP_GET][] = $route;

        if (count($rules)) {
            if (!isset($this->rules[self::HTTP_GET][$pattern])) {
                $this->rules[self::HTTP_GET][$pattern] = $rules;
            } else {
                throw new \Exception("Route already defined: $pattern");
            }
        }
    }

    /**
     * Parses the request and executes the matched Route.
     *
     * @return void
     */
    public function parseRoute()
    {
        $matchedRoute = null;
        $method= $this->getRequestMethod();
        foreach ($this->routes[$method] as $route) {
            list($pattern,$target) = $route;
            $originalPattern = $pattern;
            $pattern = str_replace("/", "\\/", strtolower($pattern));
            //Obtain matches against any "variable" between curly brackets, besides slash (/) and
            //curly brackets ({}) to prevent matching folders
            preg_match_all("/{([^\/\{\}]*)}/", $pattern, $matches);
            if ($matches) {
                list($tokens,$ids) = $matches;
                foreach ($tokens as $index => $token) {
                    $hasRules = isset($this->rules[$method][$originalPattern][$ids[$index]]);
                    if ($hasRules) {
                        $rule = $this->translateRule($this->rules[$method][$originalPattern][$ids[$index]]);
                    } else {
                        //If there are no rules, set it to an alfanumeric parameter
                        $rule = static::PATTERN_ALFANUMERIC_UNDERSCORE;
                    }
                    $pattern = str_replace($token, "(?<" . $ids[$index] . ">$rule+)", $pattern);
                }
            }
            if (preg_match("/^$pattern$/", strtolower($this->getPathInfo()), $matches)) {
                /**
                * Found the route!
                */
                $matchedRoute = $route;
                array_shift($matches);
                $realVars = array();

                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $realVars[$key] = $value;
                    }
                }
                if ((is_string($target) && function_exists($target)) ||
                    is_callable($target)) {
                    $fixedParams = $this->fixParamOrder($target, $realVars);
                    call_user_func_array($target, $fixedParams);
                } elseif (is_string($target)) {
                    $isMethod = strpos($target, static::METHOD_DELIMITER);
                    if ($isMethod !== false) {
                        list($class,$method) = explode(static::METHOD_DELIMITER, $target);
                        if (class_exists($class) && method_exists($class, $method)) {
                            $var = new $class;
                            $fixedParams = $this->fixParamOrder(array($var, $method), $realVars);
                            call_user_func_array(array($var, $method), $fixedParams);
                        } else {
                            throw new \Exception("Undefined class or method: $class@$method");
                        }
                    } else {
                        throw new \Exception("Bad Route format: not closure, not function and not instance@method.");
                    }
                }
                $this->finalize();
            }
        }
        //If no route was found...
        if (is_null($matchedRoute)) {
            throw new \Exception("Route not found exception.");
        }
    }

    /**
     * Finalizes the router after the execution of the
     * correct route.
     *
     * @return void
     */
    public function finalize()
    {
        exit();
    }

    /**
     * Returns the REGEX for a defined rule.
     *
     * @param  string $rule Name of the rule
     * @return string       Regex of the rule
     */
    public function translateRule($rule)
    {
        switch ($rule) {
            case "numeric":
                return static::PATTERN_NUMERIC;
                break;
            case "letters":
                return static::PATTERN_LETTERS;
                break;
            case "alfanumeric":
                return static::PATTERN_ALFANUMERIC;
                break;
            case "alfanumeric_full":
                return static::PATTERN_ALFANUMERIC_FULL;
                break;
            default:
                return static::PATTERN_ALFANUMERIC_UNDERSCORE;
                break;
        }
    }

    /**
     * Fix the order of arguments based on definition of method/function.
     * Returns the array $params reordered to match $callable argument definition names.
     * @param  mixed $callable The function/method which definition should be matched
     * @param  array $params   List of parameter in a random order
     * @return array           Parameters reordered to match function defined names
     */
    public function fixParamOrder($callable, $params)
    {
        $fixedParams = array();
        if (is_array($callable) && count($callable) == 2) {
            try {
                $reflex = new \ReflectionMethod($callable[0], $callable[1]);
            } catch (\ReflectionException $e) {
                throw new \Exception(sprintf("Class/Method does not exits (%s)", $callable[0]."\\".$callable[1]));
            }
        } else {
            try {
                $reflex = new \ReflectionFunction($callable);
            } catch (\ReflectionException $e) {
                throw new \Exception("Function $callable does not exists");
            }
        }
        $functionParams = $reflex->getParameters();
        foreach ($functionParams as $param) {
            if (isset($params[$param->name])) {
                $fixedParams[] = $params[$param->name];
            } else {
                throw new \Exception("Parameter Definition doesn't match route label. Param:".$param->name . " , Route labels: ".$this->printRouteLabels($params));
            }
        }
        return $fixedParams;
    }

    /**
     * Prints a string containing the keys of an array
     * @param  array $routeArgs Array to be printed
     * @return string Stringification of an array
     */
    public function printRouteLabels($routeArgs)
    {
        $return = "[";
        foreach ($routeArgs as $label => $value) {
            $return .= "$label,";
        }
        $return = rtrim($return, ",");
        return "$return]";
    }

    /**
     * Returns the request method
     *
     * @return string Http method
     * @example GET
     */
    public function getRequestMethod()
    {
        if (!isset($this->requestMethod)) {
            $this->requestMethod = AddSlashes(StripSlashes(strip_tags($_SERVER["REQUEST_METHOD"])));
        }

        return $this->requestMethod;
    }

    /**
     * Returns the path info
     *
     * @return string Path info
     * @example /users/new
     */
    public function getPathInfo()
    {
        if (!isset($this->pathInfo)) {
            $this->pathInfo = rtrim(AddSlashes(StripSlashes(strip_tags($_SERVER["PATH_INFO"]))), "/");
        }

        return $this->pathInfo;
    }

    /**
     * Returns the query string of the request
     *
     * @return string The query string
     * @example ?userId=20&page=2
     */
    public function getQueryString()
    {
        if (!isset($this->queryString)) {
            $this->queryString = AddSlashes(StripSlashes(strip_tags($_SERVER["QUERY_STRING"])));
        }

        return $this->queryString;
    }

    /**
     * Returns the path to the script.
     *
     * @return string Script path
     * @example path/index.php
     */
    public function getScriptName()
    {
        if (!isset($this->scriptName)) {
            $this->scriptName = AddSlashes(StripSlashes(strip_tags($_SERVER["SCRIPT_NAME"])));
        }

        return $this->scriptName;
    }

    /**
     * The URI part of the request
     *
     * @return string
     * @example /path/to/index.php/users/new
     */
    public function getRequestUri()
    {
        if (!isset($this->requestUri)) {
            $this->requestUri = $this->getScriptName() . $this->getPathInfo();
            if (strlen($this->getQueryString())) {
                $this->requestUri .= "?" . $this->getQueryString();
            }
        }

        return $this->requestUri;
    }

    /**
     * Full URL
     *
     * @return string
     * @example http://localhost/projetos/worker/1
     */
    public function getRequestUrl()
    {
        if (!isset($this->requestUrl)) {
            $this->requestUrl = $this->getServerProtocol() . "://" . $this->getServerName();
            if (($this->isHTTP() && $this->getServerPort() != "80") ||
                ($this->isHTTPS() && $this->getServerPort() != "443") ) {
                $this->requestUrl .= ":" . $this->getServerPort();
            }
            $this->requestUrl .= $this->getRequestUri();
        }

        return $this->requestUrl;
    }

    /**
     * Returns the port used by the request
     *
     * @return string Request port
     * @example 80
     */
    public function getServerPort()
    {
        if (!isset($this->serverPort)) {
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
            if ($this->isCli()) {
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
        if (!isset($this->serverName)) {
            if (isset($_SERVER['HTTP_HOST'])) {
                //$_SERVER[HTTP_HOST] can hold the port number if it's not the default value
                $colon = strpos(':', $_SERVER['HTTP_HOST']);

                if ($colon !== false) {
                    $this->serverName = substr($_SERVER['HTTP_HOST'], 0, $colon);
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
