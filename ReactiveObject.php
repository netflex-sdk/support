<?php

namespace Netflex\Support;

use ArrayAccess;
use JsonSerializable;
use ReflectionClass;

use Illuminate\Support\Carbon;

abstract class ReactiveObject implements ArrayAccess, JsonSerializable
{
  use Hooks;
  use Events;
  use Accessors;

  /** @var ReactiveObject|ItemCollection|null */
  public $parent = null;

  /** @var array */
  protected $defaults = [];

  /** @var array */
  protected $readOnlyAttributes = ['id'];

  /**
   * @param object|array $attributes = []
   * @param object|null $parent = null
   */
  public function __construct(
    $attributes = [],
    $parent = null,
    $markAttributesAsModified = false,
  ) {
    $this->parent = $parent;

    if (is_object($attributes) || is_array($attributes)) {
      foreach ($attributes as $property => $value) {
        $this->attributes[$property] = $value;
      }
    }

    if (property_exists($this, 'defaults') && is_array($this->defaults)) {
      foreach ($this->defaults as $property => $value) {
        if (!($this->attributes[$property] ?? null)) {
          $this->attributes[$property] = $this->value;
        }
      }
    }

    if ($markAttributesAsModified) {
      array_push($this->modified, ...array_keys($attributes));
      $this->modified = array_unique($this->modified);
    }
  }

  /**
   * @param ReactiveObject|ItemCollection|null $parent
   * @return static
   */
  public function setParent($parent)
  {
    $this->parent = $parent;
    return $this;
  }

  /**
   * @return ReactiveObject|ItemCollection|null
   */
  public function getParent()
  {
    return $this->parent;
  }

  /**
   * @return ReactiveObject|ItemCollection|null
   */
  public function getRootParent()
  {
    $parent = $this->parent;

    while (!empty($parent->parent)) {
      $parent = $parent->parent;
    }

    return $parent;
  }

  /**
   * @param string|int $id
   * @return int
   */
  public function getIdAttribute($id = null)
  {
    return $id ? (int) $id : $id;
  }

  /**
   * @return array
   */
  public function __debugInfo()
  {
    $debug = [];

    foreach (array_keys($this->attributes) as $property) {
      $debug[$property] = $this->__get($property);
    }

    return $debug;
  }

  /**
   * @param object|array $attributes = []
   * @param object|null $parent = null
   * @return static
   */
  public static function factory($attributes = [], $parent = null)
  {
    return new static($attributes, $parent);
  }

  /**
   * @return array
   */
  #[\ReturnTypeWillChange]
  public function jsonSerialize()
  {
    $json = [];

    foreach (array_keys($this->attributes) as $property) {
      $value = $this->__get($property);

      if ($value instanceof ItemCollection) {
        $value = $value->jsonSerialize();
      }

      if ($value instanceof ReactiveObject) {
        $value = $value->jsonSerialize();
      }

      if (($value instanceof Carbon) && property_exists($this, 'timestamps') && in_array($property, $this->timestamps)) {
        $value = $this->serializeTimestamp($value);
      }

      $json[$property] = $value;
    }

    return $json;
  }

  public function toModifiedArray(): array
  {
    $array = [];

    if ($this->__isset('id')) {
      $array['id'] = $this->__get('id');
    }

    foreach ($this->modified as $property) {
      $value = $this->__get($property);

      if ($value instanceof ItemCollection) {
        $value = $value->toModifiedArray();
      }

      if ($value instanceof ReactiveObject) {
        $value = $value->toModifiedArray();
      }

      if (
        ($value instanceof Carbon)
        && property_exists($this, 'timestamps')
        && in_array($property, $this->timestamps)
      ) {
        $value = $this->serializeTimestamp($value);
      }

      $array[$property] = $value;
    }

    return $array;
  }

  public static function hasTrait($trait)
  {
    return in_array(
      $trait,
      array_keys((new ReflectionClass(self::class))->getTraits())
    );
  }

  public function __serialize(): array
  {
    return [
      'parent' => $this->parent,
      'attributes' => $this->attributes ?? []
    ];
  }

  public function __unserialize(array $data)
  {
    $this->parent = $data['parent'] ?? null;
    $this->attributes = $data['attributes'] ?? [];
  }
}
