<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class ListTypeTest extends BaseTest
{
    public function test()
    {
        $list = Harness::getInstance()->getRedis()->getListType(1, 'Trumpy');

        $list->delete();

        $list->pushItem('Tommy');

        $this->assertEquals('Tommy', $list->getItem(0));

        $list->pushItem('Bill');

        $this->assertEquals('Tommy', $list->getItem(0));
        $this->assertEquals('Bill', $list->getItem(1));

        $this->assertEquals(['Tommy', 'Bill'], $list->getItems(1, 10));

        $list->insertItem('Greg', 'BEFORE', 'Bill');

        $this->assertEquals(['Tommy', 'Greg', 'Bill'], $list->getItems(1, 10));
        $this->assertEquals('Tommy', $list->popItem());

        $this->assertEquals(['Greg', 'Bill'], $list->getItems(1, 10));
        $this->assertEquals('Greg', $list->blockAndPopItem());

        $list->deleteItem('Bill');

        $this->assertEquals([], $list->getItems(1, 10));

        $list->delete();
    }
}
