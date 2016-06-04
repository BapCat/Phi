<?php namespace BapCat\Phi;

use BapCat\Interfaces\Ioc\Ioc;
use BapCat\Interfaces\Ioc\Resolver;
use BapCat\Interfaces\Values\Value;

use TRex\Reflection\CallableReflection;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Dependency injection manager
 */
class Phi extends Ioc {
  /**
   * Clears out all bindings, singletons, and resolvers
   * 
   * @return  void
   */
  public function flush() {
    $this->map        = [];
    $this->singletons = [];
    $this->resolvers  = [];
  }
  
  /**
   * Binds a class to an alias
   * 
   * @param   string                  $alias      An alias (eg. `db.helper`), or a real class or
   *                                              interface name to be replaced by `$binding`
   * @param   string|callable|object  $binding    May be one of the following:
   *                                              <ul>
   *                                                  <li>The fully-qualified name of a class</li>
   *                                                  <li>An instance of a class (creates a singleton)</li>
   *                                                  <li>A callable that returns an instance of a class</li>
   *                                              </ul>
   * 
   * @return  void
   */
  public function bind($alias, $binding) {
    $this->map[$alias] = $binding;
  }
  
  /**
   * Binds a class to an alias as a lazy-loaded singleton
   * 
   * @param   string                  $alias      An alias (eg. `db.helper`), or a real class or
   *                                              interface name to be replaced by `$binding`
   * @param   string|callable|object  $binding    May be one of the following:
   *                                              <ul>
   *                                                  <li>The fully-qualified name of a class</li>
   *                                                  <li>An instance of a class (creates a singleton)</li>
   *                                                  <li>A callable that returns an instance of a class</li>
   *                                              </ul>
   * @param   mixed[]   $arguments  The arguments to pass to the binding when it is constructed
   * 
   * @return  void
   */
  public function singleton($alias, $binding, array $arguments = []) {
    if(!is_object($binding) || is_callable($binding)) {
      $this->singletons[$alias] = [$binding, $arguments];
    } else {
      // If they gave us an object, it's already loaded... no need to lazy-load it
      $this->bind($alias, $binding);
    }
  }
  
  /**
   * Resolves an alias to a concrete class name
   * 
   * @param  string  $alias  An alias (eg. `db.helper`) to resolve back to a real class
   * 
   * @return string  The concrete class registered to alias, or `$alias` if there is no binding
   */
  public function resolve($alias) {
    if(array_key_exists($alias, $this->map)) {
      return $this->map[$alias];
    }
    
    return $alias;
  }
  
  /**
   * Adds a custom resolver to the IoC container
   * 
   * @param  Resolver  $resolver The resolver to add
   * 
   * @return  void
   */
  public function addResolver(Resolver $resolver) {
    $this->resolvers[] = $resolver;
  }
  
  /**
   * Gets or creates an instance of an alias
   * 
   * @param  string   $alias      An alias (eg. `db.helper`), or a real class or interface name
   * @param  mixed[]  $arguments  The arguments to pass to the binding
   * 
   * @return object  A new instance of `$alias`'s binding, or a shared instance in the case of singletons
   */
  public function make($alias, array $arguments = []) {
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
      list($binding, $args) = $this->singletons[$alias];
      
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
   * @param  string   $binding    The binding to instantiate
   * @param  mixed[]  $arguments  The arguments to pass to the constructor
   * 
   * @return object  The instance that is created
   */
  private function buildObject($binding, array $arguments = []) {
    // This will be used to `new` up the binding
    $reflector = new ReflectionClass($binding);
    
    // Make sure it's instantiable (ie. not abstract/interface)
    if(!$reflector->isInstantiable()) {
      throw new InvalidArgumentException("$binding is not an instantiable class");
    }
    
    // Grab the constructor
    $method = $reflector->getConstructor();
    
    // If there's no constructor, it's easy.  Just make a new instance.
    if(empty($method)) {
      return new $binding();
    }
    
    $values = $this->buildArguments($method, $arguments);
    
    // Done! Create a new instance using the values array
    return new $binding(...$values);
  }
  
  /**
   * Executes a callable using dependency injection
   * 
   * @param  callable  $call       A callable to execute using dependency injection
   * @param  mixed[]   $arguments  The arguments to pass to the callable
   * 
   * @return mixed     The return value of the callable
   */
  public function call(callable $call, array $arguments = []) {
    $reflector = new CallableReflection($call);
    $method = $reflector->getReflector();
    $values = $this->buildArguments($method, $arguments);
    
    //TODO Workaround for PHP5...
    if($reflector->isStaticMethod()) {
      return $reflector->invokeArgsStatic($values);
    }
    
    return $call(...$values);
  }
  
  /**
   * Where the magic happens.  Builds the argument list for a given method and calls it.
   * 
   * @param  ReflectionFunctionAbstract  $method     The method to call
   * @param  mixed[]                     $arguments  The arguments to pass to the the method
   * 
   * @return mixed  The return value of the method
   */
  private function buildArguments(ReflectionFunctionAbstract $method, array $arguments = []) {
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
          if($arg_index == $parameter->getName()) {
            $values[$param_index] = $argument;
            unset($arguments[$arg_index]);
            break;
          }
        }
      }
    }
    
    // Step 2...
    foreach($parameters as $param_index => $parameter) {
      if($parameter->getClass()) {
        foreach($arguments as $arg_index => $argument) {
          if(is_object($argument)) {
            if($parameter->getClass()->isInstance($argument)) {
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
        if($parameter->getClass()) {
          if($parameter->getClass()->isSubclassOf(Value::class)) {
            $values[$param_index] = $this->make($parameter->getClass()->getName(), [array_shift($arguments)]);
            continue;
          }
          
          if(!$parameter->isOptional()) {
            $values[$param_index] = $this->make($parameter->getClass()->getName());
          }
        } else {
          if(count($arguments) !== 0) {
            $values[$param_index] = array_shift($arguments);
          }
        }
      }
    }
    
    ksort($values);
    
    return $values;
  }
  
  /**
   * An assotiative array of aliases and bindings
   * 
   * @var  mixed[]
   */
  private $map = [];
  
  /**
   * An assotiative array of aliases and bindings to be lazy-loaded as singletons
   * 
   * @var  mixed[]
   */
  private $singletons = [];
  
  /**
   * An array of custom resolvers
   * 
   * @var  Resolver[]
   */
  private $resolvers = [];
}
