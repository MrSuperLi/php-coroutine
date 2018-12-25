<?php

// 原文： http://www.laruence.com/2015/05/28/3038.html

include './src/Task.php';
include './src/Scheduler.php';
include './src/SystemCall.php';


function childTask()
{
    $tid = (yield SystemCall::getTaskId());

    while (true) {
        echo "Child task $tid still alive!\n";
        yield;
    }
}

function testChildTask()
{
    $tid = (yield SystemCall::getTaskId());
    $childTid = (yield SystemCall::newTask(childTask()));

    for ($i = 1; $i <= 6; ++$i) {
        echo "Parent task $tid iteration $i.\n";
        yield;

        if ($i == 3) {
            yield SystemCall::killTask($childTid);
        }
    }
}

function testGetTaskId($max) {
    $tid = (yield SystemCall::getTaskId()); // <-- here's the syscall!
    for ($i = 1; $i <= $max; ++$i) {
        echo "This is task $tid iteration $i.\n";
        yield;
    }
}
 
$scheduler = new Scheduler;
 
//$scheduler->newTask(testGetTaskId(10));
//$scheduler->newTask(testGetTaskId(5));

$scheduler->newTask(testChildTask());
 
$scheduler->run();