<?php

use BapCat\Interfaces\Ioc\Resolver;

class CustomResolver implements Resolver {
  public function make($alias, array $arguments = []) {
    if($alias == 'A') {
      return new B(new A());
    }
  }
}