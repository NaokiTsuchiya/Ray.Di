<?php
/**
 * This file is part of the Ray package.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Ray\Di;

use Ray\Aop\AbstractMatcher;
use Ray\Aop\Matcher;
use Ray\Aop\Pointcut;
use Ray\Aop\PriorityPointcut;

abstract class AbstractModule
{
    /**
     * @var Matcher
     */
    protected $matcher;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var AbstractModule
     */
    protected $lastModule;

    /**
     * @param AbstractModule $module
     */
    public function __construct(
        AbstractModule $module = null
    ) {
        $this->lastModule = $module;
        $this->activate();
        if ($module) {
            $this->container->merge($module->getContainer());
        }
    }

    abstract protected function configure();

    /**
     * @param string $interface
     *
     * @return Bind
     */
    protected function bind($interface = '')
    {
        $bind = new Bind($this->getContainer(), $interface);

        return $bind;
    }

    /**
     * @param AbstractModule $module
     */
    public function install(AbstractModule $module)
    {
        $this->getContainer()->merge($module->getContainer());
    }

    /**
     * @param AbstractModule $module
     */
    public function override(AbstractModule $module)
    {
        $module->getContainer()->merge($this->container);
        $this->container = $module->getContainer();
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        if (! $this->container) {
            $this->activate();
        }
        return $this->container;
    }

    /**
     * @param AbstractMatcher $classMatcher
     * @param AbstractMatcher $methodMatcher
     * @param array           $interceptors
     */
    public function bindInterceptor(AbstractMatcher $classMatcher, AbstractMatcher $methodMatcher, array $interceptors)
    {
        $pointcut = new Pointcut($classMatcher, $methodMatcher, $interceptors);
        $this->container->addPointcut($pointcut);
        foreach ($interceptors as $interceptor) {
            (new Bind($this->container, $interceptor))->to($interceptor)->in(Scope::SINGLETON);
        }
    }

    /**
     * @param AbstractMatcher $classMatcher
     * @param AbstractMatcher $methodMatcher
     * @param array           $interceptors
     */
    public function bindPriorityInterceptor(AbstractMatcher $classMatcher, AbstractMatcher $methodMatcher, array $interceptors)
    {
        $pointcut = new PriorityPointcut($classMatcher, $methodMatcher, $interceptors);
        $this->container->addPointcut($pointcut);
        foreach ($interceptors as $interceptor) {
            (new Bind($this->container, $interceptor))->to($interceptor)->in(Scope::SINGLETON);
        }
    }

    private function activate()
    {
        $this->container = new Container;
        $this->matcher = new Matcher;
        $this->configure();
    }

    /**
     * @param string $interface
     * @param string $newName
     * @param string $sourceName
     * @param string $targetInterface
     */
    public function rename($interface, $newName, $sourceName = Name::ANY, $targetInterface = '')
    {
        $targetInterface = $targetInterface ?: $interface;
        $this->lastModule->getContainer()->move($interface, $sourceName, $targetInterface, $newName);
    }
}
