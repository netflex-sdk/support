<?php

namespace Netflex\Support;

use Exception;

class JWT
{
  /**
   * @param string $data
   * @return string
   */
  private static function base64UrlEncode($data)
  {
    $urlSafeData = strtr(base64_encode($data), '+/', '-_');
    return rtrim($urlSafeData, '=');
  }

  /**
   * @param string $data
   * @return string
   */
  private static function base64UrlDecode($data)
  {
    $urlUnsafeData = strtr($data, '-_', '+/');
    $paddedData = str_pad($urlUnsafeData, strlen($data) % 4, '=', STR_PAD_RIGHT);

    return base64_decode($paddedData);
  }

  /**
   * @param array $payload
   * @param string $secret
   * @param int $exp
   * @param string $iss
   * @return string
   */
  public static function create($payload = [], $secret, $exp = 60, $iss = 'netflex')
  {
    if (!$secret) {
      throw new Exception('JWT secret missing');
    }

    $header = [
      'iat' => time(),
      'exp' => time() + $exp,
      'iss' => $iss,
      'typ' => 'JWT',
      'alg' => 'HS256'
    ];

    $token = [];

    $token[] = static::base64UrlEncode(json_encode($header));
    $token[] = static::base64UrlEncode(json_encode($payload));
    $token[] = static::base64UrlEncode(
      hash_hmac('sha256', implode('.', $token), $secret, true)
    );

    return implode('.', $token);
  }

  /**
   * @param string $jwt
   * @param string $secret
   * @return mixed
   */
  public static function decodeAndVerify($jwt, $secret)
  {
    if (static::verify($jwt, $secret)) {
      return static::decode($jwt)->payload;
    }

    return false;
  }

  /**
   * @param string $jwt
   * @param string $secret
   * @return object
   */
  public static function verify($jwt, $secret)
  {
    if (!$secret) {
      throw new Exception('JWT secret missing');
    }

    $token = static::decode($jwt);

    $header = static::base64UrlEncode(json_encode($token->header));
    $payload = static::base64UrlEncode(json_encode($token->payload));
    $signature = hash_hmac('sha256', "$header.$payload", $secret, true);

    return hash_equals($token->signature, $signature) && ($token->header->exp ?? 0) >= time();
  }

  /**
   * @param string $jwt
   * @return object
   */
  public static function decode($jwt)
  {
    $token = array_map('static::base64UrlDecode', explode('.', $jwt));

    return (object) [
      'header' => json_decode(array_shift($token)),
      'payload' => json_decode(array_shift($token)),
      'signature' => array_shift($token)
    ];
  }
}
