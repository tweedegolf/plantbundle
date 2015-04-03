<?php

namespace TweedeGolf\PlantBundle\Retriever;

use BadFunctionCallException;
use DateTime;
use UnexpectedValueException;

/**
 * PlantProxy
 *
 * This class is a simpler reflection of the plant entity, containing a copy of all the properties in a way
 * that is easier to use in controllers and templates. 
 */
class PlantProxy {

    /**
     * @var
     */
    private $id;

    /**
     * @var array Array with key values pairs "property name": "property value" (array)
     */
    private $values = [];

    /**
     * Holds the old or new source
     */
    private $source;

    /**
     * @var DateTime
     */
    private $createdAt;

    /**
     * @var DateTime
     */
    private $updatedAt;

    /**
     * All possible valid types
     */
    private $valid_types = [
        'radio',
        'check',
        'text',
        'images',
        'bool',
        'lines',
        'string'
    ];

    /**
     * @param $id
     */
    public function __construct($id = null)
    {
        $this->id = $id;
        $this->values['names']['values'] = [];
        $this->values['names']['type'] = 'lines';
    }

    public function setSource($source)
    {
        $this->source = $source;
    }
    
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set the value of $property
     * 
     * @param string $property
     * @param array $values
     * @param bool $overwrite - whether to overwrite the value 
     */
    public function set($property, $values, $overwrite = true, $type = null)
    {
        if (array_key_exists($property, $this->values)) {
            if ($overwrite) {
                $this->values[$property]['values'] = $values;
            } else {
                if (!is_array($values)) {
                    $values = [$values];
                }
                foreach ($values as $v) {
                    $this->values[$property]['values'][] = $v;
                }
            }
        } else {
            $this->values[$property]['values'] = $values;
        }

        if ($type !== null) {
            if (in_array($type, $this->valid_types)) {
                $this->setPropertyType($property, $type);
            } else{
                throw new \Exception("Given {$type} is not a valid type.");
            }
        }
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Return the value of $property
     *
     * @param string The property name
     * @return array 
     */
    public function get($property)
    {
        if (array_key_exists($property, $this->values)) {
            return $this->values[$property]['values'];
        } 

        return [];
    }

    /**
     * Helper hat resturns property values as string (often there is only one value)
     * @param $property
     * @return string
     */
    public function getAsString($property)
    {
        $result = '';

        if (array_key_exists($property, $this->values)) {
            $values = $this->values[$property]['values'];

            $i = 0;
            foreach($values as $v) {
                $result .= ($i > 0 ? ' ' . $v : $v);
                $i += 1;
            }

            return $result;
        }

        return $result;
    }

    /**
     * @param $property
     * @return array
     */
    public function remove($property)
    {
        if (array_key_exists($property, $this->values)) {
            unset($this->values[$property]);
            return $this->values;
        }

        throw new BadFunctionCallException(sprintf('The property %s could not be removed because it does not exist.', $property));
    }

    /**
     * @param $property
     * @return bool
     */
    public function has($property)
    {
        return array_key_exists($property, $this->values);
    }

    /**
     * Get an array of existing properties for this plant
     *
     * @return array
     */
    public function getAllProperties()
    {
        return array_keys($this->values);
    }

    /**
     * To String method
     *
     * @return string
     */
    public function __toString()
    {
        return "Unnamed plant #".$this->id;
    }

    /**
     * Reset the values in the proxy
     */
    public function resetValues()
    {
        $this->values = [];
        $this->values['names']['values'] = [];
        $this->values['names']['type'] = 'lines';
    }

    /**
     * A PlantProxy has getters for all properties that a plant can have. If this plant has a value for
     * the given property, then that value is returned as array. Otherwise an empty array is returned
     *
     * @param string The property name
     * @return array
     */
    public function __get($property)
    {
        if (array_key_exists($property, $this->values)) {
            $values = $this->values[$property]['values'];

            // extra check so that always an array is returned
            if (!is_array($values)) {
                return [$values];
            }

            return $values;
        } else {
            return [];
        }
    }

    /**
     * @param $property
     * @param $value
     */
    public function __set($property, $value)
    {
        $this->values[$property]['values'] = array_values($value);
    }

    /**
     * Return current choices for in form for a property with multiple values
     *
     * @param $property
     * @return array
     */
    public function getCurrentChoices($property)
    {
        if (!$this->has($property)){
            return [];
        }

        $values = $this->get($property);

        $data = [];

        foreach($values as $v) {
            $data[$v] = $v;
        }

        return $data;
    }

    /**
     * Return current choice for in form for a property with single value
     *
     * @param $property
     * @return array
     */
    public function getCurrentChoice($property)
    {
        if (!$this->has($property)){
            return '';
        }

        $values = $this->get($property);

        return $values[0];
    }

    /**
     * @param $names
     */
    public function addName($name)
    {
        if (!isset($this->values['names'])) {
            $this->values['names'] = [];
        }

        if (!in_array($name, $this->values['names']['values'])) {
            $this->values['names']['values'][] = $name;
        }
    }

    /**
     * @param $names
     */
    public function setNames($names)
    {
        $this->values['names']['values'] = $names;
    }

    /**
     * @return mixed
     */
    public function getNames()
    {
        return $this->values['names']['values'];
    }

    /**
     * Get the type for the given field 
     * 
     * @param string
     * @return string
     */
    public function getPropertyType($property)
    {
        if (array_key_exists($property, $this->values)) {
            return $this->values[$property]['type'];
        }

        return null;
    }

    /**
     * Set the type for the given property 
     *
     * @param string
     * @param string
     */
    public function setPropertyType($property, $type)
    {
        if (in_array($type, $this->valid_types)) {
            $this->values[$property]['type'] = $type;
        } else {
            throw new \Exception("Given {$type} is not a valid type.");
        }
    }

    /**
     * Get the first name. Returns empty string if no names are set
     *
     * @return string
     */
    public function getName()
    {
        if (isset($this->values['names']) && count($this->values['names']['values']) > 0) {
            return $this->values['names']['values'][0];
        }

        return '';
    }

    /**
     * Get the first image (url)
     *
     * @return string || null
     */
    public function getImage()
    {
        if (isset($this->values['images']) && count($this->values['images']['values']) > 0) {
            return $this->values['images']['values'][0];
        }

        return null;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Checks if this plants spreads quickly
     *
     * @return bool
     */
    public function spreads()
    {
        return in_array('spreading out', $this->get('growth_habit'));
    }
}
