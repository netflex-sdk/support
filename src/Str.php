<?php

namespace Netflex\Support;

use Illuminate\Support\Str as BaseStr;

/**
 * @deprecated v3.2.1
 */
class Str extends BaseStr
{
  /**
   * @param string $str
   * @return string
   * @deprecated v3.2.1
   */
  public static function toCamcelCase(string $str): string
  {
    return lcfirst(preg_replace_callback('/(^|[_ -])([a-z])/', function ($matches) {
      return strtoupper($matches[2]);
    }, $str));
  }
}
