# 1，概述

本文涉及: 
* WebSocket原理
* 浏览器端语法
* PHP实用WorkerMan实现WebSocket服务器端
* socket.io
* NodeJS下的WebSocket 
* 握手认证流程
* WebSocket协议

本文是第一部分，主要说明外表ok的原基本原理和浏览器服务器端基本语法，然后使用一个案例展示，浏览器端和服务器端如何通讯. 深入学习原理和使用，请看后续部分.

WebSocket, 一个用来解决 一个用来解BS架构当中B/S架构中, 浏览器与服务器端即时通讯的利器. 随HTML5而来, 并在B/S即时通讯领域逐渐成为主流.

Socket什么? 通常的理解就是: "网络上的两个程序通过一个双向的通信连接实现数据的交换，这个连接的一端称为一个socket". 说白了就是两个人打电话, 每个人就是一个socket. 两个socket在打电话.

Websocket, 顾名思义, 就是web上的两个端, 也就是B浏览器端与S服务器端之间的一种通讯协议.

在WebSocket没出现之前, 浏览器端与服务器端的通讯, 主要就是单向的, 永远是浏览器向服务器发送请求, 而服务器给浏览器响应. 也就是服务器不能主动将服务器状态推送到浏览器端, 只能等待浏览器向服务索取. 因此为了能够快速的从服务器端获取状态, 才有了轮询, 长轮询(会在其他篇幅讲解，如有需要可以在公众号输入: ajax轮许, 或者 ajax长轮询)等技术.
WebSocket之后, 浏览器与服务器间的即时通讯就有一个统一的标准, 高效的传输数据了.

# 2，WebSocket原理
如图所示：
webSocket通讯，具体有两个大的步骤来实现：
一，浏览器与服务器在http协议的基础上升级为webSocket通信协议。
二，浏览器与服务器之间，在webSocket协议上进行双向的通讯，直到WebSocket通讯结束。

当webSocket连接建立好之后，浏览器端就可以与服务器端进行双向的数据通讯了。不需要重复的去建立tcp连接。

# 3，代码构建浏览器服务器端的webSocket通讯
如上图所示，想要建立浏览器端与服务器端的webSocket通讯，需要浏览器端先向服务器端发出一个升级为WebSocket的请求，那也就是说，此时需要我们的服务器在等待浏览器发出这个升级协议的请求。因此，我们先构建服务器端的websocket代码，然后在利用浏览器端的webSocket代码与服务器端进行连接通讯。

完整的示例代码，可以github上获取，地址如下：

## 3.1，服务器端的代码，

服务器端的webSocket代码。我们使用非常流行workerMan进行构建(workerMan一个高性能的PHP Socket 服务器框架。可以在公众号中输入workerMan获取相关教程。本例中只涉及如何使用workerMan与浏览器端完成webSocket的通信)。示例代码如下：ws-server.php
    
    ws-server.php
    <?php
    // 导入需要的类
    use Workerman\Worker;
    use Workerman\Lib\Timer;
    // 载入自动加载文件
    require __DIR__ . '/Workerman/Autoloader.php';

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

以上代码，我们就使用了WorkerMan快速的构建了一个监听2346号端口的服务器端socket程序。代码中实现了两件比较重要的事情:
1.我们去监听，客户端发送过来的数据. 通过对象onmessage属性棒的监听. 当客户端向服务器端发送数据时执行onmessage所对应的回调函数，函数中，将接收到的数据打印出来, 我们可以在接下来的测试当中会看到所打印出来的数据.
2.设置一个定时器会，每隔五秒钟执行，执行的功能是向所有连接到该服务器的客户端，发送，hello client和当前时间. 同样这个向浏览器端发送的数据，我们会在浏览器的测试当中看到.

然后在服务器端运行上面的这段脚本：

    $ php ws-server.php start
    ----------------------- WORKERMAN -----------------------------
    Workerman version:3.3.90          PHP version:5.6.20
    ------------------------ WORKERS -------------------------------
    worker        listen                    processes status
    none          websocket://0.0.0.0:2346   4        [OK]
    ----------------------------------------------------------------
    Press Ctrl-C to quit. Start success.

* 在此强调一下，webSocket与workerMan没有直接的关系. 我们在示例代码中使用workerMan只是为了更方便的演示交互效果, 不要将workerMan视为webSocket协议的一部分. workerMan只是实现了webSocket协议. 可以在公众号中输入workerMan获取相关教程. *

## 3.2，浏览器端代码
在浏览器端使用webSocket协议, 我们可以直接使用WebSocket对象来完成通讯.
我们使用下面的代码来实现，与服务器端的通讯: ws-client.html

    ws-client.html
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>webSocket示例A-接受服务器端发送端数据</title>
    </head>
    <body>
    <script>
        // 实例化，浏览器端WebSocket
        // 并使用ws协议连接位于：127.0.0.1:2346端口的webSocket服务器。
        ws = new WebSocket("ws://127.0.0.1:2346");

        // 当服务器端向浏览器发送消息时，执行的回调函数
        ws.onmessage = function(e) {
            console.log("Message from server: " + e.data);
        };

        // 8s后，向服务器发送一个字符串，
        setInterval(function() {
            ws.send('Hello Server! Now is ' + Date());
        }, 3000);
    </script>
    </body>
    </html>

以上代码与服务器端的代码功能类似. 也是先去建立一个webSocket的通信对象，然后去定义当接收到服务器端消息时执行的函数, 以及每隔八秒向服务器发送，的数据的测试.

下面在浏览器端运行该脚本，并打开控制台.

## 3.3 测试结果
如图所示:
我们看到，服务器端的脚本运行命令行与浏览器端的控制台，都有输出. 
服务器接收到的是浏览器每隔三秒发送的hello Server，和当前时间. 而浏览器端接收到的是服务器每隔五秒发送的hello client和当前时间.
从本例我们可以看出，使用webSoekct时服务器端可以主动向浏览器端发送数据，同时浏览器端也可以主动向服务器端发送数据，这样的话就可以做到服务器端与浏览器端即时通讯，而不必非要等到浏览器发出请求服务器端才可以响应，这就是webSocket的一个主要应用场景，即时通讯.


本文是第一部分，主要说明webSocket基本原理和浏览器服务器端基本语法，然后使用一个案例展示，浏览器端和服务器端如何通讯. 深入学习原理和使用，请看后续部分.

# 4, socket.io
官网:https://socket.io/
socket.is是一套比较成熟的ws解决方案，它同时包含了浏览器端和服务器端webSocket协议实现. 而且，同时包含了webSocket在内的多种浏览器端与服务器端即时通讯解决方案. 这就意味着如果我们使用socket.io，那么即使我们的浏览器不支持webSocket协议, 也会使用其他的技术来实现即时通讯而不用去修改客户端和服务器端的代码.# webSocket-basic
