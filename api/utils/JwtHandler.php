<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHandler
{
  private $jwt_secret;
  private $issuedAt;
  private $expire;
  private $jwt;

  public function __construct()
  {
    // Set your secret key
    $this->jwt_secret = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_me';
    // By default issue now and expire in 1 day
    $this->issuedAt = time();
    $this->expire = $this->issuedAt + (int)($_ENV['JWT_EXPIRATION_SECONDS'] ?? 86400);
  }

  public function jwtEncodeData($iss, $data)
  {
    $token = array(
      "iss" => $iss,
      "aud" => $iss,
      "iat" => $this->issuedAt,
      "exp" => $this->expire,
      "data" => $data
    );

    $this->jwt = JWT::encode($token, $this->jwt_secret, 'HS256');
    return $this->jwt;
  }

  public function jwtDecodeData($jwt_token)
  {
    try {
      $decode = JWT::decode($jwt_token, new Key($this->jwt_secret, 'HS256'));
      return $decode->data;
    } catch (Exception $e) {
      error_log('JWT Decode failed: ' . $e->getMessage());
      return null;
    }
  }
}
