<?php

class Method {
  public static $a_static;
  public static $b_static;
  
  public $a;
  public $b;
  
  public static function testStatic(A $a, $b) {
    self::$a_static = $a;
    self::$b_static = $b;
  }
  
  public function test(A $a, $b) {
    $this->a = $a;
    $this->b = $b;
  }
}
