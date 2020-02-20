<?php
namespace Xanweb\Module;

use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Route;
use Concrete\Core\Foundation\ClassAliasList;
use Concrete\Core\Foundation\Service\ProviderList;

/**
 * @method static \Concrete\Core\Config\Repository\Liaison getConfig()
 * @method static \Concrete\Core\Config\Repository\Liaison getFileConfig()
 * @method static string getPackagePath()
 * @method static string getRelativePath()
 *
 * @see \Concrete\Core\Package\Package
 */
abstract class Module implements ModuleInterface
{
    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static $resolvedPackInstance;

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
        $pkgHandle = static::pkgHandle();
        if (!isset(static::$resolvedPackInstance[$pkgHandle])) {
            static::$resolvedPackInstance[$pkgHandle] = self::app('Concrete\Core\Package\PackageService')->getByHandle($pkgHandle);
        }

        return static::$resolvedPackInstance[$pkgHandle];
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

    /**
     * Handle dynamic, static calls to the controller.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $pkg = static::pkg();

        if (!$pkg) {
            throw new \RuntimeException(t('Package Not Found.'));
        }

        return $pkg->getController()->$method(...$args);
    }
}
