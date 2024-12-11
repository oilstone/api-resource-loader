<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Transformers\Contracts\Transformer as TransformerContract;
use Api\Resources\Factory;
use Api\Resources\Resource as ApiResource;
use Api\Schema\Schema as BaseSchema;
use Api\Transformers\Transformer;
use Closure;
use Illuminate\Support\Str;
use Oilstone\ApiResourceLoader\Decorators\ResourceDecorator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Resource
 * @package App\Resources
 */
abstract class Resource
{
    public static bool $autoload = true;

    public static string $defaultEndpoint;

    protected bool $asSingleton = false;

    protected ?string $schema = null;

    protected ?string $repository = null;

    protected string $schemaFactory;

    protected ?ServerRequestInterface $request;

    protected ?Sentinel $sentinel;

    protected string $endpoint;

    /**
     * @var string[]|array[]
     */
    protected array $belongsTo = [];

    /**
     * @var string[]|array[]
     */
    protected array $hasMany = [];

    /**
     * @var string[]|array[]
     */
    protected array $hasOne = [];

    /**
     * @var string[]|array[]
     */
    protected array $nest = [];

    /**
     * @var string[]
     */
    protected array $only = [];

    /**
     * @var string[]
     */
    protected array $except = [];

    /**
     * @var array[]
     */
    protected array $listeners = [];

    /**
     * @var string[]
     */
    protected array $decorators = [];

    /**
     * @var string|null
     */
    protected ?string $modelFactory = null;

    /**
     * @var string|null
     */
    protected ?string $transformer = null;

    /**
     * @var array
     */
    protected array $cached = [];

    public function __construct()
    {
        foreach ($this->decorators as $decorator) {
            if (is_subclass_of($decorator, ResourceDecorator::class)) {
                (new $decorator)->decorate($this);
            }
        }
    }

    /**
     * @return string
     */
    public static function endpoint(): string
    {
        return static::$defaultEndpoint ?? Str::kebab(Str::pluralStudly(class_basename(static::class)));
    }

    /**
     * @param Factory $factory
     * @return ApiResource
     */
    public function make(Factory $factory): ApiResource
    {
        if (isset($this->cached['resource'])) {
            return $this->cached['resource'];
        }

        $schema = $this->makeSchema();
        $resource = $factory->{$this->asSingleton ? 'singleton' : 'collectable'}($schema, $this->makeRepository($this->sentinel ?? null), $this->makeTransformer($schema));

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

        $this->cached['resource'] = $resource;

        return $resource;
    }

    /**
     * @return BaseSchema
     */
    public function makeSchema(): BaseSchema
    {
        if (isset($this->cached['schema'])) {
            return $this->cached['schema'];
        }

        $schema = $this->schema ?? lcfirst(class_basename($this));

        if (isset($this->schemaFactory) && method_exists($this->schemaFactory, $schema)) {
            $schema = $this->schemaFactory::{$schema}();

            $this->cached['schema'] = $schema;

            return $schema;
        }

        $schema = $this->newSchemaObject();

        $this->schema($schema);

        foreach ($this->decorators as $decorator) {
            if (is_subclass_of($decorator, ResourceDecorator::class)) {
                (new $decorator)->decorateSchema($schema);
            }
        }

        $this->cached['schema'] = $schema;

        return $schema;
    }

    /**
     * @param BaseSchema $schema
     * @return void
     */
    protected function schema(BaseSchema $schema): void
    {
        //
    }

    /**
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function makeRepository(?Sentinel $sentinel = null, ...$params): ?RepositoryContract
    {
        if (isset($this->cached['repository'])) {
            return $this->cached['repository'];
        }

        if (isset($this->repository)) {
            $repositoryClass = $this->repository;
            $repository = new $repositoryClass(...$params);

            if (method_exists($repository, 'setSentinel')) {
                $repository->setSentinel($sentinel);
            }

            $this->cached['repository'] = $repository;

            return $repository;
        }

        $repository = $this->repository($sentinel, ...$params);

        $this->cached['repository'] = $repository;

        return $repository;
    }

    /**
     * @param null|Sentinel $sentinel
     * @return null|RepositoryContract
     */
    protected function repository(?Sentinel $sentinel): ?RepositoryContract
    {
        return null;
    }

    /**
     * @param BaseSchema $schema
     * @return TransformerContract
     */
    public function makeTransformer(BaseSchema $schema): ?TransformerContract
    {
        if (isset($this->cached['transformer'])) {
            return $this->cached['transformer'];
        }

        if (isset($this->transformer)) {
            $transformer = $this->transformer;

            $this->cached['transformer'] = $transformer;

            return new $transformer($schema);
        }

        $transformer = $this->transformer($schema);

        $this->cached['transformer'] = $transformer;

        return $transformer;
    }

    /**
     * @param BaseSchema $schema
     * @return TransformerContract
     */
    protected function transformer(BaseSchema $schema): ?TransformerContract
    {
        return new Transformer($schema);
    }

    /**
     * @param string $type
     * @param string|array $relation
     * @return array
     */
    protected function getRelation(string $type, string|array $relation): array
    {
        if (is_string($relation)) {
            $relation = [$relation];
        }

        if (isset($relation[1])) {
            return $relation;
        }

        $relationName = $relation[0];
        $method = $type . Str::studly($relationName);

        if (method_exists($this, $method)) {
            $relation[] = $this->{$method}();

            return $relation;
        }

        if ($type === 'belongsTo' || $type === 'hasOne') {
            $relation[] = function ($relation) use ($relationName) {
                $relation->bind(Str::plural($relationName));
            };

            return $relation;
        }

        if ($type === 'nest') {
            $relation[] = function ($relation) use ($relationName) {
                $relation->bind($relationName)->localKey('id')->foreignKey(Str::snake(Str::camel(Str::singular($this->getEndpoint()) . '_id')));
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
        return $this->setSchemaFactory($schemaFactory);
    }

    /**
     * @param string $modelFactory
     * @return $this
     */
    public function withModelFactory(string $modelFactory): self
    {
        return $this->setModelFactory($modelFactory);
    }

    /**
     * @param array $listeners
     * @return $this
     */
    public function withListeners(array $listeners): self
    {
        foreach ($listeners as $event => $eventListeners) {
            foreach ($eventListeners as $listener) {
                $this->addListener($event, $listener);
            }
        }

        return $this;
    }

    /**
     * @param ServerRequestInterface|null $request
     * @return $this
     */
    public function withRequest(?ServerRequestInterface $request): self
    {
        return $this->setRequest($request);
    }

    /**
     * @param Sentinel|null $sentinel
     * @return $this
     */
    public function withSentinel(?Sentinel $sentinel): self
    {
        return $this->setSentinel($sentinel);
    }

    /**
     * @return bool
     */
    public function getAsSingleton(): bool
    {
        return $this->asSingleton;
    }

    /**
     * @param bool $asSingleton
     * @return Resource
     */
    public function setAsSingleton(bool $asSingleton): Resource
    {
        $this->asSingleton = $asSingleton;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @param string|null $schema
     * @return Resource
     */
    public function setSchema(?string $schema): Resource
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRepository(): ?string
    {
        return $this->repository;
    }

    /**
     * @param string|null $repository
     * @return Resource
     */
    public function setRepository(?string $repository): Resource
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @return string
     */
    public function getSchemaFactory(): string
    {
        return $this->schemaFactory;
    }

    /**
     * @param string $schemaFactory
     * @return Resource
     */
    public function setSchemaFactory(string $schemaFactory): Resource
    {
        $this->schemaFactory = $schemaFactory;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getModelFactory(): ?string
    {
        return $this->modelFactory;
    }

    /**
     * @param string|null $modelFactory
     * @return Resource
     */
    public function setModelFactory(?string $modelFactory): Resource
    {
        $this->modelFactory = $modelFactory;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTransformer(): ?string
    {
        return $this->transformer;
    }

    /**
     * @param string|null $transformer
     * @return void
     */
    public function setTransformer(?string $transformer): void
    {
        $this->transformer = $transformer;
    }

    /**
     * @return ServerRequestInterface|null
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param ServerRequestInterface|null $request
     * @return Resource
     */
    public function setRequest(?ServerRequestInterface $request): Resource
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return Sentinel|null
     */
    public function getSentinel(): ?Sentinel
    {
        return $this->sentinel;
    }

    /**
     * @param Sentinel|null $sentinel
     * @return Resource
     */
    public function setSentinel(?Sentinel $sentinel): Resource
    {
        $this->sentinel = $sentinel;

        return $this;
    }

    /**
     * @param string $belongsTo
     * @param Closure|null $closure
     * @return Resource
     */
    public function addBelongsTo(string $belongsTo, ?Closure $closure = null): Resource
    {
        if (isset($closure)) {
            $belongsTo = [$belongsTo, $closure];
        }

        $this->belongsTo[] = $belongsTo;

        return $this;
    }

    /**
     * @return array
     */
    public function getBelongsTo(): array
    {
        return $this->belongsTo;
    }

    /**
     * @param string[]|array[] $belongsTo
     * @return Resource
     */
    public function setBelongsTo(array $belongsTo): Resource
    {
        $this->belongsTo = $belongsTo;

        return $this;
    }

    /**
     * @param string $hasMany
     * @param Closure|null $closure
     * @return Resource
     */
    public function addHasMany(string $hasMany, ?Closure $closure = null): Resource
    {
        if (isset($closure)) {
            $hasMany = [$hasMany, $closure];
        }

        $this->hasMany[] = $hasMany;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getHasMany(): array
    {
        return $this->hasMany;
    }

    /**
     * @param string[] $hasMany
     * @return Resource
     */
    public function setHasMany(array $hasMany): Resource
    {
        $this->hasMany = $hasMany;

        return $this;
    }

    /**
     * @param string $hasOne
     * @param Closure|null $closure
     * @return Resource
     */
    public function addHasOne(string $hasOne, ?Closure $closure = null): Resource
    {
        if (isset($closure)) {
            $hasOne = [$hasOne, $closure];
        }

        $this->hasOne[] = $hasOne;

        return $this;
    }

    /**
     * @return array
     */
    public function getHasOne(): array
    {
        return $this->hasOne;
    }

    /**
     * @param string[]|array[] $hasOne
     * @return Resource
     */
    public function setHasOne(array $hasOne): Resource
    {
        $this->hasOne = $hasOne;

        return $this;
    }

    /**
     * @param string $nest
     * @param Closure|null $closure
     * @return Resource
     */
    public function addNest(string $nest, ?Closure $closure = null): Resource
    {
        if (isset($closure)) {
            $nest = [$nest, $closure];
        }

        $this->nest[] = $nest;

        return $this;
    }

    /**
     * @return array
     */
    public function getNest(): array
    {
        return $this->nest;
    }

    /**
     * @param string[]|array[] $nest
     * @return Resource
     */
    public function setNest(array $nest): Resource
    {
        $this->nest = $nest;

        return $this;
    }

    /**
     * @param string $only
     * @return Resource
     */
    public function addOnly(string $only): Resource
    {
        $this->only[] = $only;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getOnly(): array
    {
        return $this->only;
    }

    /**
     * @param string[] $only
     * @return Resource
     */
    public function setOnly(array $only): Resource
    {
        $this->only = $only;

        return $this;
    }

    /**
     * @param string $except
     * @return Resource
     */
    public function addExcept(string $except): Resource
    {
        $this->except[] = $except;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getExcept(): array
    {
        return $this->except;
    }

    /**
     * @param string[] $except
     * @return Resource
     */
    public function setExcept(array $except): Resource
    {
        $this->except = $except;

        return $this;
    }

    /**
     * @param string $event
     * @param string $listener
     * @return Resource
     */
    public function addListener(string $event, string $listener): Resource
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;

        return $this;
    }

    /**
     * @return array[]
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * @param array[] $listeners
     * @return Resource
     */
    public function setListeners(array $listeners): Resource
    {
        $this->listeners = $listeners;

        return $this;
    }

    /**
     * @param string $decorator
     * @return Resource
     */
    public function addDecorator(string $decorator): Resource
    {
        $this->decorators[] = $decorator;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDecorators(): array
    {
        return $this->decorators;
    }

    /**
     * @param string[] $decorators
     * @return Resource
     */
    public function setDecorators(array $decorators): Resource
    {
        $this->decorators = $decorators;

        return $this;
    }

    /**
     * @param string $endpoint
     * @return Resource
     */
    public function setEndpoint(string $endpoint): Resource
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint ?? static::endpoint();
    }

    /**
     * @return BaseSchema
     */
    protected function newSchemaObject(): BaseSchema
    {
        return new BaseSchema();
    }
}
