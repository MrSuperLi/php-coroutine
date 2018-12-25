<?php
/**
 * 协程 系统调用
 * 
 * 是给程序的一层操作task和schedule的封装好的API
 * 不给task直接操作schedule
 */
class SystemCall {

    /**
     * 回调
     *
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, Scheduler $schedule)
    {
        $callback = $this->callback;
        return $callback($task, $schedule);
    }

    /* 类似 多进程管理的调用 start */

    // 例如 wait（它一直等待到任务结束运行时), 
    // exec（它替代当前任务)和fork（它创建一个当前任务的克隆). 
    // fork非常酷,而 且你可以使用PHP的协程真正地实现它, 因为它们都支持克隆.
    public static function getTaskId()
    {
        return new self(
            function(Task $task, Scheduler $schedule){
                $task->setSendValue($task->getTaskId());
                $schedule->schedule($task);
            }
        );
    }

    public static function newTask(Generator $coroutine)
    {
        return new self(
            function(Task $task, Scheduler $schedule) use ($coroutine) {
                $task->setSendValue($schedule->newTask($coroutine));
                $schedule->schedule($task);
            }
        );
    }

    public static function killTask($tid)
    {
        return new self(
            function(Task $task, Scheduler $schedule) use($tid) {
                if ($schedule->killTask($tid)) {
                    $schedule->schedule($task);
                } else {
                    // 系统调用出错
                    throw new \Exception('Invalid task ID!');
                }
                
            }
        );
    }
    /* 类似 多进程管理的调用 end */

    /* 非阻塞IO */
    public static function waitForRead($socket) {
        return new self(
            function(Task $task, Scheduler $scheduler) use ($socket) {
                $scheduler->waitForRead($socket, $task);
            }
        );
    }
     
    public static function waitForWrite($socket) {
        return new self(
            function(Task $task, Scheduler $scheduler) use ($socket) {
                $scheduler->waitForWrite($socket, $task);
            }
        );
    }
    /* 非阻塞IO */


}