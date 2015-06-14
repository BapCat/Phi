<?php

use BapCat\Interfaces\Ioc\Resolver;

class CustomResolver2 implements Resolver {
  public function make($alias, array $arguments = []) {
    if($alias == 'B') {
      return new A();
    }
  }
}