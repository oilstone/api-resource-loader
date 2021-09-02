<?php

namespace Oilstone\ApiResourceLoader\Resources;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Repositories\Stitch\Resource as StitchRepository;
use Api\Schema\Stitch\Schema;
use Closure;
use Oilstone\ApiResourceLoader\Listeners\HandleSoftDeletes;
use Oilstone\ApiResourceLoader\Listeners\HandleTimestamps;
use Stitch\DBAL\Schema\Table;
use Stitch\Model;

class Stitch extends Resource
{
    /**
     * @var string[]|Closure[]
     */
    protected array $modelListeners = [];

    /**
     * @var string
     */
    protected string $modelFactory;

    /**
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * @var bool
     */
    protected bool $timestamps = true;

    /**
     * @var bool
     */
    protected bool $softDeletes = false;

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
     * @param Sentinel|null $sentinel
     * @return RepositoryContract|null
     */
    public function getRepository(?Sentinel $sentinel): ?RepositoryContract
    {
        return parent::getRepository($sentinel) ?? new StitchRepository($this->getModel());
    }

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        if (isset($this->modelFactory)) {
            $model = $this->model ?? lcfirst(class_basename($this));

            return $this->modelFactory::{$model}();
        }

        $model = \Stitch\Stitch::make(function (Table $table) {
            if (method_exists($this, 'model')) {
                $this->model($table);
            }

            if ($this->usesTimestamps()) {
                $table->timestamps();
            }

            if ($this->usesSoftDeletes()) {
                $table->softDeletes();
            }
        });

        if ($this->usesTimestamps()) {
            $model->listen(fn() => new HandleTimestamps());
        }

        if ($this->usesSoftDeletes()) {
            $model->listen(fn() => new HandleSoftDeletes());
        }

        foreach ($this->modelListeners as $modelListener) {
            $model->listen(is_string($modelListener) ? fn() => new $modelListener() : $modelListener);
        }

        return $model;
    }

    /**
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * @return bool
     */
    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        if (isset($this->schemaFactory)) {
            $schema = $this->schema ?? lcfirst(class_basename($this));

            return $this->schemaFactory::{$schema}();
        }

        $schema = new Schema($this->getModel()->getTable());

        if (method_exists($this, 'schema')) {
            $this->schema($schema);
        }

        return $schema;
    }
}
