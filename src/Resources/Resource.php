<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Resources\Factory;
use Api\Resources\Resource as ApiResource;
use Api\Schema\Schema as BaseSchema;
use Closure;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Resource
 * @package App\Resources
 */
abstract class Resource
{
    /**
     * @var string
     */
    protected static string $endpoint;

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
     * @var array[]
     */
    protected array $listeners = [];

    /**
     * @var string
     */
    protected string $schemaFactory;

    /**
     * @var ServerRequestInterface|null
     */
    protected ?ServerRequestInterface $request;

    /**
     * @var Sentinel|null
     */
    protected ?Sentinel $sentinel;

    /**
     * @return string
     */
    public static function endpoint(): string
    {
        if (!isset(static::$endpoint)) {
            return Str::kebab(Str::pluralStudly(class_basename(static::class)));
        }

        return static::$endpoint;
    }

    /**
     * @param Factory $factory
     * @return ApiResource
     */
    public function make(Factory $factory): ApiResource
    {
        /** @var ApiResource $resource */
        $resource = $factory->{$this->asSingleton ? 'singleton' : 'collectable'}($this->getSchema(), $this->getRepository($this->sentinel));

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

        if ($this->listeners) {
            foreach ($this->listeners as $event => $eventListeners) {
                foreach ($eventListeners as $eventListener) {
                    $resource->listen($event, $eventListener);
                }
            }
        }

        return $resource;
    }

    /**
     * @return Closure
     */
    public function getSchema(): Closure
    {
        if (isset($this->schemaFactory)) {
            $schema = $this->schema ?? lcfirst(class_basename($this));

            if (method_exists($this->schemaFactory, $schema)) {
                return $this->schemaFactory::{$schema}();
            }
        }

        return function (BaseSchema $schema) {
            if (method_exists($this, 'schema')) {
                $this->schema($schema);
            }
        };
    }

    /**
     * @param BaseSchema $schema
     */
    public function schema(BaseSchema $schema): void
    {
        //
    }

    /**
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function getRepository(?Sentinel $sentinel): ?RepositoryContract
    {
        if (isset($this->repository)) {
            return new $this->repository($sentinel);
        }

        if (method_exists($this, 'repository')) {
            return $this->repository();
        }

        return null;
    }

    /**
     * @return RepositoryContract|null
     */
    public function repository(): ?RepositoryContract
    {
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

    /**
     * @param ServerRequestInterface|null $request
     * @return $this
     */
    public function withRequest(?ServerRequestInterface $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param Sentinel|null $sentinel
     * @return $this
     */
    public function withSentinel(?Sentinel $sentinel): self
    {
        $this->sentinel = $sentinel;

        return $this;
    }
}
