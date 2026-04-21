<?php

namespace Netflex\Support;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @template TKey of array-key
 * @template TValue of ReactiveObject
 *
 * @extends BaseCollection<TKey, TValue>
 */
abstract class ItemCollection extends BaseCollection implements JsonSerializable
{
  use Hooks;

  public ReactiveObject|null $parent = null;

  /** @var class-string<TValue> */
  protected static string $type = ReactiveObject::class;

  /**
   * @param array|null $items = []
   */
  public function __construct($items = [], $parent = null)
  {
    $this->parent = $parent;

    if ($items) {
      parent::__construct(array_map(
        fn ($item) => ($this->wireItem($item)),
        $items,
      ));
    }
  }

  /** @return TValue */
  protected function wireItem(mixed $item): ReactiveObject
  {
    if (
      $item instanceof ReactiveObject
      && !($item instanceof static::$type)
    ) {
      throw new InvalidArgumentException(sprintf(
        'Expected instance of %s, got %s',
        static::$type,
        get_class($item),
      ));
    }

    if (!($item instanceof static::$type)) {
      $item = new (static::$type)($item, null, false);
    }

    $item->setParent($this);

    return $item->addHook(
      'modified',
      fn () => ($this->performHook('modified')),
    );
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
   * Get and remove the first N items from the collection.
   *
   * @param int<0, max> $count
   * @return ($count is 1 ? TValue|null : static<int, TValue>)
   *
   * @throws InvalidArgumentException
   */
  public function shift($count = 1)
  {
    $modifies = !$this->isEmpty();

    $result = parent::shift($count);

    if ($modifies) {
      $this->performHook('modified');
    }

    return $result;
  }

  /**
   * Get and remove the last N items from the collection.
   *
   * @param int $count
   * @return ($count is 1 ? TValue|null : static<int, TValue>)
   */
  public function pop($count = 1)
  {
    $modifies = !$this->isEmpty();

    $result = parent::pop($count);

    if ($modifies) {
      $this->performHook('modified');
    }

    return $result;
  }

  /**
   * Push an item onto the beginning of the collection.
   *
   * @param TValue|array $value
   * @param TKey|null $key
   * @return $this
   */
  public function prepend($value, $key = null)
  {
    parent::prepend($this->wireItem($value), $key);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Push one or more items onto the end of the collection.
   *
   * @param TValue|array ...$values
   * @return $this
   */
  public function push(...$values)
  {
    parent::push(...array_map(
      fn ($item) => ($this->wireItem($item)),
      $values,
    ));
    $this->performHook('modified');
    return $this;
  }

  /**
   * Get and remove an item from the collection.
   *
   * @template TPullDefault
   *
   * @param TKey $key
   * @param TPullDefault|(Closure(): TPullDefault)  $default
   * @return TValue|TPullDefault
   */
  public function pull($key, $default = null)
  {
    $value = parent::pull($key, $default);
    $this->performHook('modified');
    return $value;
  }

  /**
   * Splice a portion of the underlying collection array.
   *
   * @param  int  $offset
   * @param  int|null  $length
   * @param  array<array-key, TValue>  $replacement
   * @return static
   */
  public function splice($offset, $length = null, $replacement = [])
  {
    $replacement = array_map(
      fn ($item) => ($this->wireItem($item)),
      $replacement,
    );
    parent::splice($offset, $length, $replacement);
    $this->performHook('modified');
    return $this;
  }

  /**
   * Transform each item in the collection using a callback.
   *
   * @param  callable(TValue, TKey): (TValue|array)  $callback
   * @return static
   */
  public function transform(callable $callback)
  {
    parent::transform(
      fn ($item, $key) => (
        $this->wireItem($callback($item, $key))
      ),
    );
    $this->performHook('modified');
    return $this;
  }

  public function remove(ReactiveObject $item): static
  {
    $key = $this->search($item);

    if ($key !== false) {
      $item->parent = null;
      $this->splice($key, 1);
    }

    return $this;
  }

  /** @return TValue */
  public function create(
    array $attributes = [],
    bool $markAttributesAsModified = true,
  ): ReactiveObject {
    $item = new (static::$type)(
      $attributes,
      $this,
      $markAttributesAsModified,
    );

    $this->add($item);

    return $item;
  }

  /**
   * Add an item to the collection.
   *
   * @param TValue|array $item
   * @return $this
   */
  public function add($item)
  {
    parent::add($this->wireItem($item));
    $this->performHook('modified');
    return $this;
  }

  /**
   * Set the item at a given offset.
   *
   * @param  TKey|null  $key
   * @param  TValue|array  $value
   * @return void
   */
  public function offsetSet($key, $value): void
  {
    parent::offsetSet($key, $this->wireItem($value));
    $this->performHook('modified');
  }

  /**
   * Unset the item at a given offset.
   *
   * @param TKey $key
   * @return void
   */
  public function offsetUnset($key): void
  {
    parent::offsetUnset($key);
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
   * Get the items not present in the given items.
   *
   * @return BaseCollection<TKey, TValue>
   */
  public function diff($items)
  {
    return $this->toBase()->diff($items);
  }

  /**
   * Get the items not present in the given items,
   * using the callback.
   *
   * @return BaseCollection<TKey, TValue>
   */
  public function diffUsing($items, callable $callback)
  {
    return $this->toBase()->diffUsing($items, $callback);
  }

  /**
   * Run a filter over each of the items.
   *
   * @return BaseCollection<TKey, TValue>
   */
  public function filter(callable $callback = null)
  {
    return $this->toBase()->filter($callback);
  }

  /**
   * Flip the items in the collection.
   *
   * @return BaseCollection<TValue, TKey>
   */
  public function flip()
  {
    return $this->toBase()->flip();
  }

  /**
   * Intersect the collection with the given items.
   *
   * @return BaseCollection<TKey, TValue>
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

  public function jsonSerialize(): array
  {
    $items = $this->all();

    if ($items && count($items)) {
      return array_map(function ($item) {
        return $item->jsonSerialize();
      }, $items);
    }

    return [];
  }

  public function toModifiedArray()
  {
    $items = $this->all();

    if ($items && count($items)) {
      return array_map(function (ReactiveObject $item) {
        return $item->toModifiedArray();
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
