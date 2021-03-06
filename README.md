# jaeger client example ( Java PHP C++ ) ( 非业务侵入 )

## 背景

```
1. 全链路 Trace. 
2. 通过  [[ 分析函数调用栈 ]], 建立树状调用关系 DAG 图, 确定函数层级和 父子\兄弟 调用关系。业务不用关心函数调用关系，自动生成。
```

## 说明

```
1. 用 UDP 发包, 不用等 ACK, 网络层面不影响业务性能.
2. 数据包需要小于 MTU 避免分包, 一般不会超过, 可以 tcpdump 查看每个包大小.
3. 非业务侵入. 在使用的地方嵌入两行代码, 就能 开始\结束 一个 Span.
4. 自动获取分析函数调用栈, 将函数调用关系组织成树状 DAG 图, 树中的 "兄弟\父子" 关系就与 Jager 中的 "Child\Follow" 关系 对应, 简化模型.
5. 为了提升读写效率, 还将树状 DAG 图中的每个节点加入一个 KV Map 中, 实现 O(1) 查询和加入一个 DAG 图节点，对业务性能影响低.
6. PHP 中可使用 debug_backtrace() 获取函数栈, Java 中可以使用 Thread.currentThread().getStackTrace(), Golang 中可通过 Context 获取.
7. 我在 PHP 项目中 运行 debug_backtrace() 一千万次, 耗时 2 秒, 每个操作0.2微秒, 所以代码运行层面也不影响业务性能.
8. 代码量非常少, PHP 的代码总计不到 200 行.
9. PHP 执行环境是单线程, 所以不用考虑并发问题, Java 和 C++ 就需要考虑了, 可通过线程 id 为每个线程生成并关联一个 线程私有 DAG 图.
9. PHP 调用栈一般很浅, Java 由于工具封装较好, 所以调用栈可能很长, 不过只会将调用了 trigger() 和 close() 的函数加入 DAG 图, DAG 大小也是业务可控的, 调用多少次 trigger 生成对应个数节点.
9. DAG 图节点的多少, 是和我们有多少个函数调用 trigger() 一致的(不是调用次数, 相同层次函数虽然可以被调用多次, 或者一个函数中存在多个 trigger, 但是只对应一个 DAG 图节点). 简单点说 就是代码中有多少个函数写了 trigger(), DAG 图中就会有多少个节点, 即所需额外内存大小也是业务可控的.
9. 依赖这个 PHP 项目: composer require jukylin/jaeger-php.
9. 粒度可以细化到函数调用栈, 并且可以在 stop 中增加几行代码, 把所有执行耗时的函数记录到日志中. (比如 网络请求 数据库查询 等)
```

## 使用

```

以 PHP 为例, 下载即可运行.

开始 一个 Span : Trace::trigger();

结束 一个 Span : Trace::close();

```
```
示例如下:

<?php

use \jaeger\Trace;

class Curler {

    public function exe() {
    
    
    
        Trace::trigger();  // 开始记录一个函数，开始 Span
        
                                                              usleep(1000000); // 模拟业务逻辑
        
        Trace::close();    // 结束记录一个函数，结束 Span
        
        
        
    }
}

class Tester {

    private $curler;

    public function __construct() {
    
    
    
        $this->curler = new Curler();
        
        
        
        
        // 初始化 和 注册 清理 函数 用于处理未正确 close 的
        
        
        Trace::init("tester", "xxx.xxx.xxx.xxx:6831"); // jaeger-agent ip
        
        register_shutdown_function(function () {
            Trace::flush();
        });
        
        
        
        
    }

    public function exe() {
    
    
  
        Trace::trigger();  // 开始记录一个函数，开始 Span
        
        
                                                               usleep(1000000);

                                                               for($i = 0; $i < 2; $i++) {
                                                                   $this->exe_i($i);            // exe_i 当中的 Trigger/Close 对应的 Span 是 exe 中的子 Span
                                                               }
                                                  
        
        Trace::close();    // 结束记录一个函数，结束 Span
        
        

    }

    private function exe_i($i) {
    
    

        Trace::trigger();   // 开始记录一个函数，开始 Span
        
                                                                   usleep(1000000);

                                                                   $this->curler->exe();

        Trace::close();     // 结束记录一个函数，结束 Span



    }
}

$tester = new Tester();

$tester->exe();

```

## 扩展

```
1. Operation::stop() 中可以获取当前 span 的 duration, 从而 记录所有操作的耗时, 进一步记录所有慢查询.
```

## 结果


![按服务和操作查询](https://github.com/11061055/jaeger_client_example/blob/master/img/total.png)
