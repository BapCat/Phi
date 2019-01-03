<?php declare(strict_types=1);

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
use PHPUnit\Framework\TestCase;

class PhiTest extends TestCase {
  /** @var  Phi  $phi */
  private $phi;

  public function setUp(): void {
    parent::setUp();
    $this->phi = Phi::instance();
    $this->phi->flush();
  }

  public function testNoConstructor(): void {
    $instance = $this->phi->make(NoConstructor::class);
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testAlias(): void {
    $this->phi->bind('test', NoConstructor::class);
    $instance = $this->phi->make('test');
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testClosure(): void {
    $this->phi->bind('test', function($parameter1, $parameter2) {
      $this->assertEquals('param1', $parameter1);
      $this->assertEquals('param2', $parameter2);

      return new NoConstructor();
    });

    $instance = $this->phi->make('test', ['param1', 'param2']);
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testSingleton(): void {
    $this->phi->bind('test', new NoConstructor());
    $instance = $this->phi->make('test');
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testUninstantiable(): void {
    $this->expectException(InvalidArgumentException::class);

    $this->phi->make(Uninstantiable::class);
  }

  public function testScalarParameters(): void {
    $instance = $this->phi->make(ScalarConstructor::class, ['a', 'b']);
    $this->assertEquals('a', $instance->getVal1());
    $this->assertEquals('b', $instance->getVal2());
  }

  public function testAutoInjection(): void {
    $instance = $this->phi->make(TypedConstructor::class);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertInstanceOf(B::class, $instance->getB());
    $this->assertInstanceOf(A::class, $instance->getB()->getA());
  }

  public function testAutoInjectionPartialOverride(): void {
    $b = $this->phi->make(B::class);
    $instance = $this->phi->make(TypedConstructor::class, [$b]);
    $this->assertEquals($b, $instance->getB());
  }

  public function testAutoInjectionFullOverride(): void {
    $a = $this->phi->make(A::class);
    $b = $this->phi->make(B::class, [$a]);
    $instance = $this->phi->make(TypedConstructor::class, [$a, $b]);
    $this->assertEquals($a, $instance->getA());
    $this->assertEquals($b, $instance->getB());
    $this->assertEquals($a, $instance->getB()->getA());
  }

  public function testUnorderedInterleavedInjection(): void {
    $b = $this->phi->make(B::class);
    $instance = $this->phi->make(MixedConstructor::class, ['test1', 'test2', $b]);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertEquals($b, $instance->getB());
    $this->assertEquals('test1', $instance->getVal1());
    $this->assertEquals('test2', $instance->getVal2());
  }

  public function testMultipleInjectionsOfOneClass(): void {
    $instance = $this->phi->make(DoubleDependencyConstructor::class);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertInstanceOf(B::class, $instance->getB1());
    $this->assertInstanceOf(B::class, $instance->getB2());
    $this->assertNotEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }

  public function testMultipleInjectionsOfOneClassWithOneOverride(): void {
    $b = $this->phi->make(B::class);
    $instance = $this->phi->make(DoubleDependencyConstructor::class, [$b]);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertInstanceOf(B::class, $instance->getB1());
    $this->assertInstanceOf(B::class, $instance->getB2());
    $this->assertNotEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }

  public function testMultipleInjectionsOfOneClassWithOverrides(): void {
    $b = $this->phi->make(B::class);
    $instance = $this->phi->make(DoubleDependencyConstructor::class, [$b, $b]);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertInstanceOf(B::class, $instance->getB1());
    $this->assertInstanceOf(B::class, $instance->getB2());
    $this->assertEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }

  public function testMultipleInjectionsOfOneClassBindings(): void {
    $this->phi->bind(B::class, $this->phi->make(B::class));
    $instance = $this->phi->make(DoubleDependencyConstructor::class);
    $this->assertInstanceOf(A::class, $instance->getA());
    $this->assertInstanceOf(B::class, $instance->getB1());
    $this->assertInstanceOf(B::class, $instance->getB2());
    $this->assertEquals(spl_object_hash($instance->getB1()), spl_object_hash($instance->getB2()));
  }

  public function testKeyedInjection(): void {
    $instance = $this->phi->make(MixedConstructor::class, ['val2' => 'test1', 'test2']);
    $this->assertEquals('test1', $instance->getVal2());
    $this->assertEquals('test2', $instance->getVal1());
  }

  public function testMultipleKeyedInjection(): void {
    $instance = $this->phi->make(ScalarConstructor::class, ['val2' => 'test1', 'val1' => 'test2']);
    $this->assertEquals('test1', $instance->getVal2());
    $this->assertEquals('test2', $instance->getVal1());
  }

  public function testCustomResolver(): void {
    $phi = $this->phi;
    $phi->addResolver(new CustomResolver());

    $instance = $phi->make(A::class);
    $this->assertInstanceOf(B::class, $instance);

    $instance = $phi->make(NoConstructor::class);
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testMultipleCustomResolvers(): void {
    $phi = $this->phi;
    $phi->addResolver(new CustomResolver());
    $phi->addResolver(new CustomResolver2());

    $instance = $phi->make(A::class);
    $this->assertInstanceOf(B::class, $instance);

    $instance = $phi->make(B::class);
    $this->assertInstanceOf(A::class, $instance);

    $instance = $phi->make(NoConstructor::class);
    $this->assertInstanceOf(NoConstructor::class, $instance);
  }

  public function testSingletons(): void {
    $phi = $this->phi;

    $phi->singleton('ThereCanBeOnlyOne', A::class);

    $a1 = $phi->make('ThereCanBeOnlyOne');
    $a2 = $phi->make('ThereCanBeOnlyOne');

    $this->assertSame($a1, $a2);

    $a3 = $phi->make(A::class);

    $this->assertNotSame($a1, $a3);
  }

  public function testSingletonInjection(): void {
    $phi = $this->phi;

    $phi->singleton(ScalarConstructor::class, ScalarConstructor::class, ['test1', 'test2']);

    $o = $phi->make(ScalarConstructor::class);

    $this->assertEquals('test1', $o->getVal1());
    $this->assertEquals('test2', $o->getVal2());
  }

  public function testClosuregleton(): void {
    $phi = $this->phi;

    $doIt = false;
    $phi->singleton('closure', function(string $didItWork) use(&$doIt) : NoConstructor {
      if($didItWork === 'Yes it did') {
        $doIt = true;
      }

      return new NoConstructor();
    }, ['Yes it did']);

    $phi->make('closure');

    $this->assertTrue($doIt);
  }

  public function testRecursiveResolution(): void {
    $phi = $this->phi;

    $phi->singleton(NoConstructor::class, NoConstructor::class);
    $phi->bind('a1', NoConstructor::class);
    $phi->bind('a2', 'a1');
    $phi->bind('a3', 'a2');

    $a1 = $phi->make('a3');
    $a2 = $phi->make(NoConstructor::class);

    $this->assertSame($a1, $a2);
  }

  public function testSingletonWithObject(): void {
    $phi = $this->phi;

    $a1 = new NoConstructor();

    $phi->singleton(NoConstructor::class, $a1);

    $a2 = $phi->make(NoConstructor::class);

    $this->assertSame($a1, $a2);
  }

  public function testResolve(): void {
    $phi = $this->phi;

    $phi->bind('A1', 'A2');

    $this->assertEquals('A2', $phi->resolve('A1'));
    $this->assertEquals('NoBinding', $phi->resolve('NoBinding'));
  }

  public function testCallInstanceMethod(): void {
    $instance = new Method();

    $this->phi->call([$instance, 'test'], ['123']);

    $this->assertInstanceOf(A::class, $instance->a);
    $this->assertSame('123', $instance->b);

    $a = new A();

    $this->phi->call([$instance, 'test'], [$a, '321']);

    $this->assertSame($a, $instance->a);
    $this->assertSame('321', $instance->b);
  }

  public function testCallStaticMethod(): void {
    $this->phi->call([Method::class, 'testStatic'], ['123']);

    $this->assertInstanceOf(A::class, Method::$a_static);
    $this->assertSame('123', Method::$b_static);

    $a = new A();

    $this->phi->call([Method::class, 'testStatic'], [$a, '321']);

    $this->assertSame($a, Method::$a_static);
    $this->assertSame('321', Method::$b_static);
  }

  public function testCallScapedStaticMethod(): void {
    $this->phi->call('Method::testStatic', ['123']);

    $this->assertInstanceOf(A::class, Method::$a_static);
    $this->assertSame('123', Method::$b_static);

    $a = new A();

    $this->phi->call('Method::testStatic', [$a, '321']);

    $this->assertSame($a, Method::$a_static);
    $this->assertSame('321', Method::$b_static);
  }

  public function testCallInvokable(): void {
    $invokable = new Invokable();

    $a = $this->phi->call($invokable);

    $this->assertInstanceOf(A::class, $a);

    $original_a = new A();

    $a = $this->phi->call($invokable, [$original_a]);

    $this->assertSame($original_a, $a);
  }

  public function testCallClosure(): void {
    $a = $this->phi->call(function(A $a) {
      return $a;
    });

    $this->assertInstanceOf(A::class, $a);

    $original_a = new A();

    $a = $this->phi->call(function(A $a) : A {
      return $a;
    }, [$original_a]);

    $this->assertSame($original_a, $a);
  }

  public function testCallGlobal(): void {
    function doTheThing(A $a) : A {
      return $a;
    }

    $a = $this->phi->call('doTheThing');

    $this->assertInstanceOf(A::class, $a);

    $original_a = new A();

    $a = $this->phi->call('doTheThing', [$original_a]);

    $this->assertSame($original_a, $a);
  }
}
