<?php
use Workerman\Worker;
use Workerman\Lib\Timer;

require_once __DIR__ . '/Workerman/Autoloader.php';

// 创建一个Worker监听2346端口，使用websocket协议通讯
$ws_worker = new Worker("websocket://0.0.0.0:2346");

// 启动4个进程对外提供服务
$ws_worker->count = 4;

// 当收到客户端发来的数据后返回hello $data给客户端
$ws_worker->onMessage = function($connection, $data)
{
    echo 'Message From client: ', $data , "\n";
};

// 进程启动时设置一个定时器，定时向所有客户端连接发送数据
$ws_worker->onWorkerStart = function($worker)
{
    // 每隔5.0s，向所有的连接客户端，发送当前时间，
    Timer::add(5.0, function() use($worker) 
    {
        // 遍历当前进程所有的客户端连接，发送当前服务器的时间
        foreach($worker->connections as $connection)
        {
            $connection->send('Hello Client! Now is '. date('H:i:s'));
        }
    });
};

// 运行worker
Worker::runAll();