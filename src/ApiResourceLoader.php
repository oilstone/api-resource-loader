<?php

namespace Oilstone\ApiResourceLoader;

use Api\Api;
use Illuminate\Support\Str;
use Oilstone\ApiResourceLoader\Resources\Resource;
use Psr\Http\Message\ServerRequestInterface;

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
     * @var ServerRequestInterface|null
     */
    protected ?ServerRequestInterface $request = null;

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
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function request(ServerRequestInterface $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param string $path
     * @param string|null $namespace
     * @return $this
     */
    public function loadResourcesFromPath(string $path, string $namespace): self
    {
        foreach (glob($path) as $resourceName) {
            $className = Str::finish($namespace, '\\') . basename($resourceName, '.php');

            if (is_subclass_of($className, Resource::class)) {
                /** @var Resource $resource */
                $resource = new $className();

                $this->api->register($resource->endpoint(), $resource->withSchemaFactory($this->schemaFactory)->withRequest($this->request)->make());
            }
        }

        return $this;
    }
}
