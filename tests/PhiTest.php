<?php

require_once __DIR__ . '/stubs/A.php';
require_once __DIR__ . '/stubs/B.php';
require_once __DIR__ . '/stubs/DoubleDependencyConstructor.php';
require_once __DIR__ . '/stubs/Invokable.php';
require_once __DIR__ . '/stubs/MixedConstructor.php';
require_once __DIR__ . '/stubs/NoConstructor.php';
require_once __DIR__ . '/stubs/ScalarConstructor.php';
require_once __DIR__ . '/stubs/TypedConstructor.php';
require_once __DIR__ . '/stubs/Uninstantiable.php';
require_once __DIR__ . '/stubs/CustomResolver.php';
require_once __DIR__ . '/stubs/CustomResolver2.php';
require_once __DIR__ . '/stubs/Method.php';

use BapCat\Phi\Phi;

class PhiTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->phi = Phi::instance();
    $this->phi->flush();
  }
  
  public function testNoConstructor() {
    $instance = $this->phi->make('NoConstructor');
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testAlias() {
    $this->phi->bind('test', 'NoConstructor');
    $instance = $this->phi->make('test');
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testClosure() {
    $this->phi->bind('test', function($parameter1, $parameter2) {
      $this->assertEquals('param1', $parameter1);
      $this->assertEquals('param2', $parameter2);
      
      return new NoConstructor();
    });
    
    $instance = $this->phi->make('test', ['param1', 'param2']);
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testSingleton() {
    $this->phi->bind('test', new NoConstructor());
    $instance = $this->phi->make('test');
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testUninstantiable() {
    $this->setExpectedException('InvalidArgumentException');
    
    $instance = $this->phi->make('Uninstantiable');
  }
  
  public function testScalarParameters() {
    $instance = $this->phi->make('ScalarConstructor', ['a', 'b']);
    $this->assertEquals('a', $instance->getVal1());
    $this->assertEquals('b', $instance->getVal2());
  }
  
  public function testAutoInjection() {
    $instance = $this->phi->make('TypedConstructor');
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertInstanceOf('B', $instance->getB());
    $this->assertInstanceOf('A', $instance->getB()->getA());
  }
  
  public function testAutoInjectionPartialOverride() {
    $b = $this->phi->make('B');
    $instance = $this->phi->make('TypedConstructor', [$b]);
    $this->assertEquals($b, $instance->getB());
  }
  
  public function testAutoInjectionFullOverride() {
    $a = $this->phi->make('A');
    $b = $this->phi->make('B', [$a]);
    $instance = $this->phi->make('TypedConstructor', [$a, $b]);
    $this->assertEquals($a, $instance->getA());
    $this->assertEquals($b, $instance->getB());
    $this->assertEquals($a, $instance->getB()->getA());
  }
  
  public function testUnorderedInterleavedInjection() {
    $b = $this->phi->make('B');
    $instance = $this->phi->make('MixedConstructor', ['test1', 'test2', $b]);
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertEquals($b, $instance->getB());
    $this->assertEquals('test1', $instance->getVal1());
    $this->assertEquals('test2', $instance->getVal2());
  }
  
  public function testMultipleInjectionsOfOneClass() {
    $instance = $this->phi->make('DoubleDependencyConstructor');
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertInstanceOf('B', $instance->getB1());
    $this->assertInstanceOf('B', $instance->getB2());
    $this->assertNotEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }
  
  public function testMultipleInjectionsOfOneClassWithOneOverride() {
    $b = $this->phi->make('B');
    $instance = $this->phi->make('DoubleDependencyConstructor', [$b]);
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertInstanceOf('B', $instance->getB1());
    $this->assertInstanceOf('B', $instance->getB2());
    $this->assertNotEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }
  
  public function testMultipleInjectionsOfOneClassWithOverrides() {
    $b = $this->phi->make('B');
    $instance = $this->phi->make('DoubleDependencyConstructor', [$b, $b]);
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertInstanceOf('B', $instance->getB1());
    $this->assertInstanceOf('B', $instance->getB2());
    $this->assertEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }
  
  public function testMultipleInjectionsOfOneClassBindings() {
    $this->phi->bind('B', $this->phi->make('B'));
    $instance = $this->phi->make('DoubleDependencyConstructor');
    $this->assertInstanceOf('A', $instance->getA());
    $this->assertInstanceOf('B', $instance->getB1());
    $this->assertInstanceOf('B', $instance->getB2());
    $this->assertEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }
  
  public function testKeyedInjection() {
    $instance = $this->phi->make('MixedConstructor', ['val2' => 'test1', 'test2']);
    $this->assertEquals('test1', $instance->getVal2());
    $this->assertEquals('test2', $instance->getVal1());
  }
  
  public function testMultipleKeyedInjection() {
    $instance = $this->phi->make('ScalarConstructor', ['val2' => 'test1', 'val1' => 'test2']);
    $this->assertEquals('test1', $instance->getVal2());
    $this->assertEquals('test2', $instance->getVal1());
  }
  
  public function testCustomResolver() {
    $phi = $this->phi;
    $phi->addResolver(new CustomResolver());
    
    $instance = $phi->make('A');
    $this->assertInstanceOf('B', $instance);
    
    $instance = $phi->make('NoConstructor');
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testMultipleCustomResolvers() {
    $phi = $this->phi;
    $phi->addResolver(new CustomResolver());
    $phi->addResolver(new CustomResolver2());
    
    $instance = $phi->make('A');
    $this->assertInstanceOf('B', $instance);
    
    $instance = $phi->make('B');
    $this->assertInstanceOf('A', $instance);
    
    $instance = $phi->make('NoConstructor');
    $this->assertInstanceOf('NoConstructor', $instance);
  }
  
  public function testSingletons() {
    $phi = $this->phi;
    
    $phi->singleton('ThereCanBeOnlyOne', 'A');
    
    $a1 = $phi->make('ThereCanBeOnlyOne');
    $a2 = $phi->make('ThereCanBeOnlyOne');
    
    $this->assertSame($a1, $a2);
    
    $a3 = $phi->make('A');
    
    $this->assertNotSame($a1, $a3);
  }
  
  public function testSingletonInjection() {
    $phi = $this->phi;
    
    $phi->singleton('ScalarConstructor', 'ScalarConstructor', ['test1', 'test2']);
    
    $o = $phi->make('ScalarConstructor');
    
    $this->assertEquals('test1', $o->getVal1());
    $this->assertEquals('test2', $o->getVal2());
  }
  
  public function testClosuregleton() {
    $phi = $this->phi;
    
    $doIt = false;
    $phi->singleton('closure', function($didItWork) use(&$doIt) {
      if($didItWork == 'Yes it did') {
        $doIt = true;
      }
      
      return new NoConstructor;
    }, ['Yes it did']);
    
    $b = $phi->make('closure');
    
    $this->assertTrue($doIt);
  }
  
  public function testRecursiveResolution() {
    $phi = $this->phi;
    
    $phi->singleton('NoConstructor', 'NoConstructor');
    $phi->bind('a1', 'NoConstructor');
    $phi->bind('a2', 'a1');
    $phi->bind('a3', 'a2');
    
    $a1 = $phi->make('a3');
    $a2 = $phi->make('NoConstructor');
    
    $this->assertSame($a1, $a2);
  }
  
  public function testSingletonWithObject() {
    $phi = $this->phi;
    
    $a1 = new NoConstructor();
    
    $phi->singleton('NoConstructor', $a1);
    
    $a2 = $phi->make('NoConstructor');
    
    $this->assertSame($a1, $a2);
  }
  
  public function testResolve() {
    $phi = $this->phi;
    
    $phi->bind('A1', 'A2');
    
    $this->assertEquals('A2', $phi->resolve('A1'));
    $this->assertEquals('NoBinding', $phi->resolve('NoBinding'));
  }
  
  public function testCallInstanceMethod() {
    $instance = new Method();
    
    $this->phi->call([$instance, 'test'], ['123']);
    
    $this->assertInstanceOf(A::class, $instance->a);
    $this->assertSame('123', $instance->b);
    
    $a = new A();
    
    $this->phi->call([$instance, 'test'], [$a, '321']);
    
    $this->assertSame($a, $instance->a);
    $this->assertSame('321', $instance->b);
  }
  
  public function testCallStaticMethod() {
    $this->phi->call([Method::class, 'testStatic'], ['123']);
    
    $this->assertInstanceOf(A::class, Method::$a_static);
    $this->assertSame('123', Method::$b_static);
    
    $a = new A();
    
    $this->phi->call([Method::class, 'testStatic'], [$a, '321']);
    
    $this->assertSame($a, Method::$a_static);
    $this->assertSame('321', Method::$b_static);
  }
  
  public function testCallScapedStaticMethod() {
    $this->phi->call('Method::testStatic', ['123']);
    
    $this->assertInstanceOf(A::class, Method::$a_static);
    $this->assertSame('123', Method::$b_static);
    
    $a = new A();
    
    $this->phi->call('Method::testStatic', [$a, '321']);
    
    $this->assertSame($a, Method::$a_static);
    $this->assertSame('321', Method::$b_static);
  }
  
  public function testCallInvokable() {
    $invokable = new Invokable();
    
    $a = $this->phi->call($invokable);
    
    $this->assertInstanceOf(A::class, $a);
    
    $original_a = new A();
    
    $a = $this->phi->call($invokable, [$original_a]);
    
    $this->assertSame($original_a, $a);
  }
  
  public function testCallClosure() {
    $a = $this->phi->call(function(A $a) {
      return $a;
    });
    
    $this->assertInstanceOf(A::class, $a);
    
    $original_a = new A();
    
    $a = $this->phi->call(function(A $a) {
      return $a;
    }, [$original_a]);
    
    $this->assertSame($original_a, $a);
  }
  
  public function testCallGlobal() {
    function doTheThing(A $a) {
      return $a;
    }
    
    $a = $this->phi->call('doTheThing');
    
    $this->assertInstanceOf(A::class, $a);
    
    $original_a = new A();
    
    $a = $this->phi->call('doTheThing', [$original_a]);
    
    $this->assertSame($original_a, $a);
  }
}
