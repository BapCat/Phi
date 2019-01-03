<?php declare(strict_types=1);

use BapCat\Phi\Phi;
use BapCat\Values\Email;
use BapCat\Values\Text;
use BapCat\Values\Timestamp;
use PHPUnit\Framework\TestCase;

class Issue9ValueObjectInjectionTest extends TestCase {
  /** @var  Phi  $ioc */
  private $ioc;

  public function setUp(): void {
    parent::setUp();
    $this->ioc = Phi::instance();
  }

  public function testValueObjectInjection(): void {
    $time = time();

    $obj = $this->ioc->make(ValueObjectConstructor::class, [
      'Some text',
      'dude.bro@do.u.lift',
      $time
    ]);

    $this->assertSame('Some text',          $obj->text->raw);
    $this->assertSame('dude.bro@do.u.lift', $obj->email->raw);
    $this->assertSame($time,                $obj->timestamp->raw);
  }
}

class ValueObjectConstructor {
  public $text;
  public $email;
  public $timestamp;

  public function __construct(Text $text, Email $email, Timestamp $timestamp) {
    $this->text = $text;
    $this->email = $email;
    $this->timestamp = $timestamp;
  }
}
