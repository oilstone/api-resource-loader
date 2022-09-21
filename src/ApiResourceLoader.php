<?php

namespace Oilstone\ApiResourceLoader;

use Api\Api;
use Api\Resources\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Oilstone\ApiResourceLoader\Resources\Resource;

/**
 * Class ApiResourceLoader
 * @package Oilstone\ApiResourceLoader
 */
class ApiResourceLoader
{
    /**
     * @var Api
     */
    protected Api $api;

    /**
     * @var string
     */
    protected string $schemaFactory = '';

    /**
     * @var string
     */
    protected string $modelFactory = '';

    /**
     * @var array
     */
    protected array $listeners = [];

    /**
     * @return ApiResourceLoader
     */
    public static function make(): ApiResourceLoader
    {
        return new static;
    }

    /**
     * @param Api $api
     * @return $this
     */
    public function api(Api $api): self
    {
        $this->api = $api;

        return $this;
    }

    /**
     * @param string $schemaFactory
     * @return $this
     */
    public function schemaFactory(string $schemaFactory): self
    {
        $this->schemaFactory = $schemaFactory;

        return $this;
    }

    /**
     * @param string $modelFactory
     * @return $this
     */
    public function modelFactory(string $modelFactory): self
    {
        $this->modelFactory = $modelFactory;

        return $this;
    }

    /**
     * @param string $modelFactory
     * @return $this
     */
    public function listeners(array $listeners): self
    {
        $this->listeners = $listeners;

        return $this;
    }

    /**
     * @param string $path
     * @param string $namespace
     * @return $this
     */
    public function loadResourcesFromPath(Application $app, string $path, string $namespace): self
    {
        $request = $this->api->getKernel()->resolve('request.instance');
        $sentinel = $this->api->getKernel()->resolve('guard.sentinel');

        foreach (glob($path) as $resourceName) {
            $className = Str::finish($namespace, '\\') . basename($resourceName, '.php');
            $resourceName = basename($resourceName, '.php') . 'Resource';

            if (is_subclass_of($className, Resource::class)) {
                $app->singleton($resourceName, function () use ($sentinel, $request, $className) {
                    return (new $className())
                        ->withSchemaFactory($this->schemaFactory)
                        ->withModelFactory($this->modelFactory)
                        ->withListeners($this->listeners)
                        ->withRequest($request ?: null)
                        ->withSentinel($sentinel ?: null);
                });

                $app->alias($resourceName, 'resources.' . $className::endpoint());

                if ($className::$autoload) {
                    $this->api->register($className::endpoint(), function (Factory $factory) use ($resourceName) {
                        return App::make($resourceName)->make($factory);
                    });
                }
            }
        }

        return $this;
    }
}
