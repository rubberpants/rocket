<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class StringTypeTest extends BaseTest
{
    public function test()
    {
        $string = Harness::getInstance()->getRedis()->getStringType(1, 'Rowzdower');

        $string->delete();

        $this->assertFalse($string->exists());

        $string->set('Ator');

        $this->assertTrue($string->exists());
        $this->assertEquals('Ator', $string->get());
        $this->assertEquals('Ator', $string."");

        $string->delete();

        $this->assertFalse($string->exists());
    }
}
