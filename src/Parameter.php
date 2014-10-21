<?php
/**
 * This file is part of the Ray package.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Ray\Di;

final class Parameter
{
    /**
     * @var string
     */
    private $index;

    /**
     * @var bool
     */
    private $isDefaultAvailable;

    /**
     * @var
     */
    private $default;

    /**
     * @param \ReflectionParameter $parameter
     * @param string               $name
     */
    public function __construct(\ReflectionParameter $parameter, $name)
    {
        $interface = $this->getTypeHint($parameter);
        $this->isDefaultAvailable = $parameter->isDefaultValueAvailable();
        if ($this->isDefaultAvailable) {
            $this->default = $parameter->getDefaultValue();
        }
        $this->index = $interface . '-' . $name;
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return string
     */
    private function getTypeHint(\ReflectionParameter $parameter)
    {
        if (defined('HHVM_VERSION')) {
            return $parameter->info['type_hint'];
        }
        $typHint = $parameter->getClass();

        return $typHint instanceof \ReflectionClass ? $typHint->name : '';
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->index;
    }

    /**
     * @return bool
     */
    public function isDefaultAvailable()
    {
        return $this->isDefaultAvailable;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->default;
    }
}
