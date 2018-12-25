<?php

include './Task.php';
include './Scheduler.php';
include './SystemCall.php';
include './CoroutineReturnValue.php';

function child1_co()
{
    yield 'child1';
    // 返回 CoroutineReturnValue 对象会终止嵌入协程，并且返回给调用它的上级协程
    yield new CoroutineReturnValue('child1 return value');

    print('不会执行这里');
}

function parent_co()
{
    yield 1;
    $ret = yield child1_co();

    var_dump($ret);

    yield new CoroutineReturnValue('dddd');

    var_dump('stop'); // 不会运行
}


$scheduler = new Scheduler();

$scheduler->newTask(parent_co());

$scheduler->run();