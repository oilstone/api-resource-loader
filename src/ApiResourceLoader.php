<?php

namespace Oilstone\ApiResourceLoader;

use Api\Api;
use Api\Resources\Factory;
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
     * @param string $path
     * @param string $namespace
     * @return $this
     */
    public function loadResourcesFromPath(string $path, string $namespace): self
    {
        $request = $this->api->getKernel()->resolve('request.instance');
        $sentinel = $this->api->getKernel()->resolve('guard.sentinel');

        foreach (glob($path) as $resourceName) {
            $className = Str::finish($namespace, '\\') . basename($resourceName, '.php');
            $resourceName = basename($resourceName, '.php') . 'Resource';

            if (is_subclass_of($className, Resource::class)) {
                app()->singleton($resourceName, function () use ($sentinel, $request, $className) {
                    return (new $className())
                        ->withSchemaFactory($this->schemaFactory)
                        ->withRequest($request)
                        ->withSentinel($sentinel);
                });

                if ($className::$autoload) {
                    $this->api->register($className::endpoint(), function (Factory $factory) use ($resourceName) {
                        return app($resourceName)->make($factory);
                    });
                }
            }
        }

        return $this;
    }
}
