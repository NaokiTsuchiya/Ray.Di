<?php

declare(strict_types=1);

namespace Ray\Compiler;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

class FakeDoubleInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation)
    {
        $result = $invocation->proceed();

        return $result * 2;
    }
}
