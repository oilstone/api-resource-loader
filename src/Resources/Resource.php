<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Container as ApiContainer;
use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Resources\Factory;
use Api\Resources\Resource as ApiResource;
use Api\Schema\Schema as BaseSchema;
use Closure;
use Illuminate\Support\Str;

/**
 * Class Resource
 * @package App\Resources
 */
abstract class Resource
{
    /**
     * @var string
     */
    protected string $endpoint;

    /**
     * @var bool
     */
    protected bool $asSingleton = false;

    /**
     * @var string|null
     */
    protected ?string $schema = null;

    /**
     * @var string|null
     */
    protected ?string $repository = null;

    /**
     * @var string[]
     */
    protected array $belongsTo = [];

    /**
     * @var string[]
     */
    protected array $except = [];

    /**
     * @var string[]
     */
    protected array $hasMany = [];

    /**
     * @var string[]
     */
    protected array $hasOne = [];

    /**
     * @var string[]
     */
    protected array $nest = [];

    /**
     * @var string[]
     */
    protected array $only = [];

    /**
     * @var string
     */
    protected string $schemaFactory;

    /**
     * @return string
     */
    public function endpoint(): string
    {
        if (!isset($this->endpoint)) {
            return Str::kebab(Str::pluralStudly(class_basename($this)));
        }

        return $this->endpoint;
    }

    /**
     * @return Closure
     */
    public function make(): Closure
    {
        return function (Factory $factory, ApiContainer $container) {
            /** @var ApiResource $resource */
            $resource = $factory->{$this->asSingleton ? 'singleton' : 'collectable'}($this->schema(), $this->repository($container->get('guard.sentinel')));

            if ($this->only) {
                $resource->only(...$this->only);
            }

            if ($this->except) {
                $resource->except(...$this->only);
            }

            foreach (['belongsTo', 'hasMany', 'hasOne', 'nest'] as $type) {
                if ($this->{$type}) {
                    foreach ($this->{$type} as $relation) {
                        $resource->{$type}(...$this->getRelation($type, $relation));
                    }
                }
            }

            return $resource;
        };
    }

    /**
     * @return Closure
     */
    public function schema(): Closure
    {
        if ($this->schemaFactory) {
            $schema = isset($this->schema) ? $this->schema : lcfirst(class_basename($this));

            if (method_exists($this->schemaFactory, $schema)) {
                return $this->schemaFactory::{$schema}();
            }
        }

        return function (BaseSchema $schema) {
            //
        };
    }

    /**
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function repository(?Sentinel $sentinel): ?RepositoryContract
    {
        if (isset($this->repository)) {
            return new $this->repository($sentinel);
        }

        return null;
    }

    /**
     * @param string $type
     * @param string $relationName
     * @return array
     */
    protected function getRelation(string $type, string $relationName): array
    {
        $method = $type . Str::studly($relationName);
        $relation = [$relationName];

        if (method_exists($this, $method)) {
            $relation[] = $this->{$method}();
        } else if ($type === 'belongsTo') {
            $relation[] = function ($relation) use ($relationName) {
                $relation->bind(Str::plural($relationName));
            };
        }

        return $relation;
    }

    /**
     * @param string $schemaFactory
     * @return $this
     */
    public function withSchemaFactory(string $schemaFactory): self
    {
        $this->schemaFactory = $schemaFactory;

        return $this;
    }
}
