<?php

class Method {
  public $a;
  public $b;
  
  public function test(A $a, $b) {
    $this->a = $a;
    $this->b = $b;
  }
}
