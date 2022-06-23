<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Repositories\Stitch\Resource as StitchRepository;
use Api\Schema\Schema as BaseSchema;
use Api\Schema\Stitch\Schema;
use Api\Transformers\Contracts\Transformer as TransformerContract;
use Api\Transformers\Stitch\Transformer;
use Closure;
use Oilstone\ApiResourceLoader\Decorators\StitchDecorator;
use Oilstone\ApiResourceLoader\Listeners\HandleSoftDeletes;
use Oilstone\ApiResourceLoader\Listeners\HandleTimestamps;
use Oilstone\ApiResourceLoader\Models\Stitch as StitchModel;
use Stitch\DBAL\Schema\Table;
use Stitch\Model;

class Stitch extends Resource
{
    protected ?string $model = null;

    protected bool $timestamps = true;

    protected bool $softDeletes = false;

    /**
     * @var string[]|Closure[]
     */
    protected array $modelListeners = [];

    /**
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function makeRepository(?Sentinel $sentinel = null): ?RepositoryContract
    {
        if (isset($this->cached['repository'])) {
            return $this->cached['repository'];
        }

        $repository = parent::makeRepository($sentinel) ?? new StitchRepository($this->makeModel());

        $this->cached['repository'] = $repository;

        return $repository;
    }

    /**
     * @param bool $withListeners
     * @return Model
     * @noinspection PhpUndefinedMethodInspection
     */
    public function makeModel(bool $withListeners = true): Model
    {
        if (isset($this->cached['model'])) {
            return $this->cached['model'];
        }

        $model = $this->model ?? lcfirst(class_basename($this));

        if (isset($this->modelFactory) && method_exists($this->modelFactory, $model)) {
            $model = $this->modelFactory::{$model}();

            $this->cached['model'] = $model;

            return $model;
        }

        $schema = $this->makeSchema();
        $table = $schema->getTable();

        if ($this->usesTimestamps()) {
            $table->timestamps();
        }

        if ($this->usesSoftDeletes()) {
            $table->timestamp('deleted_at');
        }

        foreach ($this->decorators as $decorator) {
            if (is_subclass_of($decorator, StitchDecorator::class)) {
                (new $decorator)->decorateModel($table);
            }
        }

        $this->model($table);

        $model = StitchModel::make($table)->setResource($this);

        if ($withListeners) {
            if ($this->usesTimestamps()) {
                $model->listen(fn () => new HandleTimestamps());
            }

            if ($this->usesSoftDeletes()) {
                $model->listen(fn () => new HandleSoftDeletes());
            }

            foreach ($this->modelListeners as $modelListener) {
                $model->listen(is_string($modelListener) ? fn () => new $modelListener() : $modelListener);
            }
        }

        $this->cached['model'] = $model;

        return $model;
    }

    /**
     * @param Table $table
     * @return void
     */
    protected function model(Table $table): void
    {
        //
    }

    /**
     * @param Schema $schema
     * @return TransformerContract
     */
    protected function transformer(BaseSchema $schema): ?TransformerContract
    {
        return new Transformer($schema);
    }

    /**
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->getTimestamps();
    }

    /**
     * @return bool
     */
    public function usesSoftDeletes(): bool
    {
        return $this->getSoftDeletes();
    }

    /**
     * @param string|null $model
     * @return Stitch
     */
    public function setModel(?string $model): Stitch
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @param bool $timestamps
     * @return Stitch
     */
    public function setTimestamps(bool $timestamps): Stitch
    {
        $this->timestamps = $timestamps;

        return $this;
    }

    /**
     * @return bool
     */
    public function getTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * @param bool $softDeletes
     * @return Stitch
     */
    public function setSoftDeletes(bool $softDeletes): Stitch
    {
        $this->softDeletes = $softDeletes;

        return $this;
    }

    /**
     * @return bool
     */
    public function getSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    /**
     * @param string|Closure $modelListener
     * @return Stitch
     */
    public function addModelListener(string|Closure $modelListener): Stitch
    {
        $this->modelListeners[] = $modelListener;

        return $this;
    }

    /**
     * @param Closure[]|string[] $modelListeners
     * @return Stitch
     */
    public function setModelListeners(array $modelListeners): Stitch
    {
        $this->modelListeners = $modelListeners;

        return $this;
    }

    /**
     * @return array
     */
    public function getModelListeners(): array
    {
        return $this->modelListeners;
    }

    /**
     * @return Schema
     */
    protected function newSchemaObject(): Schema
    {
        return new Schema();
    }
}
