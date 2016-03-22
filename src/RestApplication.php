<?php
/**
 *
 * The main Application class
 *
 * @package      Xizlr
 * @subpackage   Core     
 * @author       Ken Lalobo <ken@mooti.io>
 */

namespace Mooti\Xizlr\Core;

use Mooti\Xizlr\Core\Exception\ControllerNotFoundException;
use Mooti\Xizlr\Core\Exception\ContainerNotFoundException;
use Mooti\Xizlr\Core\Exception\InvalidControllerException;
use Mooti\Xizlr\Core\Exception\MethodNotAllowedException;
use Mooti\Xizlr\Core\Exception\InvalidMethodException;
use Mooti\Xizlr\Core\Exception\InvalidModuleException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Interop\Container\ContainerInterface;
use League\Route\RouteCollection;
use ICanBoogie\Inflector;

class RestApplication
{
    use Xizlr;

    /**
     * @var array
     */
    private $controllers;

    /**
     * @param array  $identifier  Key/value list of controllers.
     */
    public function __construct(array $controllers)
    {
        $this->controllers = $controllers;
    }

    /**
     * Create a Symfony Request object
     *
     * @return Symfony\Component\HttpFoundation\Request 
     */
    public function createRequest()
    {
        return Request::createFromGlobals();
    }

    /**
     * Create a League RoutCollection object and add our routes to it
     *
     * @return League\Route\RouteCollection
     */
    public function createRouteCollection()
    {
        $routeCollection  = $this->createNew(RouteCollection::class);

        $routeCollection->addRoute('GET', '/{resourceNamePlural}', [$this, 'callGetResources']);
        $routeCollection->addRoute('POST', '/{resourceNamePlural}', [$this, 'callCreateNewResource']);
        $routeCollection->addRoute('PUT', '/{resourceNamePlural}', [$this, 'callCreateNewResource']);
        $routeCollection->addRoute('HEAD', '/{resourceNamePlural}', [$this, 'callMethodNotAllowed']);
        $routeCollection->addRoute('DEL', '/{resourceNamePlural}', [$this, 'callMethodNotAllowed']);

        $routeCollection->addRoute('GET', '/{resourceNamePlural}/{id}', [$this, 'callGetResource']);
        $routeCollection->addRoute('POST', '/{resourceNamePlural}/{id}', [$this, 'callEditResource']);
        $routeCollection->addRoute('PUT', '/{resourceNamePlural}/{id}', [$this, 'callEditResource']);
        $routeCollection->addRoute('HEAD', '/{resourceNamePlural}/{id}', [$this, 'callResourceExists']);
        $routeCollection->addRoute('DEL', '/{resourceNamePlural}/{id}', [$this, 'calldDeleteResource']);

        $routeCollection->addRoute('GET', '/{resourceNamePlural}/{id}/{childNamePlural}', [$this, 'callGetChildResources']);
        $routeCollection->addRoute('POST', '/{resourceNamePlural}/{id}/{childNamePlural}', [$this, 'callCreateNewChildResource']);
        $routeCollection->addRoute('PUT', '/{resourceNamePlural}/{id}/{childNamePlural}', [$this, 'callCreateNewChildResource']);
        $routeCollection->addRoute('HEAD', '/{resourceNamePlural}/{id}/{childNamePlural}', [$this, 'callMethodNotAllowed']);
        $routeCollection->addRoute('DEL', '/{resourceNamePlural}/{id}/{childNamePlural}', [$this, 'callMethodNotAllowed']);

        return $routeCollection;
    }

    /**
     * Bootstrap the application
     *
     * @param ServiceProviderInterface $serviceProvider An option service provider
     */
    public function bootstrap(ServiceProviderInterface $serviceProvider = null)
    {
        $container = $this->createNew(Container::class);

        if (isset($serviceProvider)) {
            $container->registerServices($serviceProvider);    
        }

        $xizlrServiceProvider = $this->createNew(ServiceProvider::class);
        $container->registerServices($xizlrServiceProvider);

        $this->setContainer($container);
    }

    /**
     * Register some modules
     *
     */
    public function registerModules($modules = [])
    {
        for($i = 0; $i < sizeof($modules); $i++) {
            $moduleName = $modules[$i];

            $module = $this->createNew($moduleName);

            if (!$module instanceof ModuleInterface) {
                throw new InvalidModuleException('The module at position '.($i+1).' is invalid');
            }

            $serviceProvider = $module->getServiceProvider();
            $this->getContainer()->registerServices($serviceProvider);
        }
    }

    /**
     * Run the application
     *
     */
    public function run()
    {
        if (empty($this->getContainer()) == true) {
            throw new ContainerNotFoundException('The container cannot be found. Have you forgotten to bootstrap your application?');
        }

        $routeCollection = $this->createRouteCollection();
        $dispatcher = $routeCollection->getDispatcher();

        $request = $this->createRequest();
        $response = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());
        $response->send();
    }

    /**
     * Create a new controller given a resource name
     *
     * @param string $resourceNamePlural The name of the resource being created (e.g 'users' if we are accessing http://account.mooti.io/users)
     *
     * @return Mooti\Xizlr\Core\BaseController
     */
    public function createController($resourceNamePlural)
    {
        if (isset($this->controllers[$resourceNamePlural]) == false) {
            throw new ControllerNotFoundException('the controller for "'.$resourceNamePlural.'" does not exist');
        }

        $controller = $this->createNew($this->controllers[$resourceNamePlural]);

        if (!$controller instanceof BaseController) {
            throw new InvalidControllerException('the controller "'.$this->controllers[$resourceNamePlural].'"" is not an instance of AbstractController');
        }

        return $controller;
    }

    /**
     * Call a method on a resource
     *
     * @param string $resourceNamePlural The name of the resource
     *
     * @return mixed
     */
    public function callMethod($resourceNamePlural, $methodName, $arguments = [])
    {
        $controller = $this->createController($resourceNamePlural);

        if (method_exists($controller, $methodName) == false) {
            throw new InvalidMethodException('The method ('.$methodName.') does not exist for the class '.get_class($controller));
        }
        return call_user_func_array([$controller, $methodName], $arguments);
    }

    /**
     * Get a collection of resources.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callGetResources(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = 'get'.$inflector->camelize($resourceNamePlural);

        return $this->callMethod($resourceNamePlural, $methodName, [$request, $response]);
    }

    /**
     * Create a resource.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callCreateNewResource(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = 'create'.$inflector->camelize($inflector->singularize($resourceNamePlural));

        return $this->callMethod($resourceNamePlural, $methodName, [$request, $response]);
    }

    /**
     * Get a resource.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callGetResource(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $resourceId         = $args['id'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = 'get'.$inflector->camelize($inflector->singularize($resourceNamePlural));

        return $this->callMethod($resourceNamePlural, $methodName, [$resourceId, $request, $response]);
    }

    /**
     * Edit a resource.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callEditResource(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $resourceId         = $args['id'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = 'edit'.$inflector->camelize($inflector->singularize($resourceNamePlural));

        return $this->callMethod($resourceNamePlural, $methodName, [$resourceId, $request, $response]);
    }

    /**
     * Check if a resource exists.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callResourceExists(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $resourceId         = $args['id'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = $inflector->camelize($inflector->singularize($resourceNamePlural), Inflector::DOWNCASE_FIRST_LETTER).'Exists';

        return $this->callMethod($resourceNamePlural, $methodName, [$resourceId, $request, $response]);
    }

    /**
     * Delete a resource.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function calldDeleteResource(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $resourceId         = $args['id'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);
        $methodName         = 'delete'.$inflector->camelize($inflector->singularize($resourceNamePlural));

        return $this->callMethod($resourceNamePlural, $methodName, [$resourceId, $request, $response]);
    }

    /**
     * get the child resources of a resource.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callGetChildResources(Request $request, Response $response, array $args)
    {
        $resourceNamePlural = $args['resourceNamePlural'];
        $resourceId         = $args['id'];
        $childResourceName  = $args['childNamePlural'];
        $inflector          = $this->get(ServiceProvider::INFLECTOR);

        $methodName = 'get'.$inflector->camelize($inflector->singularize($resourceNamePlural)).$inflector->camelize($childResourceName);

        return $this->callMethod($resourceNamePlural, $methodName, [$request, $response]);
    }

    /**
     * create a resource and attach it to another.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     */
    public function callCreateNewChildResource(Request $request, Response $response, array $args)
    {
        $resourceName      = $args['resourceNamePlural'];
        $resourceId        = $args['id'];
        $childResourceName = $args['childNamePlural'];
        $inflector         = $this->get(ServiceProvider::INFLECTOR);

        $methodName = 'create'.$inflector->camelize($inflector->singularize($resourceName)).$inflector->camelize($inflector->singularize($childResourceName));

        return $this->callMethod($resourceName, $methodName, [$request, $response]);
    }

    /**
     * called if you are not allowed to perform a method on a resource
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param array    $args     The arguments to passed to us by the dispatcher (normally derived from the route)
     *
     * @return mixed
     * @throws MethodNotAllowedException
     */
    public function callMethodNotAllowed(Request $request, Response $response, array $args)
    {
        throw new MethodNotAllowedException('You are not allowed to call that method');
    }
}
