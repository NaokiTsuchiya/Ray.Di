<?php
namespace Ray\Di;

use PHPUnit\Framework\TestCase;

class ArgumentTest extends TestCase
{
    /**
     * @var Argument
     */
    protected $argument;

    public function setUp()
    {
        $this->argument = new Argument(new \ReflectionParameter([FakeCar::class, '__construct'], 'engine'), Name::ANY);
    }

    public function testToString()
    {
        $this->assertSame('Ray\Di\FakeEngineInterface-' . NAME::ANY, (string) $this->argument);
    }
}
