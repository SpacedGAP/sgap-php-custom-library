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

        $dir = dirname($class_info->getFileName());
        echo $dir."  ".dirname($dir); exit;
       
        if (strstr($dir, "src/{$this->namespace}")) {
            $dir = dirname("{$dir}/../..");
        }
        $this->dir = $dir;
    }

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array($this, 'loadConfigLayout'));

        // TODO:: Detect if ZfcModule is added
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array($this, 'detectIfZfcAdminRoute'));
    }

    public function getConfig()
    {
        // Required to be defined value used in include config files.
        $module     = $this->namespace;
        $module_dir = $this->dir;
        $config     = include "{$module_dir}/config/module.config.php";

        if (defined(MY_LIBRARY_PATH) 
            and file_exists(MY_LIBRARY_PATH.'/config/module.common.config.php')) 
        {
            $common_config = include MY_LIBRARY_PATH.'/config/module.common.config.php';

            $cc_object = new \Zend\Config\Config($common_config);
            $c_object  = new \Zend\Config\Config($config);

            return $c_object->merge($cc_object)->toArray();           
        }

        return $config;
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
