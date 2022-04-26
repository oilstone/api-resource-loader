<?php

namespace Oilstone\ApiResourceLoader\Transformers;

use Api\Result\Contracts\Record;
use Api\Transformers\Contracts\Transformer as Contract;

class Transformer implements Contract
{
    protected $schema;

    public function __construct($schema)
    {
        $this->schema = $schema;
    }

    /**
     * @param Record $record
     * @return array
     */
    public function transform(Record $record): array
    {
        return $record->getAttributes();
    }
}
