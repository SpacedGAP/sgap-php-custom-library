<?php

namespace SpacedGAP;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Server\Reflection;

abstract class AbstractZf2Module
{
    public static $is_admin_route = false;

    protected $namespace;

    protected $dir;

    public function __construct()
    {
        $this->loadClassInfo();
    }

    protected function loadClassInfo()
    {
        $class_info = Reflection::reflectClass($this);
        $this->namespace = $class_info->getNamespaceName();
        $this->dir = dirname($class_info->getFileName());
    }

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array($this, 'loadConfigLayout'));
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array($this, 'detectIfZfcAdminRoute'));
    }

    public function getConfig()
    {
        // Required to be defined value used in include config files.
        $module = $this->namespace;
        $module_dir = $this->dir;

        $common_config = include MY_LIBRARY_PATH.'/config/module.common.config.php'; 
        $config = include "{$module_dir}/config/module.config.php";

        $cc_object = new \Zend\Config\Config($common_config);
        $c_object = new \Zend\Config\Config($config);

        $merge_config = $c_object->merge($cc_object)->toArray();

        return $merge_config;
    }

    public function getAutoloaderConfig()
    {
        $namespace = $this->namespace;
        $dir = $this->dir;

        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    $namespace => "{$dir}/src/{$namespace}",
                ),
            ),
        );
    }

    // onBootstrap sub actions
    
    /**
     * Load config layout
     *
     * @param Zend\Mvc\MvcEvent
     *
     * @return void
     */
    public function loadConfigLayout (MvcEvent $e) 
    {
        $app    = $e->getParam('application');
        $sm     = $app->getServiceManager();
        $config = $sm->get('config');

        $controller = $e->getTarget();
        $controllerClass = get_class($controller);
        $module          = substr($controllerClass, 0, strpos($controllerClass, '\\'));

        if (isset($config['module_layouts'][$module])) {
            $controller->layout($config['module_layouts'][$module]);
        }
        else {
            $controller->layout('layout/default');
        }
    }

    /**
     * Detect if admin
     *
     * @param Zend\Mvc\MvcEvent
     *
     * @return void
     */
    public function detectIfZfcAdminRoute (MvcEvent $e)
    {
        $app    = $e->getParam('application');
        $sm     = $app->getServiceManager();

        $match      = $e->getRouteMatch();
        $controller = $e->getTarget();
        if ($match 
            && (false !== strpos($match->getMatchedRouteName(), 'zfcadmin'))
        ) {
            self::$is_admin_route = true;
        }
    }
}
