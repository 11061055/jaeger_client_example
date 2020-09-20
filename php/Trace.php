<?php
/**
 * Date: 2020/05/10
 */

namespace jaeger;

use Jaeger\Config;
use Jaeger\Constants;

// Each function is abstracted as an operation, with flag as a unique identity.      每个函数都被抽象成一个 Operation, 由 flag 唯一标志
// And span records all detail messages such as start time and end time.             span 单中记录所有相信信息, 比如 开始时间 结束时间
// All Operations form into a DAG, so parent and children record the relationship.   所有 Operation 组成一个 DAG 图, parent 和 children 记录这种关系

class Operation {

    public $flag;      // int64
    public $name;      // string
    public $span;      // span

    public $parent;         // Operation
    public $children = [];  // Operations

    private $started;

    public function __construct($flag, $name) {
        $this->flag = $flag;
        $this->name = $name;
    }

    public function start(&$tracer, $options = []) {

        $this->stop();

        $span          = $tracer->startSpan($this->name, $options);
        $this->span    = $span;
        $this->started = true;
    }

    public function startAsRoot(&$tracer) {

        // 这里可以 获取 上游 HTTP 头部设置的 trace id 和 span id
        $context = new \Jaeger\SpanContext(1000000, 1000001, 1);
        $this->start($tracer, [\OpenTracing\Reference::CHILD_OF => $context]);
    }

    public function stop() {

        if (!$this->started) {
            return false;
        }
        foreach ($this->children as $flag => &$operation) {
            $operation->stop();
            unset($this->children[$flag]);
        }

        unset($operation);
        $this->span->finish();
        $this->started = false;
    }

    public function equal(Operation &$operation) {

        return (($operation->flag == $this->flag) &&
                ($operation->name == $this->name));
    }

    public function startChildOperation(&$tracer, &$childOperation) {

        $context   = $this->span->getContext();
        $childOperation->start($tracer, [\OpenTracing\Reference::CHILD_OF => $context]);
        $childOperation->parent = &$this;
        $this->children[$childOperation->flag] = &$childOperation;
    }

    public function startBrotherOperation(&$tracer, &$brotherOperation) {

        $this->stop();
        $context   = $this->span->getContext();
        $brotherOperation->start($tracer, ["references" => (\OpenTracing\Reference::create(\OpenTracing\Reference::FOLLOWS_FROM, $context))]);
        if($this->parent == null) {
            return;
        }
        $brotherOperation->parent = &$this->parent;
        $this->parent->children[$brotherOperation->flag] = &$brotherOperation;
    }
}

class Trace
{
    private static $_config;
    private static $_tracer;

    private static $_opMapAll = [];
    private static $_opMapInUse = [];

    public static function init($service, $host)
    {

        $config = \Jaeger\Config::getInstance();
        if (empty($host) || self::$_tracer != null) {
            return;
        }
        self::$_config = $config;
        self::$_tracer = $config->initTracer($service, $host);
    }

    public static function flush() {

        foreach (self::$_opMapInUse as $flag => $bool) {
            self::$_opMapAll[$flag]->stop();
            unset(self::$_opMapInUse[$flag]);
        }
        self::$_config->flush();
    }

    public static function trigger() {

        $stacks = debug_backtrace();
        if (count($stacks) < 2) {
            return;
        }
        array_shift($stacks);
        $presentStack = $stacks[0];
        $stacks       = array_reverse($stacks);
        $flags        = self::getOperationFlags($stacks);
        $flags        = array_reverse($flags);
        $presentFlag  = $flags[0];

        self::genOperation($presentFlag, $presentStack);
        $presentOperation = &self::$_opMapAll[$presentFlag];

        foreach($flags as $idx => $flag) {
            
            $exist = isset(self::$_opMapInUse[$flag]) ? self::$_opMapInUse[$flag] : false;
            if ($exist) {
                $operation = &self::$_opMapAll[$flag];
                if (!($operation->equal($presentOperation))) {
                    $operation->startChildOperation(self::$_tracer, $presentOperation);
                } else {
                    $operation->startBrotherOperation(self::$_tracer, $presentOperation);
                }
                self::$_opMapInUse[$presentFlag] = true;
                return;
            }
        }
        $presentOperation->startAsRoot(self::$_tracer);
        self::$_opMapInUse[$presentFlag] = true;
    }

    public static function close() {

        $stacks = debug_backtrace();
        if (count($stacks) < 2) {
            return;
        }
        array_shift($stacks);
        $stacks      = array_reverse($stacks);
        $flags       = self::getOperationFlags($stacks);
        $flags       = array_reverse($flags);
        $presentFlag = $flags[0];

        $exist = isset(self::$_opMapInUse[$presentFlag]) ? self::$_opMapInUse[$presentFlag] : false;
        if ($exist) {
            $operation = &self::$_opMapAll[$presentFlag];
            $operation->stop();
            unset(self::$_opMapInUse[$presentFlag]);
        }
    }

    private static function getOperationFlags($stacks) {

        $prefix    = "";
        $flags     = [];
        foreach ($stacks as $idx => $stack) {

            $name    = $stack['class']."::".$stack['function'];
            $prefix  = $prefix."|".$name;
            $flag    = self::genFlagFromStackPrefix($prefix);
            $flags[] = $flag;
        }
        return $flags;
    }

    private static function genOperation($flag, $stack) {

        if (isset(self::$_opMapAll[$flag])) {
            return;
        }
        $name   = $stack['class']."::".$stack['function'];
        $name   = explode("\\", $name);
        $name   = $name[count($name) - 1];

        self::$_opMapAll[$flag] = new Operation($flag, $name);
    }

    private static function genFlagFromStackPrefix($prefix) {

        $flag = crc32($prefix);
        $flag = 0 - ((strlen($prefix) << 32) + $flag);

        return $flag;
    }
}
