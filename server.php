<?php

include './Task.php';
include './Scheduler.php';
include './SystemCall.php';

function server($port) {
    echo "Starting server at port $port...\n";
 
    $socket = @stream_socket_server("tcp://localhost:$port", $errNo, $errStr);
    if (!$socket) throw new Exception($errStr, $errNo);
 
    stream_set_blocking($socket, 0);
 
    while (true) {
        // "父协程"接收新连接
        yield SystemCall::waitForRead($socket);
        $clientSocket = stream_socket_accept($socket, 0);

        // "子协程"处理响应
        yield SystemCall::newTask(handleClient($clientSocket));
    }
}
 
function handleClient($socket) {
    yield SystemCall::waitForRead($socket);
    $data = fread($socket, 8192);
 
    $msg = "Received following request:\n\n$data";
    $msgLength = strlen($msg);
 
    $response = <<<RES
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
RES;
 
    yield SystemCall::waitForWrite($socket);
    fwrite($socket, $response);
 
    fclose($socket);
}
 
$scheduler = new Scheduler;
$scheduler->newTask(server(8000));
$scheduler->run();