<?php

use BapCat\Phi\Resolver;

class CustomResolver2 implements Resolver {
  public function make(string $alias, array $arguments = []) {
    if($alias == 'B') {
      return new A();
    }
  }
}
