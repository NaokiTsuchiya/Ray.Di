<?php

declare(strict_types=1);

namespace Ray\Di\Annotation;

use Attribute;
use Ray\Di\Di\InjectInterface;
use Ray\Di\Di\Qualifier;

#[Attribute, Qualifier]
final class FakeInjectOne implements InjectInterface
{
    public function isOptional()
    {
        return false;
    }
}
