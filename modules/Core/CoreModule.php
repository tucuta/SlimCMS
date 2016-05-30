<?php

namespace Modules\Core;

use Slim\Flash\Messages;
use Slim\Router;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Source\AModule;
use App\Source\ModuleManager;
use Modules\Core\Source\MicroModules\LoggerModule;

/**
 * Base module from use SlimCMS
 * Class CoreModule
 * @package Modules\Core
 */
class CoreModule extends AModule
{
    /**
     * Module name
     */
    const MODULE_NAME = 'core';

    /**
     * Require module loaded
     * @var array
     */
    public $requireModules = [];

    /**
     * Init module
     */
    public function initialization()
    {
        $this->registerDB();

        $this->container['dispatcher'] = function () {
            return new EventDispatcher();
        };

        $this->container['router'] = function () {
            return new Router();
            //return new \App\Source\Decorators\RouteDecorator;
        };

        $this->container->dispatcher->dispatch('module.core.beforeInitialization');
    }

    /**
     * Register route in slim framework
     */
    public function registerRoute()
    {
        $this->app->get('/', function ($req, $res) {
            $res->getBody()->write("Core module load. You application get ready.");
        })->setName('home');
    }

    /**
     * Register DI container in slim framework
     */
    public function registerDi()
    {
        $this->container['flash'] = function () {
            return new Messages();
        };

        $this->container['view'] = function ($c) {
            $view = new Twig($c->config['view']['template_path'], $c->config['view']['twig']);

            // Instantiate and add Slim specific extension
            $view->addExtension(new TwigExtension(
                $c['router'],
                $c['request']->getUri()
            ));

            $view->addExtension(new \Twig_Extension_Debug());

            return $view;
        };

    }

    /**
     * Register middleware in slim framework
     */
    public function registerMiddleware()
    {
        $this->container->dispatcher->addListener('app.beforeRun', function ($event) {
            $event->getApp()->add('App\Middleware\CoreFirstLastMiddleware:core');
        }, -1000);
    }

    /**
     * After initialization method and register (DI, Route, Middleware)
     */
    public function afterInitialization()
    {
        parent::afterInitialization();

        $modules = ModuleManager::getInstance();

        $modules->registerModule(new LoggerModule());
        /*$modules->registerModule(new \App\Modules\LoggerModule());
        $modules->registerModule(new \App\Modules\SystemOptionsModule());
        $modules->registerModule(new \App\Modules\CSRFModule());
        $modules->registerModule(new \App\Modules\FlashModule());
        $modules->registerModule(new \App\Modules\AuthModule());
        $modules->registerModule(new \App\Modules\AdminPanelModule());
        $modules->registerModule(new \App\Modules\PublicModule());*/

        if (isset($this->container['settings']['protect_double_route_register']) &&
            $this->container['settings']['protect_double_route_register']
        ) {
            $this->routerControlSystem();
        }
    }

    /**
     * Register DB manager
     */
    protected function registerDB()
    {
        $capsule = new Capsule();

        $capsule->addConnection($this->container->config['db'][$this->container->settings['db_driver']]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $this->container['db'] = function () {
            return new Capsule();
        };
    }

    /**
     * Protected error if registered 2 identity path route
     */
    protected function routerControlSystem()
    {
        $this->container->dispatcher->addListener('app.beforeRun', function ($event) {
            \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) use ($event) {
                foreach ($event->getContainer()->get('router')->getRoutes() as $route) {
                    try {
                        $r->addRoute($route->getMethods(), $route->getPattern(), $route->getIdentifier());
                    } catch (\FastRoute\BadRouteException $e) {
                        $event->getLogger()->error('Register router: ' . $e->getMessage());
                        $event->getContainer()->get('router')->removeRoute($route->getIdentifier());
                        continue;
                    }
                }
            });
        }, 1000);
    }
}