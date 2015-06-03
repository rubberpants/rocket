<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class SetTypeTest extends BaseTest
{
    public function test()
    {
        $set = Harness::getInstance()->getRedis()->getSetType(1, 'Prince of Space');

        $set->delete();

        $set->addItem('Krankor');
        $set->addItem('X-Radar');

        $this->assertTrue($set->hasItem('Krankor'));
        $this->assertFalse($set->hasItem('Professor Mackin'));

        $this->assertEquals(2, $set->getCount());
        $this->assertTrue(in_array('Krankor', $set->getItems()));
        $this->assertTrue(in_array('X-Radar', $set->getItems()));

        $set->deleteItem('X-Radar');

        $this->assertEquals(1, $set->getCount());

        $set->delete();
    }
}
