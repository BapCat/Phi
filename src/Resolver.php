<?php declare(strict_types=1); namespace BapCat\Phi;

/**
 * An interface for custom binding resolvers.  Resolvers may
 * be used to extend the functionality of the IoC container.
 *
 * @author    Corey Frenette
 * @copyright Copyright (c) 2019, BapCat
 */
interface Resolver {
  /**
   * Gets or creates an instance of an alias, or returns null to allow the next Resolver to execute
   *
   * @param  string   $alias      An alias (eg. `db.helper`), or a real class or interface name
   * @param  mixed[]  $arguments  The arguments to pass to the binding
   *
   * @return  string|callable|object|null  An instance of `$alias`'s binding, or null to allow the next Resolver to execute
   */
  public function make(string $alias, array $arguments = []);
}
