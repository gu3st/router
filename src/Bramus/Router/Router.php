<?php
/**
 * @author      Bram(us) Van Damme <bramus@bram.us>
 * @copyright   Copyright (c), 2013 Bram(us) Van Damme
 * @license     MIT public license
 */
namespace Bramus\Router;

require_once(__DIR__ . '/Context.php');

/**
 * Class Router.
 */
class Router
{
    /**
     * @var array The route patterns and their handling functions
     */
    private $afterRoutes = array();

    /**
     * @var array The before middleware route patterns and their handling functions
     */
    private $beforeRoutes = array();

    /**
     * @var array [object|callable] The function to be executed when no route has been matched
     */
    protected $notFoundCallback = [];

    /**
     * @var string Current base route, used for (sub)route mounting
     */
    private $baseRoute = '';

    /**
     * @var string The Request Method that needs to be handled
     */
    private $requestedMethod = '';

    /**
     * @var string The Server Base Path for Router Execution
     */
    private $serverBasePath;

    /**
     * @var string Default Controllers Namespace
     */
    private $namespace = '';

    /** @var array Context that gets passed into each handler and can be  */
    private $data = [];

    /**
     * Store a before middleware route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string          $methods Allowed methods, | delimited
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function before($methods, $pattern, ...$fns)
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        if ($methods === '*') {
            $methods = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
        }
        
        foreach (explode('|', $methods) as $method) {
            $this->beforeRoutes[$method][] = array(
                'pattern' => $pattern,
                'fns' => $fns,
            );
        }
    }

    /**
     * Store a route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string          $methods Allowed methods, | delimited
     * @param string          $pattern A route pattern such as /about/system
     * @param array[object|callable] $fn      The handling function to be executed
     */
    public function match($methods, $pattern, ...$fns)
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->afterRoutes[$method][] = array(
                'pattern' => $pattern,
                'fns' => $fns,
            );
        }
    }

    /**
     * Shorthand for a route accessed using any method.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function all($pattern, ...$fns)
    {
        $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $fns);
    }

    /**
     * Shorthand for a route accessed using GET.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function get($pattern, ...$fns)
    {
        $this->match('GET', $pattern, ...$fns);
    }

    /**
     * Shorthand for a route accessed using POST.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function post($pattern, ...$fns)
    {
        $this->match('POST', $pattern, ...$fns);
    }

    /**
     * Shorthand for a route accessed using PATCH.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function patch($pattern, ...$fns)
    {
        $this->match('PATCH', $pattern, ...$fns);
    }

    /**
     * Shorthand for a route accessed using DELETE.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function delete($pattern, ...$fns)
    {
        $this->match('DELETE', $pattern, ...$fns);
    }

    /**
     * Shorthand for a route accessed using PUT.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function put($pattern, ...$fns)
    {
        $this->match('PUT', $pattern, ...$fns);
    }

    /**
     * Shorthand for a route accessed using OPTIONS.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function options($pattern, ...$fns)
    {
        $this->match('OPTIONS', $pattern, ...$fns);
    }

    /**
     * Mounts a collection of callbacks onto a base route.
     *
     * @param string   $baseRoute The route sub pattern to mount the callbacks on
     * @param callable $fn        The callback method
     */
    public function mount($baseRoute, $fn)
    {
        // Track current base route
        $curBaseRoute = $this->baseRoute;

        // Build new base route string
        $this->baseRoute .= $baseRoute;

        // Call the callable
        call_user_func($fn);

        // Restore original base route
        $this->baseRoute = $curBaseRoute;
    }

    /**
     * Get all request headers.
     *
     * @return array The request headers
     */
    public function getRequestHeaders()
    {
        $headers = array();

        // If getallheaders() is available, use that
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            // getallheaders() can return false if something went wrong
            if ($headers !== false) {
                return $headers;
            }
        }

        // Method getallheaders() not available or went wrong: manually extract 'm
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get the request method used, taking overrides into account.
     *
     * @return string The Request method to handle
     */
    public function getRequestMethod()
    {
        // Take the method as found in $_SERVER
        $method = $_SERVER['REQUEST_METHOD'];

        // If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_start();
            $method = 'GET';
        }

        // If it's a POST request, check for a method override header
        elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $headers = $this->getRequestHeaders();
            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }

        return $method;
    }

    /**
     * Set a Default Lookup Namespace for Callable methods.
     *
     * @param string $namespace A given namespace
     */
    public function setNamespace($namespace)
    {
        if (is_string($namespace)) {
            $this->namespace = $namespace;
        }
    }

    /**
     * Get the given Namespace before.
     *
     * @return string The given Namespace if exists
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Execute the router: Loop all defined before middleware's and routes, and execute the handling function if a match was found.
     *
     * @param object|callable $callback Function to be executed after a matching route was handled (= after router middleware)
     *
     * @return bool
     */
    public function run($callback = null)
    {
        // Define which method we need to handle
        $this->requestedMethod = $this->getRequestMethod();

        // Handle all before middlewares
        if (isset($this->beforeRoutes[$this->requestedMethod])) {
            $this->handle($this->beforeRoutes[$this->requestedMethod]);
        }

        // Handle all routes
        $numHandled = 0;
        if (isset($this->afterRoutes[$this->requestedMethod])) {
            $numHandled = $this->handle($this->afterRoutes[$this->requestedMethod], true);
        }

        // If no route was handled, trigger the 404 (if any)
        if ($numHandled === 0) {
            if (isset($this->afterRoutes[$this->requestedMethod])) {
                $this->trigger404($this->afterRoutes[$this->requestedMethod]);
            } else {
                $this->trigger404();
            }
        } // If a route was handled, perform the finish callback (if any)
        elseif ($callback && is_callable($callback)) {
            $callback();
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }

        // Return true if a route was handled, false otherwise
        return $numHandled !== 0;
    }

    /**
     * Set the 404 handling function.
     *
     * @param object|callable|string $match_fn The function to be executed
     * @param object|callable $fn The function to be executed
     */
    public function set404($match_fn, $fn = null)
    {
      if (!is_null($fn)) {
        $this->notFoundCallback[$match_fn] = $fn;
      } else {
        $this->notFoundCallback['/'] = $match_fn;
      }
    }

    /**
     * Triggers 404 response
     *
     * @param string $pattern A route pattern such as /about/system
     */
    public function trigger404($match = null){

        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // handle 404 pattern
        if (count($this->notFoundCallback) > 0)
        {
            // loop fallback-routes
            foreach ($this->notFoundCallback as $route_pattern => $route_callable) {

              // matches result
              $matches = [];

              // check if there is a match and get matches as $matches (pointer)
              $is_match = $this->patternMatches($route_pattern, $this->getCurrentUri(), $matches, PREG_OFFSET_CAPTURE);

              // is fallback route match?
              if ($is_match) {
                $params = $this->getParams($matches);

                $this->invokeMultiple([$route_callable], $params);

                ++$numHandled;
              }
            }
        }
        if (($numHandled == 0) && (isset($this->notFoundCallback['/']))) {
            $this->invoke($this->notFoundCallback['/']);
        } elseif ($numHandled == 0) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        }
    }

    /**
    * Replace all curly braces matches {} into word patterns (like Laravel)
    * Checks if there is a routing match
    *
    * @param $pattern
    * @param $uri
    * @param $matches
    * @param $flags
    *
    * @return bool -> is match yes/no
    */
    private function patternMatches($pattern, $uri, &$matches, $flags)
    {
      // Replace all curly braces matches {} into word patterns (like Laravel)
      $pattern = preg_replace('/\/{(.*?)}/', '/(?<$1>.*?)', $pattern);

      // we may have a match!
      return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
    }

    /**
     * Handle a a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes       Collection of route patterns and their handling functions
     * @param bool  $quitAfterRun Does the handle function need to quit after one route was matched?
     *
     * @return int The number of routes handled
     */
    private function handle($routes, $quitAfterRun = false)
    {
        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // The current page URL
        $uri = $this->getCurrentUri();

        // Loop all routes
        foreach ($routes as $route) {

            // get routing matches
            $is_match = $this->patternMatches($route['pattern'], $uri, $matches, PREG_OFFSET_CAPTURE);

            // is there a valid match?
            if ($is_match) {
                $params = $this->getParams($matches);

                // Call the handling function with the URL parameters if the desired input is callable
                $this->invokeMultiple($route['fns'], $params);

                ++$numHandled;

                // If we need to quit, then quit
                if ($quitAfterRun) {
                    break;
                }
            }
        }

        // Return the number of routes handled
        return $numHandled;
    }

    private function invokeMultiple($fns, $params = []) {
        $context = new Context($params, $this->data);
        if(is_array($fns)) {
            foreach($fns as $fn) {
                $this->invoke($fn, $context);
                if($context->getStop()) {
                    break;
                }
            }
        }
    }

    private function invoke($fn, $context)
    {
        if (is_callable($fn)) {
            call_user_func_array($fn, $context);
        }

        // If not, check the existence of special parameters
        elseif (stripos($fn, '@') !== false) {
            // Explode segments of given route
            list($controller, $method) = explode('@', $fn);

            // Adjust controller class if namespace has been set
            if ($this->getNamespace() !== '') {
                $controller = $this->getNamespace() . '\\' . $controller;
            }

            try {
                $reflectedMethod = new \ReflectionMethod($controller, $method);
                // Make sure it's callable
                if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                    if ($reflectedMethod->isStatic()) {
                       forward_static_call_array(array($controller, $method), $context);
                    } else {
                        // Make sure we have an instance, because a non-static method must not be called statically
                        if (\is_string($controller)) {
                            $controller = new $controller();
                        }
                        call_user_func_array(array($controller, $method), $context);
                    }
                }
            } catch (\ReflectionException $reflectionException) {
                // The controller class is not available or the class does not have the method $method
            }
        }
    }

    /**
     * Define the current relative URI.
     *
     * @return string
     */
    public function getCurrentUri()
    {
        // Get the current Request URI and remove rewrite base path from it (= allows one to run the router in a sub folder)
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBasePath()));

        // Don't take query params into account on the URL
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // Remove trailing slash + enforce a slash at the start
        return '/' . trim($uri, '/');
    }

    /**
     * Return server base Path, and define it if isn't defined.
     *
     * @return string
     */
    public function getBasePath()
    {
        // Check if server base path is defined, if not define it.
        if ($this->serverBasePath === null) {
            $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return $this->serverBasePath;
    }

    /**
     * Explicilty sets the server base path. To be used when your entry script path differs from your entry URLs.
     * @see https://github.com/bramus/router/issues/82#issuecomment-466956078
     *
     * @param string
     */
    public function setBasePath($serverBasePath)
    {
        $this->serverBasePath = $serverBasePath;
    }

    private function getParams($matches = []) {

        // Rework matches to only contain the matches, not the orig string
        $matches = array_slice($matches, 1);
        // make keys orderly
        $keys = array_keys($matches);
        natcasesort($keys);
        $keys = array_values($keys);

        foreach($keys as $key) {
            $match = $matches[$key];
            if(!is_numeric($key)){
                $params[strtolower($key)] = $match[0][0];
                continue;
            }
            if (isset($matches[$key + 1]) && isset($matches[$key + 1][0]) && is_array($matches[$key + 1][0])) {
                if ($matches[$key + 1][0][1] > -1) {
                    $params[$key] = trim(substr($match[0][0], 0, $matches[$key + 1][0][1] - $match[0][1]), '/');
                    continue;
                }
            }
            $params[$key] = isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
        }
        return $params;
    }

    private function splat($array = array()) {
        $ret = [];
        foreach($array as $v) {
            if(is_array($v)){
                foreach($v as $v2) {
                    $ret[] = $v2;
                }
            }
            else {
                $ret[] = $v;
            }
        }
        return $ret;
    }
}
