<?php

namespace Netflex\Support;

trait Accessors
{
  use Timestamps;

  /** @var array */
  protected $attributes = [];

  /** @var array */
  public $modified = [];

  private function getterName($property)
  {
    return str_replace('_', '', 'get' . ucfirst(Str::toCamcelCase($property)) . 'Attribute');
  }

  private function setterName($property)
  {
    return str_replace('_', '', 'set' . ucfirst(Str::toCamcelCase($property)) . 'Attribute');
  }

  /**
   * Get the raw attributes
   *
   * @return array
   */
  public function toArray()
  {
    return $this->attributes;
  }

  /**
   * @param string $property
   * @return mixed
   */
  public function __get($property)
  {
    $value = $this->attributes[$property] ?? null;
    $getter = $this->getterName($property);

    if (method_exists($this, $getter)) {
      $value = $this->{$getter}($value);
    }

    $hasTimestamps = method_exists($this, 'isTimestamp');

    if (property_exists($this, 'defaults')) {
      if (is_null($value) && array_key_exists($property, $this->defaults)) {
        $value = $this->defaults[$property];
      }
    }

    if ($hasTimestamps && $this->isTimestamp($property)) {
      return $this->getTimestamp($value);
    }

    return $value;
  }

  /**
   * @param string $property
   * @param mixed $value
   */
  public function __set($property, $value)
  {
    if (property_exists($this, $property)) {
      return $this->{$property} = $value;
    }

    if (
      !property_exists($this, 'readOnlyAttributes') ||
      !in_array($property, $this->readOnlyAttributes)
    ) {
      $setter = $this->setterName($property);

      if (property_exists($this, 'timestamps') && in_array($property, $this->timestamps) && method_exists($this, 'setTimestamp')) {
        $value = $this->setTimestamp($value);
      }

      if (method_exists($this, $setter)) {
        return $this->{$setter}($value);
      }

      $this->attributes[$property] = $value;
      $this->modified[] = $property;
      $this->modified = array_unique($this->modified);

      if (method_exists($this, 'performHook')) {
        $this->performHook('modified');
      }
    }
  }

  /**
   * @param string $property
   * @return bool
   */
  public function __isset($property)
  {
    return !is_null($this->{$property});
  }

  /**
   * @param string $key
   */
  public function __unset($key)
  {
    if (array_key_exists($key, $this->attributes)) {
      $this->__set($key, null);
    }
  }

  /**
   * @param string $property
   * @return bool
   */
  public function offsetExists(mixed $property): bool
  {
    $getter = $this->getterName($property);

    return method_exists($this, $getter) || array_key_exists($property, $this->attributes);
  }

  /**
   * @param string $property
   * @return mixed
   */
  public function offsetGet(mixed $property): mixed
  {
    return $this->__get($property);
  }

  /**
   * @param string $property
   * @param mixed $value
   * @return mixed
   */
  public function offsetSet(mixed $property, mixed $value): void
  {
    $this->__set($property, $value);
  }

  /**
   * @param string $key
   * @return void
   */
  public function offsetUnset(mixed $key): void
  {
    $this->__unset($key);
  }

  /**
   * @return string
   */
  public function __toString()
  {
    return json_encode($this);
  }
}
