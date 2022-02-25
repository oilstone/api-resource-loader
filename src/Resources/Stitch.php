<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Repositories\Stitch\Resource as StitchRepository;
use Api\Schema\Stitch\Schema;
use Closure;
use Oilstone\ApiResourceLoader\Decorators\ResourceDecorator;
use Oilstone\ApiResourceLoader\Decorators\StitchDecorator;
use Oilstone\ApiResourceLoader\Listeners\HandleSoftDeletes;
use Oilstone\ApiResourceLoader\Listeners\HandleTimestamps;
use Oilstone\ApiResourceLoader\Models\Stitch as StitchModel;
use Stitch\DBAL\Schema\Table;
use Stitch\Model;

class Stitch extends Resource
{
    protected string $modelFactory;

    protected ?string $model = null;

    protected bool $timestamps = true;

    protected bool $softDeletes = false;

    /**
     * @var string[]|Closure[]
     */
    protected array $modelListeners = [];

    /**
     * @param string $modelFactory
     * @return $this
     */
    public function withModelFactory(string $modelFactory): self
    {
        return $this->setModelFactory($modelFactory);
    }

    /**
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function makeRepository(?Sentinel $sentinel): ?RepositoryContract
    {
        return parent::makeRepository($sentinel) ?? new StitchRepository($this->makeModel());
    }

    /**
     * @param bool $withListeners
     * @return Model
     * @noinspection PhpUndefinedMethodInspection
     */
    public function makeModel(bool $withListeners = true): Model
    {
        $model = $this->model ?? lcfirst(class_basename($this));

        if (isset($this->modelFactory) && method_exists($this->modelFactory, $model)) {
            return $this->modelFactory::{$model}();
        }

        $model = StitchModel::make(function (Table $table) {
            $this->model($table);

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
        });

        if ($withListeners) {
            if ($this->usesTimestamps()) {
                $model->listen(fn() => new HandleTimestamps());
            }

            if ($this->usesSoftDeletes()) {
                $model->listen(fn() => new HandleSoftDeletes());
            }

            foreach ($this->modelListeners as $modelListener) {
                $model->listen(is_string($modelListener) ? fn() => new $modelListener() : $modelListener);
            }
        }

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
     * @param string $modelFactory
     * @return Stitch
     */
    public function setModelFactory(string $modelFactory): Stitch
    {
        $this->modelFactory = $modelFactory;

        return $this;
    }

    /**
     * @return string
     */
    public function getModelFactory(): string
    {
        return $this->modelFactory;
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
        return new Schema($this->makeModel()->getTable());
    }
}
