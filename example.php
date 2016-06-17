<?php

$redis = new Redis();
$redis->connect("redis");
$redis->set("hello", "world");
echo $redis->get("hello");
