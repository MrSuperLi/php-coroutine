<?php

function count_to_ten() {
    yield 1;
    yield 2;
    yield from [3, 4];
    yield from new ArrayIterator([5, 6]);
    yield from seven_eight();
    return yield from nine_ten();
}

function seven_eight() {
    yield 7;
    yield from eight();
}

function eight() {
    yield 8;
}

function nine_ten() {
    yield 9;
    return 10;
}

$gen = count_to_ten();
foreach ($gen as $num) {
    echo "$num ";
}

// 最后的那个10，不会通过 send或者current返回。结束的生成器只会返回 null
// 但是可以通过 getReturn 方法获取返回值
echo $gen->getReturn(), "\n";