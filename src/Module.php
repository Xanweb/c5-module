<?php
namespace Xanweb\Module;

use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Route;
use Concrete\Core\Foundation\ClassAliasList;
use Concrete\Core\Foundation\Service\ProviderList;

abstract class Module implements ModuleInterface
{
    /**
     * Class to be used Statically.
     */
    private function __construct()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::pkg()
     */
    public static function pkg()
    {
        $pkg = null;
        $app = Application::getFacadeApplication();
        $cache = $app->make('cache/request');
        $item = $cache->getItem(sprintf('/package/handle/%s', static::pkgHandle()));
        if (!$item->isMiss()) {
            $pkg = $item->get();
        } else {
            $pkg = $app->make('Concrete\Core\Package\PackageService')->getByHandle(static::pkgHandle());

            $cache->save($item->set($pkg));
        }

        return $pkg;
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::config()
     */
    public static function config()
    {
        return static::pkg()->getController()->getConfig();
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::fileConfig()
     */
    public static function fileConfig()
    {
        return static::pkg()->getController()->getFileConfig();
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::boot()
     */
    public static function boot()
    {
        $aliases = static::getClassAliases();
        if (!empty($aliases)) {
            $aliasList = ClassAliasList::getInstance();
            $aliasList->registerMultiple($aliases);
        }

        $app = self::app();
        $providers = static::getServiceProviders();
        if (is_array($providers) && !empty($providers)) {
            $app->make(ProviderList::class)->registerProviders($providers);
        }

        $routeListClasses = static::getRoutesClasses();
        if (is_array($routeListClasses) && !empty($routeListClasses)) {
            /**
             * @var \Concrete\Core\Routing\Router
             */
            $router = Route::getFacadeRoot();
            foreach ($routeListClasses as $routeListClass) {
                if (is_subclass_of($routeListClass, 'Concrete\Core\Routing\RouteListInterface')) {
                    $router->loadRouteList($app->build($routeListClass));
                } else {
                    throw new \Exception(t(get_called_class() . ':getRoutesClass: RoutesClass should be instanceof Concrete\Core\Routing\RouteListInterface'));
                }
            }
        }
    }

    /**
     * Classes to be registered as aliases in \Concrete\Core\Foundation\ClassAliasList.
     *
     * @return array
     */
    protected static function getClassAliases()
    {
        return [
            static::getPackageAlias() => get_called_class(),
        ];
    }

    /**
     * Get Package Alias.
     *
     * @return string
     */
    protected static function getPackageAlias()
    {
        return camelcase(static::pkgHandle());
    }

    /**
     * Get Service Providers Class Names.
     *
     * @return array
     */
    protected static function getServiceProviders()
    {
        return [];
    }

    /**
     * Get Classes names for RouteList, must be instance of \Concrete\Core\Routing\RouteListInterface.
     *
     * @return array
     */
    protected static function getRoutesClasses()
    {
        return [];
    }

    /**
     * @param string $make [optional]
     *
     * @return \Concrete\Core\Application\Application|object
     */
    protected static function app($make = null)
    {
        $app = Application::getFacadeApplication();

        if (!is_null($make)) {
            return $app->make($make);
        }

        return $app;
    }
}
