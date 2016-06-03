<?php

use BapCat\Phi\Phi;
use BapCat\Phi\InstantiationError;
use BapCat\Phi\InstantiationException;

class Issue11InstantiationBacktraceTest extends PHPUnit_Framework_TestCase {
  private $phi;
  
  public function setUp() {
    $this->phi = Phi::instance();
  }
  
  public function testMissingUnhintedParam() {
    try {
      $this->phi->make(Issue11TestClassUnhinted::class);
    } catch(InstantiationException $e) {
      $this->assertSame(Issue11TestClassUnhinted::class, $e->getAlias());
      $this->assertEmpty($e->getArgs());
    }
  }
  
  /**
   * @requires PHP 7
   */
  public function testMissingHintedParam() {
    try {
      $this->phi->make(Issue11TestClassHintedArray::class);
    } catch(InstantiationError $e) {
      $this->assertSame(Issue11TestClassHintedArray::class, $e->getAlias());
    }
  }
  
  /**
   * @requires PHP 7
   */
  public function testNestedMissingHintedParam() {
    try {
      $this->phi->make(Issue11TestClass::class);
    } catch(InstantiationError $e) {
      $this->assertSame(Issue11TestClass::class, $e->getAlias());
      $this->assertSame(Issue11TestClassNested::class, $e->getPrevious()->getAlias());
      $this->assertSame(Issue11TestClassHintedArray::class, $e->getPrevious()->getPrevious()->getAlias());
      $this->assertEmpty($e->getArgs());
    }
  }
  
  /**
   * @requires PHP 7
   */
  public function testWrongHintedParam() {
    try {
      $this->phi->make(Issue11TestClassHintedArray::class, ['test']);
    } catch(InstantiationError $e) {
      $this->assertSame(Issue11TestClassHintedArray::class, $e->getAlias());
      $this->assertSame(['test'], $e->getArgs());
    }
  }
}

class Issue11TestClass {
  public function __construct(Issue11TestClassNested $test) {
    
  }
}

class Issue11TestClassNested {
  public function __construct(Issue11TestClassHintedArray $test) {
    
  }
}

class Issue11TestClassNoArgs {
  public function __construct() {
    
  }
}

class Issue11TestClassUnhinted {
  public function __construct($var) {
    
  }
  
  public function call($var) {
    
  }
}

class Issue11TestClassHintedArray {
  public function __construct(array $arrary) {
    
  }
  
  public function call(array $array) {
    
  }
}
