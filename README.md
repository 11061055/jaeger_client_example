# jaeger client example ( Java PHP C++ ) ( 非业务侵入 )

## 背景与说明

```

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
    
        Trace::trigger();
        
        usleep(1000000); // 模拟业务逻辑
        
        Trace::close();
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
  
        Trace::trigger();
        
        usleep(1000000);

        for($i = 0; $i < 2; $i++) {
            $this->exe_i($i);
        }
        
        Trace::close();

    }

    private function exe_i($i) {

        Trace::trigger();
        
        usleep(1000000);

        $this->curler->exe();

        Trace::close();

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
