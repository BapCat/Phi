<?php namespace BapCat\Phi;

use BapCat\Interfaces\Ioc\Ioc;
use BapCat\Interfaces\Ioc\Resolver;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Dependency injection manager
 */
class Phi extends Ioc {
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
   */
  public function bind($alias, $binding) {
    $this->_map[$alias] = $binding;
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
   */
  public function singleton($alias, $binding) {
    if(!is_object($binding)) {
      $this->_singletons[$alias] = $binding;
    } else {
      // If they gave us an object, it's already loaded... no need to lazy-load it
      $this->bind($alias, $binding);
    }
  }
  
  /**
   * Resolves an alias to a concrete class name
   * 
   * @param   string  $alias  An alias (eg. `db.helper`) to resolve back to a real class
   * 
   * @returns string          The concrete class registered to alias, or `$alias` if there is no binding
   */
  public function resolve($alias) {
    if(array_key_exists($alias, $this->_map)) {
      return $this->_map[$alias];
    }
    
    if(array_key_exists($alias, $this->_singleton)) {
      return $this->_singleton[$alias];
    }
    
    return $alias;
  }
  
  /**
   * Adds a custom resolver to the IoC container
   * 
   * @param   Resolver  $resolver The resolver to add
   */
  public function addResolver(Resolver $resolver) {
    $this->_resolvers[] = $resolver;
  }
  
  /**
   * Gets or creates an instance of an alias
   * 
   * @param   string  $alias      An alias (eg. `db.helper`), or a real class or interface name
   * @param   array   $arguments  The arguments to pass to the binding
   * 
   * @returns object  A new instance of `$alias`'s binding, or a shared instance in the case of singletons
   */
  public function make($alias, array $arguments = []) {
    // Iterate over each resolver and see if they have a binding override
    foreach($this->_resolvers as $resolver) {
      // Ask the resolver for the alias' binding
      $binding = $resolver->make($alias, $arguments);
      
      // If it's not null, we got a binding
      if($binding !== null) {
        return $binding;
      }
    }
    
    // Check to see if we have a singleton bound to this alias
    if(array_key_exists($alias, $this->_singletons)) {
      $binding = $this->_singletons[$alias];
      
      if(is_callable($binding)) {
        $this->_map[$alias] = call_user_func_array($binding, $arguments);
      } else {
        $this->_map[$alias] = $this->_buildObject($binding, $arguments);
      }
      
      unset($this->_singletons[$alias]);
    }
    
    // Check to see if we have something bound to this alias
    if(array_key_exists($alias, $this->_map)) {
      $binding = $this->_map[$alias];
      
      if(is_callable($binding)) {
        // If it's callable, we call it and pass on our arguments
        return call_user_func_array($binding, $arguments);
      } elseif(is_object($binding)) {
        // If it's an object, simply return it
        return $binding;
      }
    } else {
      // If we don't have a binding, we'll just be `new`ing up the alias
      $binding = $alias;
    }
    
    return $this->_buildObject($binding, $arguments);
  }
  
  private function _buildObject($binding, array $arguments = []) {
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
      return $reflector->newInstance();
    }
    
    $values = $this->buildArguments($method, $arguments);
    
    // Done! Create a new instance using the values array
    return $reflector->newInstanceArgs($values);
  }
  
  public function execute($instance, $method, array $arguments = []) {
    $class = new ReflectionClass($instance);
    $method = $class->getMethod($method);
    $values = $this->buildArguments($method, $arguments);
    
    if(!$method->isStatic()) {
        return $method->invokeArgs($instance, $values);
    } else {
        return $method->invokeArgs(null, $values);
    }
  }
  
  private function buildArguments(ReflectionMethod $method, array $arguments = []) {
    // Grab all of the constructor's parameters
    $parameters = $method->getParameters();
    
    // Size array
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
    foreach($arguments as $argIndex => $argument) {
      if(is_string($argIndex)) {
        foreach($parameters as $paramIndex => $parameter) {
          if($argIndex == $parameter->getName()) {
            $values[$paramIndex] = $argument;
            unset($arguments[$argIndex]);
            break;
          }
        }
      }
    }
    
    // Step 2...
    foreach($parameters as $paramIndex => $parameter) {
      if($parameter->getClass()) {
        foreach($arguments as $argIndex => $argument) {
          if(is_object($argument)) {
            if($parameter->getClass()->isInstance($argument)) {
              $values[$paramIndex] = $argument;
              unset($arguments[$argIndex]);
              break;
            }
          }
        }
      }
    }
    
    // Step 3...
    foreach($parameters as $paramIndex => $parameter) {
      if(!array_key_exists($paramIndex, $values)) {
        if($parameter->getClass()) {
          $values[$paramIndex] = $this->make($parameter->getClass()->getName());
        } else {
          $values[$paramIndex] = array_shift($arguments);
        }
      }
    }
    
    ksort($values);
    
    return $values;
  }
  
  /**
   * @var array   An assotiative array of aliases and bindings
   */
  private $_map = [];
  
  /**
   * @var array   An assotiative array of aliases and bindings to be lazy-loaded as singletons
   */
  private $_singletons = [];
  
  /**
   * @var array   An array of custom resolvers
   */
  private $_resolvers = [];
  
  /**
   * Protected constructor; class cannot be instantiated.
   */
  protected function __construct() { }
}
