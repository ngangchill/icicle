<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\Events\{EventFactoryInterface, TimerInterface};
use Icicle\Loop\UvLoop;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Manager\TimerManagerInterface;

class TimerManager implements TimerManagerInterface
{
    const MILLISEC_PER_SEC = 1e3;

    /**
     * @var resource
     */
    private $loopHandle;

    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;

    /**
     * ObjectStorage mapping Timer objects to event resources.
     *
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;

    /**
     * @var array Array mapping timer handles to Timer objects.
     */
    private $timerReverseTable = [];

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param \Icicle\Loop\UvLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory)
    {
        $this->loopHandle = $loop->getLoopHandle();
        $this->factory = $factory;

        $this->timers = new ObjectStorage();

        $this->callback = function ($timerHandle) {
            $timer = $this->timerReverseTable[(int)$timerHandle];

            if (!$timer->isPeriodic()) {
                $this->stop($timer);
            }

            $timer->call();
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            \uv_close($this->timers->getInfo());
        }

        // Need to completely destroy timer events before freeing base or an error is generated.
        $this->timers = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return !$this->timers->count();
    }

    /**
     * {@inheritdoc}
     */
    public function create(float $interval, bool $periodic, callable $callback, array $args = []): TimerInterface
    {
        $timer = $this->factory->timer($this, $interval, $periodic, $callback, $args);

        $this->start($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function start(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            $timerHandle = \uv_timer_init($this->loopHandle);

            \uv_timer_start(
                $timerHandle,
                $timer->getInterval() * self::MILLISEC_PER_SEC,
                $timer->getInterval() * self::MILLISEC_PER_SEC,
                $this->callback
            );

            $this->timers[$timer] = $timerHandle;
            $this->timerReverseTable[(int)$timerHandle] = $timer;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $timerHandle = $this->timers[$timer];

            \uv_timer_stop($timerHandle);
            \uv_close($timerHandle);

            unset($this->timers[$timer]);
            unset($this->timerReverseTable[(int)$timerHandle]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(TimerInterface $timer): bool
    {
        return isset($this->timers[$timer]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function reference(TimerInterface $timer)
    {
        $this->timers->reference($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            \uv_timer_stop($this->timers->getInfo());
            \uv_close($this->timers->getInfo());
        }

        $this->timers = new ObjectStorage();
    }
}
