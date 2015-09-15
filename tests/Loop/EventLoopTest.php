<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use EventBase;
use Icicle\Loop\EventLoop;
use Icicle\Loop\Events\EventFactoryInterface;

/**
 * @requires extension event
 */
class EventLoopTest extends AbstractLoopTest
{
    protected static $base;
    
    public static function setUpBeforeClass()
    {
        if (extension_loaded('event')) {
            self::$base = new EventBase();
        }
    }
    
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new EventLoop(true, $eventFactory, self::$base);
    }
}
