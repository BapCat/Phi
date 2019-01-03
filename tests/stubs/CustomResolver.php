<?php

use BapCat\Phi\Resolver;

class CustomResolver implements Resolver {
  public function make(string $alias, array $arguments = []) {
    if($alias == 'A') {
      return new B(new A());
    }
  }
}
