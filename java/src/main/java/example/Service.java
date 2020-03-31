package example;

import java.util.Random;

import io.jaegertracing.Configuration;
import io.jaegertracing.internal.JaegerTracer;
import io.jaegertracing.internal.JaegerSpanContext;
import io.jaegertracing.internal.samplers.ConstSampler;
import io.opentracing.References;
import io.opentracing.Span;
import io.opentracing.util.GlobalTracer;

public class Service {
    
    private Service(String name) {
    	this.registTracer(name);
    }
    
    // 初始化 tracer
    private void registTracer(String name) {

    	// 设置环境变量
    	System.setProperty(Configuration.JAEGER_AGENT_HOST, "192.168.xxx.xxx");
    	System.setProperty(Configuration.JAEGER_SERVICE_NAME, name);
    	System.setProperty(Configuration.JAEGER_REPORTER_LOG_SPANS, "true");
    	System.setProperty(Configuration.JAEGER_SAMPLER_TYPE, ConstSampler.TYPE);
    	System.setProperty(Configuration.JAEGER_SAMPLER_PARAM, "1");
    	
    	Configuration config = Configuration.fromEnv();  
    	JaegerTracer tracer = config.getTracer();
    	
    	// 注册全局tracer
    	GlobalTracer.register(tracer);
    }

    // 用法 1 这种用法 自己建立父根 trace
    private void doTask1() {
    	
    	// 开始父 span, 可自定义多个 KV 形的 tag, 生成随机的 span id 和 traice id
        Span parentSpan = GlobalTracer.get().buildSpan("doTask1").withTag("pkey", "pvalue").start();          
                
        try {  
        	
        		Thread.sleep(5);/*模拟业务逻辑*/
        	
        		// 开始一个子 span      	
        		Span childSpan1 = GlobalTracer.get().buildSpan("mysql1").asChildOf(parentSpan).start();
        		Thread.sleep(5); /*模拟读取mysql的业务逻辑*/
        		// 结束一个子 span 	
        		childSpan1.finish();

        		// 开始另一个子 span       	
        		Span childSpan2 = GlobalTracer.get().buildSpan("mysql2").addReference(References.FOLLOWS_FROM, childSpan1.context()).start();
        		Thread.sleep(5); /*模拟读取mysql的业务逻辑*/
        		// 结束另一个子 span 
        		childSpan2.finish();

        		Thread.sleep(5);/*模拟业务逻辑*/
        	
        } catch (Exception e) {}

        // 结束父 span
        parentSpan.finish();
    }
    
    // 用法 2 这种用法 使用外部的 trace id 作为父 trace id
    private void doTask2() {
        
    	long d = (new Random()).nextLong() / 10;
    	// 设置 trace id 和 span id 等参数, 可以从外部获取, 这里取的随机数.
    	// 整个链路用同一个 trace id, 这里的 span id 可以 改为 使用 调用方 传的 span id
    	JaegerSpanContext ctx = new JaegerSpanContext(d, d + 1, d + 2, (byte)1);
           
    	// start, doTask2操作是外部请求的子 trace
        Span parentSpan = GlobalTracer.get().buildSpan("doTask2").addReference(References.CHILD_OF, ctx).start();  //和asChildOf一样        

        try {  
        	
        		Thread.sleep(5);/*模拟业务逻辑*/
        	
        		// 开始一个子 span      	
        		Span childSpan1 = GlobalTracer.get().buildSpan("mysql1").addReference(References.CHILD_OF, parentSpan.context()).start(); 	     	
        		Thread.sleep(5); /*模拟读取mysql的业务逻辑*/
        		// 结束一个子 span 	
        		childSpan1.finish();

        		// 开始另一个子 span       	
        		Span childSpan2 = GlobalTracer.get().buildSpan("mysql2").addReference(References.FOLLOWS_FROM, childSpan1.context()).start();
        		Thread.sleep(5); /*模拟读取mysql的业务逻辑*/
        		// 结束另一个子 span 
        		childSpan2.finish();
        		
        		Thread.sleep(5);/*模拟业务逻辑*/
        	
        } catch (Exception e) {}
        
        // finish
        parentSpan.finish();
    }

    public static void main(String[] args) {

        Service service = new Service("my_service");

        for (int i = 0; i < 1000; ++ i) {   
        	service.doTask1();
        	//service.doTask2();
        }
    }
}