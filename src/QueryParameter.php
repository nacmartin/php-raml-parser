<?php
namespace Raml;

class QueryParameter
{
    private $validTypes = ['string', 'number', 'integer', 'date', 'boolean', 'file'];

    // ---

    /**
     * @var string
     */
    private $displayName;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $example;

    /**
     * @var boolean
     */
    private $required = false;

    // ---

    /**
     * @param string $description
     * @param string $type
     */
    public function __construct($displayName = null, $description = null, $type = 'string', $example = null, $required = false)
    {
        if(!in_array($type, $this->validTypes)) {
            throw new \Exception('"'.$type.'" is not a valid type');
        }

        $this->displayName = $displayName;
        $this->description = $description;
        $this->type = $type;
        $this->example = $example;
        $this->required = (bool) $required;
    }

    // ---

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getExample()
    {
        return $this->example;
    }

    /**
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }
}
