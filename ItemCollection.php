<?php

namespace Netflex\Support;

use JsonSerializable;
use Illuminate\Support\Collection as BaseCollection;

abstract class ItemCollection extends BaseCollection implements JsonSerializable
{
  use Hooks;

  /** @var ReactiveObject|ItemCollection|null */
  public $parent = null;

  /** @var string */
  protected static $type = ReactiveObject::class;

  /**
   * @param array|null $items = []
   */
  public function __construct($items = [], $parent = null)
  {
    $this->parent = $parent;

    if ($items) {
      parent::__construct(array_map(function ($item) {
        if (!($item instanceof static::$type)) {
          $item = static::$type::factory($item);
        }

        $item->setParent($this);

        return $item->addHook('modified', function ($_) {
          $this->performHook('modified');
        });
      }, $items));
    }
  }

  /**
   * @param array|null $items = []
   * @return static
   */
  public static function factory($items = [], $parent = null)
  {
    return new static($items, $parent);
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
   * Get and remove the last N items from the collection.
   *
   * @param int $count
   * @return mixed
   */
  public function pop($count = 1)
  {
    $modifies = true;

    if ($this->isEmpty()) {
      $modifies = false;
    }

    $result = parent::pop($count);

    if ($modifies) {
      $this->performHook('modified');
    }

    return $result;
  }

  /**
   * Push an item onto the beginning of the collection.
   *
   * @param mixed $value
   * @param mixed $key
   * @return $this
   */
  public function prepend($value, $key = null)
  {
    return parent::prepend($value, $key);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Push one or more items onto the end of the collection.
   *
   * @param mixed $values
   * @return $this
   */
  public function push(...$values)
  {
    parent::push(...$values);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Splice a portion of the underlying collection array.
   *
   * @param int $offset
   * @param int|null $length
   * @param mixed $replacement
   * @return static
   */
  public function splice($offset, $length = null, $replacement = [])
  {
    parent::splice($offset, $length, $replacement);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Transform each item in the collection using a callback.
   *
   * @param callable $callback
   * @return $this
   */
  public function transform(callable $callback)
  {
    parent::transform($callback);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Add an item to the collection.
   *
   * @param mixed $item
   * @return $this
   */
  public function add($item)
  {
    parent::add($item);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Set the item at a given offset.
   *
   * @param string|int $offset
   * @param mixed $value
   * @return void
   */
  public function offsetSet($offset, $value)
  {
    parent::offsetSet($offset, $value);
    $this->performHook('modified');
  }

  /**
   * Unset the item at a given offset.
   *
   * @param string|int $offset
   * @return void
   */
  public function offsetUnset($offset)
  {
    parent::offsetUnset($offset);
    $this->performHook('modified');
  }

  /**
   * Get the collection of items as a plain array.
   *
   * @return array
   */
  public function toArray()
  {
    return array_values(
      array_filter(
        $this->jsonSerialize()
      )
    );
  }

  /**
   * Get the items in the collection that are not present in the given items.
   *
   * @param  mixed  $items
   * @return BaseCollection
   */
  public function diff($items)
  {
    return $this->toBase()->diff($items);
  }

  /**
   * Get the items in the collection that are not present in the given items, using the callback.
   *
   * @param  mixed  $items
   * @param  callable  $callback
   * @return BaseCollection
   */
  public function diffUsing($items, callable $callback)
  {
    return $this->toBase()->diffUsing($items, $callback);
  }

  /**
   * Run a filter over each of the items.
   *
   * @param  callable|null  $callback
   * @return BaseCollection
   */
  public function filter(callable $callback = null)
  {
    return $this->toBase()->filter($callback);
  }

  /**
   * Flip the items in the collection.
   *
   * @return BaseCollection
   */
  public function flip()
  {
    return $this->toBase()->flip();
  }

  /**
   * Intersect the collection with the given items.
   *
   * @param  mixed  $items
   * @return BaseCollection
   */
  public function intersect($items)
  {
    return $this->toBase()->intersect($items);
  }

  /**
   * Get the values of a given key.
   *
   * @param  string|array|int|null  $value
   * @param  string|null  $key
   * @return BaseCollection
   */
  public function pluck($value, $key = null)
  {
    return $this->toBase()->pluck($value, $key);
  }

  /**
   * Run a map over each of the items.
   *
   * @param callable $callback
   * @return BaseCollection
   */
  public function map(callable $callback)
  {
    return $this->toBase()->map($callback);
  }

  /**
   * Run a dictionary map over the items.
   *
   * The callback should return an associative array with a single key/value pair.
   *
   * @param  callable  $callback
   * @return BaseCollection
   */
  public function mapToDictionary(callable $callback)
  {
    return new BaseCollection(parent::mapToDictionary($callback));
  }

  /**
   * Run an associative map over each of the items.
   *
   * The callback should return an associative array with a single key/value pair.
   *
   * @param  callable  $callback
   * @return BaseCollection
   */
  public function mapWithKeys(callable $callback)
  {
    return new BaseCollection(parent::mapWithKeys($callback));
  }

  /**
   * @return array
   */
  #[\ReturnTypeWillChange]
  public function jsonSerialize()
  {
    $items = $this->all();

    if ($items && count($items)) {
      return array_map(function ($item) {
        return $item->jsonSerialize();
      }, $items);
    }

    return [];
  }

  public function __serialize(): array
  {
    return [
      'parent' => $this->parent,
      'items' => $this->items,
      'hooks' => []
    ];
  }

  public function __unserialize(array $data): void
  {
    $this->parent = $data['parent'] ?? null;
    $this->items = $data['items'] ?? [];
    $this->hooks = $data['hooks'] ?? [];
  }
}
