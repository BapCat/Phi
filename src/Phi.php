<?php declare(strict_types=1); namespace BapCat\Phi;

use BapCat\Values\Value;

use ReflectionException;
use ReflectionNamedType;
use TRex\Reflection\CallableReflection;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunctionAbstract;

use function is_object;
use function is_callable;
use function is_string;
use function count;

/**
 * Dependency injection manager
 */
class Phi extends Ioc {
  /**
   * Clears out all bindings, singletons, and resolvers
   *
   * @return  void
   */
  public function flush(): void {
    $this->map        = [];
    $this->singletons = [];
    $this->resolvers  = [];
  }

  /**
   * {@inheritDoc}
   */
  public function bind(string $alias, $binding) : void {
    $this->map[$alias] = $binding;
  }

  /**
   * {@inheritDoc}
   */
  public function singleton(string $alias, $binding, array $arguments = []): void {
    if(!is_object($binding) || is_callable($binding)) {
      $this->singletons[$alias] = [$binding, $arguments];
    } else {
      // If they gave us an object, it's already loaded... no need to lazy-load it
      $this->bind($alias, $binding);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function resolve(string $alias) {
    if(array_key_exists($alias, $this->map)) {
      return $this->map[$alias];
    }

    return $alias;
  }

  /**
   * {@inheritDoc}
   */
  public function addResolver(Resolver $resolver) : void {
    $this->resolvers[] = $resolver;
  }

  /**
   * {@inheritDoc}
   */
  public function make(string $alias, array $arguments = []) {
    // Iterate over each resolver and see if they have a binding override
    foreach($this->resolvers as $resolver) {
      // Ask the resolver for the alias' binding
      $binding = $resolver->make($alias, $arguments);

      // If it's not null, we got a binding
      if($binding !== null) {
        return $binding;
      }
    }

    // Check to see if we have a singleton for this alias
    if(array_key_exists($alias, $this->singletons)) {
      [$binding, $args] = $this->singletons[$alias];

      if(is_callable($binding)) {
        $this->map[$alias] = $binding(...$args);
      } else {
        $this->map[$alias] = $this->buildObject($binding, $args);
      }

      unset($this->singletons[$alias]);
    }

    // Check to see if we have something bound to this alias
    if(array_key_exists($alias, $this->map)) {
      $binding = $this->map[$alias];

      // If it's callable, we call it and pass on our arguments
      if(is_callable($binding)) {
        return $binding(...$arguments);
      }

      // If it's an object, simply return it
      if(is_object($binding)) {
        return $binding;
      }

      return $this->make($binding, $arguments);
    }

    // If we don't have a binding, we'll just be `new`ing up the alias
    return $this->buildObject($alias, $arguments);
  }

  /**
   * Creates an instance of a binding with a given set of arguments
   *
   * @param  string  $binding    The binding to instantiate
   * @param  array   $arguments  The arguments to pass to the constructor
   *
   * @return  mixed  The instance that is created
   *
   * @throws  ReflectionException
   */
  private function buildObject(string $binding, array $arguments = []) {
    // This will be used to `new` up the binding
    $reflector = new ReflectionClass($binding);

    // Make sure it's instantiable (ie. not abstract/interface)
    if(!$reflector->isInstantiable()) {
      throw new InvalidArgumentException("$binding is not an instantiable class");
    }

    // Grab the constructor
    $method = $reflector->getConstructor();

    // If there's no constructor, it's easy.  Just make a new instance.
    if($method === null) {
      return new $binding();
    }

    $values = $this->buildArguments($method, $arguments);

    // Done! Create a new instance using the values array
    return new $binding(...$values);
  }

  /**
   * {@inheritDoc}
   */
  public function call(callable $call, array $arguments = []) {
    $reflector = new CallableReflection($call);
    $method = $reflector->getReflector();
    $values = $this->buildArguments($method, $arguments);

    return $call(...$values);
  }

  /**
   * Where the magic happens.  Builds the argument list for a given method and calls it.
   *
   * @param  ReflectionFunctionAbstract  $method     The method to call
   * @param  mixed[]                     $arguments  The arguments to pass to the the method
   *
   * @return  mixed[]  The return value of the method
   */
  private function buildArguments(ReflectionFunctionAbstract $method, array $arguments = []): array {
    $parameters = $method->getParameters();
    $values = [];

    /*
     * The following is a three-step process to fill out the parameters. For example:
     *
     * ```
     * parameters = [A $p1, B $p2, string $p3, B $p4, string $p5]
     * arguments  = [new B, new B, 'p5' => 'asdf', 'fdsa']
     * values     = [, , , , ]
     *
     * Iterate over arguments ->
     *   Does argument have key? ->
     *     Iterate over parameters ->
     *       Is argument key == parameter name? ->
     *         values[parameter index] = argument
     *         unset argument[argument key]
     *         break
     *
     * parameters = [A $p1, B $p2, string $p3, B $p4, string $p5]
     * arguments  = [new B, new B, 'fdsa']
     * values     = [, , , , 'asdf']
     *
     * Iterate over parameters ->
     *   Does parameter have a class? ->
     *     Iterate over arguments ->
     *       Is argument instance of parameter? ->
     *         values[parameter index] = argument
     *         unset argument[argument index]
     *         break
     *
     * parameters = [A $p1, B $p2, string $p3 B $p4, string $p5]
     * arguments  = ['fdsa']
     * values     = [, new B, , new B, 'asdf']
     *
     * Iterate over parameters ->
     *   Is values missing index [parameter index]?
     *     Does parameter have a class?
     *       values[parameter index] = Ioc::make(parameter)
     *     Otherwise,
     *       values[parameter index] = the first argument left in arguments
     *       pop the first element from arguments
     *
     * parameters = [A, B, string, B, string]
     * arguments  = []
     * values     = [new A (from Ioc), new B, 'fdsa', new B, 'asdf']
     * ```
     */

    // Step 1...
    foreach($arguments as $arg_index => $argument) {
      if(is_string($arg_index)) {
        foreach($parameters as $param_index => $parameter) {
          if($arg_index === $parameter->getName()) {
            $values[$param_index] = $argument;
            unset($arguments[$arg_index]);
            break;
          }
        }
      }
    }

    // Step 2...
    foreach($parameters as $param_index => $parameter) {
      $type = $parameter->getType();
      if($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
        foreach($arguments as $arg_index => $argument) {
          if(is_object($argument)) {
            $typeName = $type->getName();
            if($argument instanceof $typeName) {
              $values[$param_index] = $argument;
              unset($arguments[$arg_index]);
              break;
            }
          }
        }
      }
    }

    // Step 3...
    foreach($parameters as $param_index => $parameter) {
      if(!array_key_exists($param_index, $values)) {
        $type = $parameter->getType();

        if($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
          $class = new ReflectionClass($type->getName());

          if($class->isSubclassOf(Value::class)) {
            $values[$param_index] = $this->make($type->getName(), [array_shift($arguments)]);
            continue;
          }

          if(!$parameter->isOptional()) {
            $values[$param_index] = $this->make($type->getName());
          }
        } elseif(count($arguments) !== 0) {
          $values[$param_index] = array_shift($arguments);
        }
      }
    }

    ksort($values);

    return $values;
  }

  /** @var  mixed[]  $map  An associative array of aliases and bindings */
  private $map = [];

  /** @var  mixed[]  $singletons  An associative array of aliases and bindings to be lazy-loaded as singletons */
  private $singletons = [];

  /** @var  Resolver[]  $resolvers  An array of custom resolvers */
  private $resolvers = [];
}
