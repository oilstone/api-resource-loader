<?php

namespace Oilstone\ApiResourceLoader\Models;

use Closure;
use Oilstone\ApiResourceLoader\Resources\Stitch as StitchResource;
use Stitch\DBAL\Schema\Table;
use Stitch\Model;

class Stitch extends Model
{
    /**
     * @var StitchResource
     */
    protected StitchResource $resource;

    /**
     * Get the value of resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Set the value of resource
     *
     * @return  self
     */
    public function setResource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * @param Closure $callback
     * @return Model
     */
    public static function make(Closure $callback)
    {
        $table = new Table();

        $callback($table);

        return new static($table);
    }
}
