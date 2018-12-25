<?php

/**
 * 协程调度者
 */
class Scheduler {

    protected $maxTaskId = 0;
    protected $taskMap = [];

    protected $waitingForRead = [];
    protected $waitingForWrite = [];

    /**
     * 任务队列
     *
     * @var SplQueue
     */
    protected $taskQueue;

    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    public function newTask(Generator $coroutine)
    {
        $tid = ++ $this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    public function killTask($tid)
    {
        if (! isset($this->taskMap[$tid])) {
            return false;
        }

        unset($this->taskMap[$tid]);

        foreach ($this->taskQueue as $i => $task) {
            if ($task->getTaskId() === $tid) {
                unset($this->taskQueue[$i]);
                break;
            }
        }

        return true;
    }

    public function waitForRead($socket, Task $task)
    {
        if (isset($this->waitingForRead[(int) $socket])) {
            $this->waitingForRead[(int) $socket][1][] = $task;
        } else {
            $this->waitingForRead[(int) $socket] = [$socket, [$task]];
        }
    }

    public function waitForWrite($socket, Task $task)
    {
        if (isset($this->waitingForWrite[(int) $socket])) {
            $this->waitingForWrite[(int) $socket][1][] = $task;
        } else {
            $this->waitingForWrite[(int) $socket] = [$socket, [$task]];
        }
    }

    protected function ioPoll($timeout) {
        $rSocks = [];
        foreach ($this->waitingForRead as list($socket)) {
            $rSocks[] = $socket;
        }
     
        $wSocks = [];
        foreach ($this->waitingForWrite as list($socket)) {
            $wSocks[] = $socket;
        }
     
        $eSocks = []; // dummy
     
        if (!stream_select($rSocks, $wSocks, $eSocks, $timeout)) {
            return;
        }
     
        foreach ($rSocks as $socket) {
            list(, $tasks) = $this->waitingForRead[(int) $socket];
            unset($this->waitingForRead[(int) $socket]);
     
            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }
     
        foreach ($wSocks as $socket) {
            list(, $tasks) = $this->waitingForWrite[(int) $socket];
            unset($this->waitingForWrite[(int) $socket]);
     
            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }
    }

    // 需要在某个地方注册这个任务
    // 例如, 你可以在run()方法的开始增加$this->newTask($this->ioPollTask()).
    // 然后就像其他任务一样每执行完整任务循环一次就执行轮询操作一次（这么做一定不是最好的方法)

    // 只有任务队列为空时,我们才使用null超时,这意味着它一直等到某个套接口准备就绪.如果我们没有这么做,
    // 那么轮询任务将一而再, 再而三的循环运行, 直到有新的连接建立. 
    // 这将导致100%的CPU利用率. 相反, 让操作系统做这种等待会更有效.
    protected function ioPollTask() {
        while (true) {
            if ($this->taskQueue->isEmpty()) {
                $this->ioPoll(null);
            } else {
                $this->ioPoll(0);
            }
            yield;
        }
    }

    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    public function run()
    {
        while (! $this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();

            $retval = $task->run();
  
            // 任务调用了系统指令
            if ($retval instanceof SystemCall) {
                
                try {
                    $retval($task, $this);
                } catch (\Exception $e) {
                    // 系统调用出错 把错误传递给Task
                    $task->setException($e);
                    $this->schedule($task);
                }

                continue;
            }

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
    }

}