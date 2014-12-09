<?php

namespace Raml\SchemaParser;

interface Parser
{
    public function canParse($schema);

    public function parseString($schema, $rootDir = null);

    public function parseFile($schema);
}
