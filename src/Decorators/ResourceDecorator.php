<?php

namespace Oilstone\ApiResourceLoader\Decorators;

use Api\Schema\Schema;
use Oilstone\ApiResourceLoader\Resources\Resource;

abstract class ResourceDecorator
{
    /**
     * @param Resource $resource
     * @return void
     */
    abstract public function decorate(Resource $resource): void;

    /**
     * @param Schema $schema
     * @return void
     */
    public function decorateSchema(Schema $schema): void
    {
        //
    }
}
