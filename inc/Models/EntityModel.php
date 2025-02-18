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
    public function getAttribute(string $key, $default = null)
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
    public function setAttribute(string $key, $value): self
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
    public function hasAttribute(string $key): bool
    {
        return isset($this->data[$key]);
    }
    
    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAttributes(): array
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
     * Get only the specified attributes
     *
     * @param array $keys Attribute keys to get
     * @return array Filtered attributes
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Get all attributes except the specified ones
     *
     * @param array $keys Attribute keys to exclude
     * @return array Filtered attributes
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
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