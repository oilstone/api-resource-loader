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
use Stitch\Model;

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
    protected ?string $model = null;

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
     * @var string
     */
    protected string $modelFactory;

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
        $resource = $factory->{$this->asSingleton ? 'singleton' : 'collectable'}($this->schema(), $this->repository($this->sentinel));

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
    }

    /**
     * @return Closure
     */
    public function schema(): Closure
    {
        if (isset($this->schemaFactory)) {
            $schema = $this->schema ?? lcfirst(class_basename($this));

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
     * @return Model|null
     */
    public function model(): ?Model
    {
        if (isset($this->modelFactory)) {
            $model = $this->model ?? lcfirst(class_basename($this));

            if (method_exists($this->modelFactory, $model)) {
                return $this->modelFactory::{$model}();
            }
        }

        return null;
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
     * @param string $modelFactory
     * @return $this
     */
    public function withModelFactory(string $modelFactory): self
    {
        $this->modelFactory = $modelFactory;

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
