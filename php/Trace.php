<?php
/**
 * Created by PhpStorm.
 * User: ssssssssss
 * Date: 2020/05/10
 * Time: 上午11:30
 */

namespace jaeger;

use Jaeger\Config;
use Jaeger\Constants;

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

        $span       = $tracer->startSpan($this->name, $options);
        $this->span = $span;

        $this->started = true;
    }

    public function startAsRoot(&$tracer) {

        // 这里可以 获取 HTTP-X-XYOS-TRACE 设置 对应的 trace id 和 span id, 默认 使用 1000000 1000001
        $context = new \Jaeger\SpanContext(1000000, 1000001, 1);

        $this->start($tracer, [\OpenTracing\Reference::CHILD_OF => $context]);
    }

    public function stop() {

        if (!$this->started) {
            return false;
        }

        foreach ($this->children as $flag => &$operation) {

            $operation->stop();

            //var_dump("del ".spl_object_id($operation)." as child of " .spl_object_id($this));

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

        //var_dump("add ".spl_object_id($childOperation)." as child of " .spl_object_id($this));

        $childOperation->parent = &$this;

        $this->children[$childOperation->flag] = &$childOperation;
    }

    public function startBrotherOperation(&$tracer, &$brotherOperation) {

        $this->stop();

        $context   = $this->span->getContext();

        $this->start($tracer, ["references" => (\OpenTracing\Reference::create(\OpenTracing\Reference::FOLLOWS_FROM, $context))]);

        //var_dump("brother of ". spl_object_id($this). " is ".spl_object_id($brotherOperation));

        if($this->parent == null) {
            return;
        }

        //var_dump("add brother ".spl_object_id($brotherOperation)." of ". spl_object_id($this). " to " .spl_object_id($this->parent));

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

        $stacks = array_reverse($stacks);
        $flags  = self::getOperationFlags($stacks);
        $flags  = array_reverse($flags);

        $presentFlag = $flags[0];

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

                // var_dump("$presentFlag is now in use.");
                self::$_opMapInUse[$presentFlag] = true;

                return;
            }
        }

        $presentOperation->startAsRoot(self::$_tracer);
        // var_dump("$presentFlag is now in use.");
        self::$_opMapInUse[$presentFlag] = true;
    }

    public static function close() {

        $stacks = debug_backtrace();

        if (count($stacks) < 2) {
            return;
        }

        array_shift($stacks);

        $stacks = array_reverse($stacks);
        $flags  = self::getOperationFlags($stacks);
        $flags  = array_reverse($flags);

        $presentFlag = $flags[0];

        $exist = isset(self::$_opMapInUse[$presentFlag]) ? self::$_opMapInUse[$presentFlag] : false;

        if ($exist) {

            $operation = &self::$_opMapAll[$presentFlag];
            $operation->stop();
            // var_dump("$presentFlag is now not in use.");
            unset(self::$_opMapInUse[$presentFlag]);
        }
        self::$_config->flush();
    }

    private static function getOperationFlags($stacks) {

        $prefix    = "";
        $flags     = [];

        foreach ($stacks as $idx => $stack) {

            $name   = $stack['class']."::".$stack['function'];
            $prefix = $prefix."|".$name;
            $flag   = self::genFlagFromStackPrefix($prefix);

            $flags[]= $flag;
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

        // var_dump("new name: $name flag: $flag ".spl_object_id(self::$_opMapAll[$flag]));
    }

    private static function genFlagFromStackPrefix($prefix) {

        $flag = crc32($prefix);
        $flag = 0 - ((strlen($prefix) << 32) + $flag);

        return $flag;
    }
}