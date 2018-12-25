<?php

/**
 * 协程任务
 */
class Task {

    /**
     * 任务ID
     *
     * @var int
     */
    protected $taskId;

    /**
     * 生成器
     *
     * @var Generator
     */
    protected $coroutine;

    /**
     * 生成器send的参数
     *
     * @var mixed
     */
    protected $sendValue = null;

    /**
     * 是否第一次调用
     *
     * @var boolean
     */
    protected $beforeFirstYield = true;

    public function __construct($taskId, Generator $coroutine)
    {
        $this->taskId = $taskId;
        // $this->coroutine = $coroutine;
        $this->coroutine = $this->stackedCoroutine($coroutine);
    }

    public function getTaskId()
    {
        return $this->taskId;
    }

    public function setSendValue($value)
    {
        $this->sendValue = $value;
    }
    
    public function run()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;
            return $this->coroutine->current();
        } else {
            $retval = $this->coroutine->send($this->sendValue);
            $this->sendValue = null;
            return $retval;
        }
    }

    public function isFinished()
    {
        return ! $this->coroutine->valid();
    }

    // 返回包装好的生成器
    public function stackedCoroutine(Generator $gen) {
        $stack = new SplStack();

        while (true) {
            $value = $gen->current();

            // 如果 yield 的是生成器 则运行这个生成器
            if ($value instanceof Generator) {
                // 先把当前的生成器压入栈，先运行返回的生成器
                $stack->push($gen);
                $gen = $value;
                continue;
            }

            // 生成器返回值
            $isReturnValue = $value instanceof CoroutineReturnValue;

            // 返回的生成器结束 或者 yield 一个CoroutineReturnValue
            if (!$gen->valid() || $isReturnValue) {
                // 查看栈中是否还有生成器
                if ($stack->isEmpty()) {
                    // 堆栈协程运行结束
                    return;
                }

                $gen = $stack->pop();
                $gen->send($isReturnValue ? $value->getValue() : null);
                continue;
            }

            // 如果yield后面不是生成器 不是 CoroutineReturnValue 对象
            // 就返回调度器运行
            $gen->send(yield $gen->key() => $value);
        }
    }
}
