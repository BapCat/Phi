<?php declare(strict_types=1); namespace BapCat\Phi;

/**
 * Dependency injection manager
 *
 * @author    Corey Frenette
 * @copyright Copyright (c) 2019, BapCat
 */
abstract class Ioc implements Resolver {
  /** @var  Ioc|null  $instance  Singleton instance */
  private static $instance;

  /**
   * Accessor for singleton
   *
   * @return  Ioc
   */
  public static function instance(): Ioc {
    if(self::$instance === null) {
      self::$instance = new static();
    }

    return self::$instance;
  }

  /**
   * @note  This function automatically binds Ioc to itself
   */
  private function __construct() {
    $this->bind(__CLASS__, $this);
  }

  /**
   * Binds a class to an alias
   *
   * @param  string                  $alias    An alias (eg. `db.helper`), or a real class or
   *                                           interface name to be replaced by `$binding`
   * @param  string|callable|object  $binding  May be one of the following:
   *                                           <ul>
   *                                             <li>The fully-qualified name of a class</li>
   *                                             <li>An instance of a class (creates a singleton)</li>
   *                                             <li>A callable that returns an instance of a class</li>
   *                                           </ul>
   *
   * @return  void
   */
  public abstract function bind(string $alias, $binding): void;

  /**
   * Binds a class to an alias as a lazy-loaded singleton
   *
   * @param  string                  $alias    An alias (eg. `db.helper`), or a real class or
   *                                           interface name to be replaced by `$binding`
   * @param  string|callable|object  $binding  May be one of the following:
   *                                           <ul>
   *                                             <li>The fully-qualified name of a class</li>
   *                                             <li>An instance of a class (creates a singleton)</li>
   *                                             <li>A callable that returns an instance of a class</li>
   *                                           </ul>
   * @param  array  $arguments  The arguments to pass to the binding when it is constructed
   *
   * @return  void
   */
  public abstract function singleton(string $alias, $binding, array $arguments = []): void;

  /**
   * Resolves an alias to a concrete class name
   *
   * @param  string  $alias  An alias (eg. `db.helper`) to resolve back to a real class
   *
   * @return  string|callable|object  The concrete class registered to alias, or `$alias` if there is no binding
   */
  public abstract function resolve(string $alias);

  /**
   * Adds a custom resolver to the IoC container
   *
   * @param  Resolver  $resolver  The resolver to add
   *
   * @return  void
   */
  public abstract function addResolver(Resolver $resolver): void;

  /**
   * Executes a callable using dependency injection
   *
   * @param  callable  $call       A callable to execute using dependency injection
   * @param  mixed[]   $arguments  The arguments to pass to the callable
   *
   * @return  mixed  The return value of the callable
   */
  public abstract function call(callable $call, array $arguments = []);
}
