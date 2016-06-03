<?php namespace BapCat\Phi;

/**
 * Rather than extending the Throwable interface, I've chosen to include all
 * throwable methods myself.  This is the only way to maintain support for PHP5.
 */
interface PhiThrowable {
  public function getMessage();
  public function getCode();
  public function getFile();
  public function getLine();
  public function getTrace();
  public function getTraceAsString();
  public function getPrevious();
  public function __toString();
}
