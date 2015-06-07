<?php

namespace MajorCaiger\DoctrineEntityGenerator;

use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

/**
 * Module
 */
class Module implements AutoloaderProviderInterface, ConsoleBannerProviderInterface, ConsoleUsageProviderInterface
{
    public function getConsoleBanner(AdapterInterface $console)
    {
        return 'Doctrine Entity Generator Module';
    }

    public function getConsoleUsage(AdapterInterface $console)
    {
        return array(
            'generate' => 'Generate entities'
        );
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/DoctrineEntityGenerator',
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        // You may not need to do this if you're doing it elsewhere in your
        // application
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }
}
