<?php
namespace AstraChild\Models;

/**
 * Base Entity Model
 * 
 * Provides common functionality for data models
 */
abstract class EntityModel
{
    /**
     * Model data
     *
     * @var array
     */
    protected $data = [];
    
    /**
     * Get a data attribute
     *
     * @param string $key The attribute key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
    
    /**
     * Set a data attribute
     *
     * @param string $key The attribute key
     * @param mixed $value The value to set
     * @return self
     */
    public function setAttribute($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Check if attribute exists
     *
     * @param string $key
     * @return boolean
     */
    public function hasAttribute($key)
    {
        return isset($this->data[$key]);
    }
    
    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->data;
    }
    
    /**
     * Set multiple attributes at once
     *
     * @param array $attributes
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }
    
    /**
     * Determine if the model exists in the storage
     *
     * @return bool
     */
    abstract public function exists();
    
    /**
     * Save the model
     *
     * @return bool
     */
    abstract public function save();
}