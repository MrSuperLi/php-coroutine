<?php

class CoSocket {
    protected $socket;
 
    public function __construct($socket) {
        $this->socket = $socket;
    }
 
    public function accept() {
        yield SystemCall::waitForRead($this->socket);
        yield new CoroutineReturnValue(new self(stream_socket_accept($this->socket, 0)));
    }
 
    public function read($size) {
        yield SystemCall::waitForRead($this->socket);
        yield new CoroutineReturnValue(fread($this->socket, $size));
    }
 
    public function write($string) {
        yield SystemCall::waitForWrite($this->socket);
        fwrite($this->socket, $string);
    }
 
    public function close() {
        @fclose($this->socket);
    }
}
