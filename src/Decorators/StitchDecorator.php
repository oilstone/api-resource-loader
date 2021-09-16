<?php

namespace Oilstone\ApiResourceLoader\Decorators;

use Stitch\DBAL\Schema\Table;

abstract class StitchDecorator extends ResourceDecorator
{
    /**
     * @param Table $table
     * @return void
     */
    public function decorateModel(Table $table): void
    {
        //
    }
}
