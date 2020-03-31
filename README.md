# jaeger deploy and client example ( Java PHP C++ )

## 背景

全链路 Trace. 要求用 UDP 发包不影响业务性能.

## 使用

以 java client 为例, 下载即可运行. 其中 JAEGER_AGENT_HOST 是 UDP 包接收地址. 数据包尽量小于 MTU 避免分包.

## 查询


### 按服务和操作简要查询


![按服务和操作查询](https://github.com/11061055/jaeger_client_example/blob/master/img/total.png)


![按服务和操作查询](https://github.com/11061055/jaeger_client_example/blob/master/img/task1_total.png)


### 按 trace id 查询

![按服务和操作查询](https://github.com/11061055/jaeger_client_example/blob/master/img/task1_detail.png)
