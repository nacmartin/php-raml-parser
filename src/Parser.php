<?php
namespace Raml;

use Symfony\Component\Yaml\Yaml;

class Parser
{
    /**
     * Array of cached files
     * No point in fetching them twice
     *
     * @var array
     */
    private $cachedFiles = [];

    // ---

    /**
     * @param $schemaParsers
     */
    public function __construct($schemaParsers = [])
    {
        $this->schemaParsers = [
          new \Raml\SchemaParser\JsonSchemaParser()
        ];
    }

    /**
     * Parse a RAML file
     *
     * @param string $fileName
     * @param boolean $parseSchemas
     *
     * @return \Raml\ApiDefinition
     */
    public function parse($fileName, $parseSchemas = true)
    {
        if (!is_file($fileName)) {
            throw new \Exception('File does not exist');
        }

        $rootDir = dirname(realpath($fileName));

        $array = $this->includeAndParseFiles(
            $this->parseYaml($fileName),
            $rootDir,
            $parseSchemas
        );

        if (!$array) {
            throw new \Exception('RAML file appears to be empty');
        }

        if (isset($array['traits'])) {
            $keyedTraits = [];
            foreach ($array['traits'] as $trait) {
                foreach ($trait as $k => $t) {
                    $keyedTraits[$k] = $t;
                }
            }

            foreach ($array as $key => $value) {
                if (strpos($key, '/') === 0) {
                    $name = (isset($value['displayName'])) ? $value['displayName'] : substr($key, 1);
                    $array[$key] = $this->replaceTraits($value, $keyedTraits, $key, $name);
                }
            }
        }

        // ---

        if (isset($array['resourceTypes'])) {
            $keyedTraits = [];
            foreach ($array['resourceTypes'] as $trait) {
                foreach ($trait as $k => $t) {
                    $keyedTraits[$k] = $t;
                }
            }

            foreach ($array as $key => $value) {
                if (strpos($key, '/') === 0) {
                    $name = (isset($value['displayName'])) ? $value['displayName'] : substr($key, 1);
                    $array[$key] = $this->replaceTypes($value, $keyedTraits, $key, $name);
                }
            }
        }

        // ---

        // parse any inline schemas
        if ($parseSchemas && $array) {
            $array = $this->arrayMapRecursive(
                function ($data) use ($rootDir) {
                    foreach ($this->schemaParsers as $schemaParser) {
                        if ($schemaParser->canParse($data)) {
                            return $schemaParser->parseString($data, $rootDir);
                        }
                    }

                    return $data;

                },
                $array
            );
        }

        return new ApiDefinition($array);
    }

    /**
     * Apply a callback to all elements of a recursive array
     *
     * @param callable $func
     * @param array $arr
     * @return array
     */
    private function arrayMapRecursive(callable $func, array $arr)
    {
        array_walk_recursive(
            $arr,
            function (&$v) use ($func) {
                $v = $func($v);
            }
        );

        return $arr;
    }

    /**
     * Convert a yaml file into a string
     *
     * @param string $fileName
     * @return array
     */
    private function parseYaml($fileName)
    {
        return Yaml::parse($fileName);
    }

    /**
     * Load and parse a file
     *
     * @throws \Exception
     *
     * @param string $fileName
     * @param string $rootDir
     * @return array|\stdClass
     */
    private function loadAndParseFile($fileName, $rootDir, $parseSchemas)
    {
        // cache based on file name, prevents including/parsing the same file multiple times
        if (isset($this->cachedFiles[$fileName])) {
            return $this->cachedFiles[$fileName];
        }

        $fullPath = $rootDir . '/' . $fileName;
        if (is_readable($fullPath) === false) {
            return false;
        }

        $fileExtension = (pathinfo($fileName, PATHINFO_EXTENSION));

        $fileData = null;

        if (in_array($fileExtension, ['yaml', 'yml', 'raml', 'rml'])) {
            // RAML and YAML files are always parsed
            $fileData = $this->includeAndParseFiles(
                $this->parseYaml($fullPath),
                dirname($fullPath),
                $parseSchemas
            );
        } elseif ($parseSchemas) {
            foreach($this->schemaParsers as $schemaParser) {
                if($schemaParser->canParseFile($fullPath)) {
                    $fileData = $schemaParser->parseFile($fullPath);
                    continue;
                }
            }

            if (!$fileData) {
                throw new \Exception('Extension "' . $fileExtension . '" not supported (yet)');
            }
        } else {
            // Or just include the string
            return file_get_contents($fullPath);
        }

        // cache before returning
        $this->cachedFiles[$fileName] = $fileData;
        return $fileData;
    }

    /**
     * Recurse through the structure and load includes
     *
     * @param array|string $structure
     * @param string $rootDir
     * @return array|\stdClass
     */
    private function includeAndParseFiles($structure, $rootDir, $parseSchemas)
    {
        if (is_array($structure)) {
            return array_map(
                function ($structure) use ($rootDir, $parseSchemas) {
                    return $this->includeAndParseFiles($structure, $rootDir, $parseSchemas);
                },
                $structure
            );
        } elseif (strpos($structure, '!include') === 0) {
            return $this->loadAndParseFile(str_replace('!include ', '', $structure), $rootDir, $parseSchemas);
        } else {
            return $structure;
        }
    }

    /**
     * Insert the traits into the RAML file
     *
     * @param array $raml
     * @param array $traits
     * @param string $path
     * @param string $name
     * @return array
     */
    private function replaceTraits($raml, $traits, $path, $name)
    {
        if (!is_array($raml)) {
            return $raml;
        }

        $newArray = [];

        foreach ($raml as $key => $value) {
            if ($key === 'is') {
                foreach ($value as $traitName) {
                    $trait = [];
                    if (is_array($traitName)) {
                        $traitVariables = current($traitName);
                        $traitName = key($traitName);

                        $traitVariables['resourcePath'] = $path;
                        $traitVariables['resourcePathName'] = $name;

                        $trait = $this->applyTraitVariables($traitVariables, $traits[$traitName]);
                    } elseif (isset($traits[$traitName])) {
                        $trait = $traits[$traitName];
                    }
                    $newArray = array_replace_recursive($newArray, $this->replaceTraits($trait, $traits, $path, $name));
                }
            } else {
                $newValue = $this->replaceTraits($value, $traits, $path, $name);

                if (isset($newArray[$key])) {
                    $newArray[$key] = array_replace_recursive($newArray[$key], $newValue);
                } else {
                    $newArray[$key] = $newValue;
                }
            }

        }
        return $newArray;
    }

    /**
     * Insert the types into the RAML file
     *
     * @param array $raml
     * @param array $types
     * @param string $path
     * @param string $name
     * @return array
     */
    private function replaceTypes($raml, $types, $path, $name)
    {
        if (!is_array($raml)) {
            return $raml;
        }

        $newArray = [];

        foreach ($raml as $key => $value) {
            if ($key === 'type') {
                $type = [];

                if (is_array($value)) {
                    $traitVariables = current($value);
                    $traitName = key($value);

                    $traitVariables['resourcePath'] = $path;
                    $traitVariables['resourcePathName'] = $name;

                    $type = $this->applyTraitVariables($traitVariables, $types[$traitName]);
                } elseif (isset($types[$value])) {
                    $type = $types[$value];
                }

                $newArray = array_replace_recursive($newArray, $this->replaceTypes($type, $types, $path, $name));
            } else {
                $newValue = $this->replaceTypes($value, $types, $path, $name);


                if (isset($newArray[$key])) {
                    $newArray[$key] = array_replace_recursive($newArray[$key], $newValue);
                } else {
                    $newArray[$key] = $newValue;
                }
            }

        }
        return $newArray;
    }

    /**
     * Add trait variables
     *
     * @param array $values
     * @param array $trait
     * @return mixed
     */
    private function applyTraitVariables(array $values, array $trait)
    {
        $jsonString = json_encode($trait, true);

        // replaces <<var>>
        foreach ($values as $key => $value) {
            $jsonString = str_replace('\u003C\u003C' . $key . '\u003E\u003E', $value, $jsonString);
        }

        return json_decode($jsonString, true);
    }
}
