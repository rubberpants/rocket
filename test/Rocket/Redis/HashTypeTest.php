<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class HashTypeTest extends BaseTest
{
    public function test()
    {
        $hash = Harness::getInstance()->getRedis()->getHashType(1, 'Kalgan');

        $hash->delete();

        $this->assertFalse($hash->exists());

        $hash->setField('Ballarians', 7);

        $this->assertTrue($hash->exists());
        $this->assertTrue($hash->fieldExists('Ballarians'));
        $this->assertEquals(7, $hash->getField('Ballarians'));
        $this->assertEquals(7, $hash['Ballarians']);

        $hash->deleteField('Ballarians');

        $this->assertFalse($hash->fieldExists('Ballarians'));

        $hash->delete();

        $this->assertFalse($hash->exists());
    }
}
