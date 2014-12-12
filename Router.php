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
     * Array of routes to match the request against
     *
     * @var array
     */
    protected $routes;


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
    }

    /**
     * Initializes and empty array of routes
     *
     * @return void
     */
    public function initRoutes()
    {
        $this->routes = array(
         "GET" => array(),
         "POST" => array(),
         "PUT" => array(),
         "DELETE" => array()
        );
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
    public function get($pattern, $function)
    {
        $route = array($pattern, $function);
        $this->routes["GET"][] = $route;
    }

    /**
     * Parses the request and executes the matched Route.
     *
     * @return void
     */
    public function parseRoute()
    {
        $matchedRoute = null;
        foreach ($this->routes[$this->getRequestMethod()] as $route) {
            list($pattern,$target) = $route;
            $pattern = str_replace("/", "\\/", strtolower($pattern));
            //Obtain matches against any "variable" between curly brackets, besides slash (/) and
            //curly brackets ({}) to prevent matching folders
            preg_match_all("/{([^\/\{\}]*)}/", $pattern, $matches);
            if ($matches) {
                list($tokens,$ids) = $matches;
                foreach ($tokens as $index => $token) {
                    $pattern = str_replace($token, "(?<" . $ids[$index] . ">[0-9a-zA-Z_]+)", $pattern);
                }
                print htmlentities($pattern) . "<br>";
            }

            if (preg_match("/^$pattern$/", strtolower($this->getPathInfo()), $matches)) {
                /**
                * Found the route!
                */
                $matchedRoute = $route;

                array_shift($matches);
                $realVars = array();
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $realVars[$key] = $value;
                    }
                }

                if (is_string($target) && function_exists($target)) {
                    call_user_func($target);
                } elseif (is_callable($target)) {
                    $target();
                } elseif (is_string($target)) {
                    $isMethod = strpos($target, "@");
                    if ($isMethod !== false) {
                        list($class,$method) = explode("@", $target);
                        if (class_exists($class) && method_exists($class, $method)) {
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
        if (is_null($matchedRoute)) {
            header('HTTP/1.0 404 Not Found');
            print "Route not found!";
            exit();
        }
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
