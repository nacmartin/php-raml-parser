<?php

namespace Raml\SchemaParser;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\RefResolver;

class JsonSchemaParser implements Parser
{
    public function canParseFile($filePath)
    {
        $fileExtension = (pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($fileExtension, ['json']);
    }

    public function canParse($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function parseString($schemaString, $rootDir = null)
    {
        $retriever = new UriRetriever;
        $jsonSchemaParser = new RefResolver($retriever);

        $data = json_decode($schemaString);
        $jsonSchemaParser->resolve($data, 'file:' . $rootDir . '/');

        return $data;
    }

    public function parseFile($fileName)
    {
        $retriever = new UriRetriever;
        $jsonSchemaParser = new RefResolver($retriever);
        try {
            return $jsonSchemaParser->fetchRef('file://' . $fileName, null);
        } catch (\Exception $e) {
            throw new \Exception('Invalid JSON in ' . $fileName);
        }
    }
}
