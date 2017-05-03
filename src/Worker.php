<?php

namespace Sqsd;

class Worker
{
    /**
     * Indicates if the worker should exit.
     *
     * @var bool
     */
    public $shouldQuit = false;
    /**
     * Indicates if the worker is paused.
     *
     * @var bool
     */
    public $paused = false;
    /**
     * @var bool
     */
    public $isLeader = false;
    /**
     * @var Options
     */
    protected $options;

    /**
     * Worker constructor.
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param callable $function
     * @return void
     */
    public function daemon($function)
    {
        while (true) {
            $this->isLeader = $this->isLeaderProcess();

            if (!$this->daemonShouldRun()) {
                $this->pauseWorker();

                continue;
            }

            $function();

            $this->sleep();
            $this->stopIfNecessary();
        }
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @return bool
     */
    protected function daemonShouldRun()
    {
        return !$this->paused;
    }

    /**
     * Pause the worker for the current loop.
     *
     * @return void
     */
    protected function pauseWorker()
    {
        $this->sleep();

        $this->stopIfNecessary();
    }

    /**
     * Stop the process if necessary.
     *
     * @return void
     */
    protected function stopIfNecessary()
    {
        if ($this->shouldQuit) {
            $this->kill();
        }
    }

    /**
     * Kill the process.
     *
     * @param  int $status
     * @return void
     */
    public function kill($status = 0)
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @return void
     */
    public function sleep()
    {
        sleep($this->options->sleep > 0 ? $this->options->sleep : 1);
    }

    /**
     * Determine if this daemon process is the "leader"
     *
     * @return bool
     */
    protected function isLeaderProcess()
    {
        $leaderFile = $this->options->storagePath . '/leader.lock';
        $timeout    = strtotime('-30 seconds');

        if (file_exists($leaderFile)) {
            $pid = file_get_contents($leaderFile);
            if ($pid == getmypid()) {
                return true;
            } else {
                if (filemtime($leaderFile) < $timeout) {
                    unlink($leaderFile);
                }

                return false;
            }
        }

        file_put_contents($leaderFile, getmypid());

        return true;
    }
}