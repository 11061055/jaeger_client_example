#include <iostream>

#include <opentracing/tracer.h>
#include <jaegertracing/Config.h>
#include <jaegertracing/Logging.h>
#include <jaegertracing/Tracer.h>
#include <jaegertracing/samplers/Config.h>

using namespace std;

void initTracer()
{
    auto config = jaegertracing::Config(false,
                      			jaegertracing::samplers::Config("const",
                                                                        1.0,
                                                                        "",
                                                                        jaegertracing::samplers::Config::kDefaultMaxOperations,
                                                                        std::chrono::seconds(5)),
                                        jaegertracing::reporters::Config(jaegertracing::reporters::Config::kDefaultQueueSize,
                                                                         std::chrono::seconds(1),
                                                                         false,
                                                                         "x.x.x.x:6831",
                                                                         "")
                                       );
    auto tracer = jaegertracing::Tracer::make("my_service",
                                              config
                                             );

    opentracing::Tracer::InitGlobal(std::static_pointer_cast<opentracing::Tracer>(tracer));
}

int main(int argc, char* argv[])
{
    initTracer();

    auto parentSpan = opentracing::Tracer::Global()->StartSpan("doTask1");

    for (int i = 0; i < 1000; ++i)
    {
        std::cout << i << std::endl;

        auto child1Span = opentracing::Tracer::Global()->StartSpan("mysql1", { opentracing::ChildOf(&parentSpan->context()) } );

        auto child2Span = opentracing::Tracer::Global()->StartSpan("mysql1", { opentracing::ChildOf(&parentSpan->context()) } );
    }

    // Not stricly necessary to close tracer, but might flush any buffered
    // spans. See more details in opentracing::Tracer::Close() documentation.
    opentracing::Tracer::Global()->Close();

    std::cout << "finish" << std::endl;

    return 0;
}
