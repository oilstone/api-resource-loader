<?php

namespace Oilstone\ApiResourceLoader\Contracts;

use Oilstone\ApiResourceLoader\Resources\Resource;

interface ResourceDecorator
{
    /**
     * @param Resource $resource
     * @return void
     */
    public function decorate(Resource $resource): void;
}
