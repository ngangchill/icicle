<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\UnsupportedError;
use Icicle\Loop\Manager\Ev\EvSignalManager;
use Icicle\Loop\Manager\Ev\EvSocketManager;
use Icicle\Loop\Manager\Ev\EvTimerManager;

/**
 * Uses the ev extension to poll sockets for I/O and create timers.
 */
class EvLoop extends AbstractLoop
{
    /**
     * @var \EvLoop
     */
    private $loop;

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \EvLoop|null $loop Use null for an EvLoop object to be automatically created.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the event extension is not loaded.
     */
    public function __construct($enableSignals = true, \EvLoop $loop = null)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('ev')) {
            throw new UnsupportedError(__CLASS__ . ' requires the ev extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->loop = $loop ?: new \EvLoop();

        parent::__construct($enableSignals);
    }

    /**
     * @return  \EvLoop
     *
     * @codeCoverageIgnore
     */
    public function getEvLoop()
    {
        return $this->loop;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $flags = \Ev::RUN_ONCE;
        
        if (!$blocking) {
            $flags |= \Ev::RUN_NOWAIT;
        }

        $this->loop->run($flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * Calls loopFork() on the EvLoop object.
     */
    public function reInit()
    {
        $this->loop->loopFork();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager()
    {
        return new EvSocketManager($this, \Ev::READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager()
    {
        return new EvSocketManager($this, \Ev::WRITE);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager()
    {
        return new EvTimerManager($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager()
    {
        return new EvSignalManager($this);
    }
}
