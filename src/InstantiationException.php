<?php namespace BapCat\Phi;

use Exception;

class InstantiationException extends Exception implements PhiThrowable {
  private $alias;
  private $args;
  
  public function __construct($alias, array $args, Exception $previous) {
    $this->alias = $alias;
    $this->args  = $args;
    
    $message = "An exception occurred while instantiating $alias";
    
    for($e = $previous; $e instanceof static; $e = $e->getPrevious()) {
      $message .= " -> {$e->getAlias()}";
    }
    
    parent::__construct("$message: {$e->message}", null, $previous);
    
    $this->file = $e->file;
    $this->line = $e->line;
  }
  
  public function getAlias() {
    return $this->alias;
  }
  
  public function getArgs() {
    return $this->args;
  }
}
