# jaeger deploy and client example ( Java PHP C++ )

## 背景

```
1. 全链路 Trace. 用 UDP 发包不影响业务性能.
2. 代码侵入性很小. 自动获取函数调用栈, 自动分析是 child of 还是 follow of 关系, 两行代码 开始\结束 一个 span.
```

## 使用

```
以 php 为例, 下载即可运行. 数据包需要小于 MTU 避免分包.

开始 一个 span : \jaeger\Trace::trigger();
结束 一个 span : \jaeger\Trace::close();

示例如下:

class Curler {

    public function exe() {
    
        \jaeger\Trace::trigger();
        usleep(1000000);
        \jaeger\Trace::close();
    }
}

class Tester {

    private $curler;

    public function __construct() {
    
        $this->curler = new Curler();
        
        \jaeger\Trace::init("tester", "xxx.xxx.xxx.xxx:6831"); // jaeger-agent ip
        register_shutdown_function(function () {
            \jaeger\Trace::flush();
        });
    }

    public function exe() {
  
        \jaeger\Trace::trigger();
        
        usleep(1000000);

        for($i = 0; $i < 2; $i++) {
            $this->exe_i($i);
        }
        \jaeger\Trace::close();

    }

    private function exe_i($i) {

        \jaeger\Trace::trigger();
        
        usleep(1000000);

        $this->curler->exe();

        \jaeger\Trace::close();

    }
}

$tester = new Tester();

$tester->exe();

```


## 结果


![按服务和操作查询](https://github.com/11061055/jaeger_client_example/blob/master/img/total.png)
