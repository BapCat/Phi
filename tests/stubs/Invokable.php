<?php

class Invokable {
  public function __invoke(A $a) {
    return $a;
  }
}
