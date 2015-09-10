<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class UniqueListTypeTest extends BaseTest
{
    public function test()
    {
        $list = Harness::getInstance()->getRedis()->getUniqueListType(1, 'Boggy Creek Creature');

        $list->delete();

        $list->pushItem('Tim');

        $this->assertEquals('Tim', $list->getItem(0));
        $this->assertTrue($list->hasItem('Tim'));

        $this->assertNull($list->pushItem('Tim'));

        $list->pushItem('Leslie');
        $this->assertTrue($list->hasItem('Leslie'));

        $this->assertEquals('Tim', $list->getItem(0));
        $this->assertEquals('Leslie', $list->getItem(1));

        $this->assertEquals(['Tim', 'Leslie'], $list->getItems(1, 10));

        $list->insertItem('Tanya', 'BEFORE', 'Leslie');
        $this->assertTrue($list->hasItem('Tanya'));

        $this->assertEquals(['Tim', 'Tanya', 'Leslie'], $list->getItems(1, 10));
        $this->assertEquals('Tim', $list->popItem());
        $this->assertFalse($list->hasItem('Tim'));

        $this->assertEquals(['Tanya', 'Leslie'], $list->getItems(1, 10));
        $this->assertEquals('Tanya', $list->blockAndPopItem());
        $this->assertFalse($list->hasItem('Tanya'));

        $list->deleteItem('Leslie');
        $this->assertFalse($list->hasItem('Leslie'));

        $this->assertEquals([], $list->getItems(1, 10));

        $list->delete();
    }
}
