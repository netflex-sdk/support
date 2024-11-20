<?php

namespace Netflex\Support;

use Illuminate\Support\HtmlString as BaseHtmlString;

use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;

class HtmlString extends BaseHtmlString implements JsonSerializable, Jsonable
{
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->__toString();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
