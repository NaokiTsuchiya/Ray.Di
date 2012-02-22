<?php
/**
 * Ray
 *
 * @package Ray.Di
 * @license  http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Ray\Di;

use Doctrine\Common\Annotations\Annotation;

use Ray\Di\Exception;

use Ray\Aop\Bind,
Ray\Aop\Weaver;
/**
 * Dependency Injector.
 *
 * @package Ray.Di
 *
 * @Scope("singleton")
 */
class Injector implements InjectorInterface
{
    /**
     * Config
     *
     * @var Config
     *
     */
    protected $config;

    /**
     * Container
     *
     * @var Container
     */
    protected $container;

    /**
     * Binding module
     *
     * @var AbstractModule
     */
    protected $module;

    /**
     * Pre-destory objects
     *
     * @var SplObjectStorage
     */
    private $preDestroyObjects;

    /**
     * Constructor
     *
     * @param ContainerInterface $container  The class to instantiate.
     * @param AbstractModule     $module Binding configuration module
     */
    public function __construct(ContainerInterface $container, AbstractModule $module = null)
    {
        $this->container = $container;
        $this->config = $container->getForge()->getConfig();
        $this->preDestroyObjects = new \SplObjectStorage;
        if ($module == null) {
            $module = new EmptyModule;
        }
        $this->module = $module;
        $this->bind = new Bind;
    }

    /**
     * Set binding module
     *
     * @param AbstractModule $module
     *
     * @return void
     */
    public function setModule(AbstractModule $module)
    {
        $this->module = $module;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->notifyPreShutdown();
    }

    /**
     * Clone
     */
    public function __clone()
    {
        $this->container = clone $this->container;
    }

    /**
     * Gets the injected Config object.
     *
     * @return ConfigInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get a service object using binding module, optionally with overriding params.
     *
     * @param string $class The class to instantiate.
     * @param AbstractModule binding module
     * @param array $params An associative array of override parameters where
     * the key the name of the constructor parameter and the value is the
     * parameter value to use.
     *
     * @return object
     */
    public function getInstance($class, array $params = null)
    {
        list($config, $setter, $definition) = $this->config->fetch($class);
        // annotation-oriented dependency
        if ($definition->hasNoDefinition()) {
            list($config, $setter) = $this->bindModule($setter, $definition, $this->module);
        }
        //         $params = isset($this->container->params[$class]) ? $this->container->params[$class] : null;
        $params = is_null($params) ? $config : array_merge($config, (array) $params);
        // lazy-load params as needed
        foreach ($params as $key => $val) {
            if ($params[$key] instanceof Lazy) {
                $params[$key] = $params[$key]();
            }
        }

        // create the new instance
        $object = call_user_func_array(
                [$this->config->getReflect($class), 'newInstance'],
                $params
        );
        // call setters after creation
        foreach ($setter as $method => $value) {
            // does the specified setter method exist?
            if (method_exists($object, $method)) {
                if (!is_array($value)) {
                    // call the setter
                    $object->$method($value);
                } else {
                    call_user_func_array([$object, $method], $value);
                }
            }
        }
        $module = $this->module;
        $bind = $module($class, new $this->bind);
        /** @var $bind \BEAR\Di\Bind */
        if ($bind->hasBinding() === true) {
            $object = new Weaver($object, $bind);
        }
        // set life cycle
        if ($definition) {
            $this->setLifeCycle($object, $definition);
        }

        return $object;
    }

    /**
     * Notify pre-destory
     *
     * @return void
     */
    private function notifyPreShutdown()
    {
        $this->preDestroyObjects->rewind();
        while ($this->preDestroyObjects->valid()) {
            $object = $this->preDestroyObjects->current();
            $method = $this->preDestroyObjects->getInfo();
            $object->$method();
            $this->preDestroyObjects->next();
        }
    }

    /**
     * Set object life cycle
     *
     * @param object $instance
     * @param array  $definition
     *
     * @return void
     */
    private function setLifeCycle($instance, Definition $definition = null)
    {
        $isSet = isset($definition[Definition::POST_CONSTRUCT]);
        if ($isSet && method_exists($instance, $definition[Definition::POST_CONSTRUCT])) {
            //signal
            call_user_func(array($instance, $definition[Definition::POST_CONSTRUCT]));
        }
        if (! is_null($definition[Definition::PRE_DESTROY])) {
            $this->preDestroyObjects->attach($instance, $definition[Definition::PRE_DESTROY]);
        }

    }

    /**
     * Return dependency using modules.
     *
     * @param array $setter
     * @param array $definition
     * @param AbstractModule $module
     * @throws Exception\InvalidBinding
     *
     * @return array <$constructorParams, $setter>
     */
    private function bindModule(array $setter, Definition $definition, AbstractModule $module)
    {
        // @return array [AbstractModule::TO => [$toMethod, $toTarget]]
        $container = $this->container;
        $config = $this->container->getForge()->getConfig();
        /* @var $forge Ray\Di\Forge */
        $injector = $this;
        $getInstance = function($in, $bindingToType, $target) use ($container, $definition, $injector) {
            if ($in === Scope::SINGLETON && $container->has($target)) {
                $instance = $container->get($target);
                return $instance;
            }
            switch ($bindingToType) {
                case AbstractModule::TO_CLASS:
                    $instance = $injector->getInstance($target);
                    break;
                case AbstractModule::TO_PROVIDER:
                    $provider = $injector->getInstance($target);
                    $instance = $provider->get();
                    break;
            }
            if ($in === Scope::SINGLETON) {
                $container->set($target, $instance);
            }
            return $instance;
        };
        $bindMethod = function ($item) use ($definition, $module, $getInstance) {
            list($method, $settings) = each($item);
            array_walk($settings, [$this, 'bindOneParameter'], [$definition, $getInstance]);
            return [$method, $settings];
        };

        // main
        $setterDefinitions = (isset($definition[Definition::INJECT][Definition::INJECT_SETTER]))
        ? $definition[Definition::INJECT][Definition::INJECT_SETTER] : false;
        if ($setterDefinitions !== false) {
            $injected = array_map($bindMethod, $setterDefinitions);
            $setter = [];
            foreach ($injected as $item) {
                $setterMethod = $item[0];
                $object =  (count($item[1]) === 1 && $setterMethod !== '__construct') ? $item[1][0] : $item[1];
                $setter[$setterMethod] = $object;
            }
        }
        // constuctor injection ?
        if (isset($setter['__construct'])) {
            $params = $setter['__construct'];
            unset($setter['__construct']);
        } else {
            $params = [];
        }
        return [$params, $setter];
    }

    private function bindOneParameter(&$param, $key, array $userData)
    {
        list($definition, $getInstance) = $userData;
        $annotate = $param[Definition::PARAM_ANNOTATE];
        $typeHint = $param[Definition::PARAM_TYPEHINT];
        $hasTypeHint = isset($this->module[$typeHint])
        && isset($this->module[$typeHint][$annotate])
        && ($this->module[$typeHint][$annotate] !== []);
        $binding = $hasTypeHint ? $this->module[$typeHint][$annotate] : false;
        if ($binding === false || isset($binding[AbstractModule::TO]) === false) {
            // default bindg by @ImplemetedBy or @ProviderBy
            $binding = $this->jitBinding($param, $definition, $typeHint, $annotate);
        }
        $bindingToType = $binding[AbstractModule::TO][0];
        $target = $binding[AbstractModule::TO][1];
        if ($bindingToType === AbstractModule::TO_INSTANCE) {
            $param = $target;
            return;
        } elseif ($bindingToType === AbstractModule::TO_CLOSURE) {
            $param = $target();
            return;
        }
        if (isset($binding[AbstractModule::IN])) {
            $in = $binding[AbstractModule::IN];
        } else {
            list($param, $setter, $definition) = $this->config->fetch($target);
            $in = isset($definition[Definition::SCOPE]) ? $definition[Definition::SCOPE] : Scope::PROTOTYPE;
        }
        $param = $getInstance($in, $bindingToType, $target);
//         return $instance;
    }

    private function jitBinding($param, $definition, $typeHint, $annotate)
    {
        $typehintBy = $param[Definition::PARAM_TYPEHINT_BY];
        if ($typehintBy == []) {
            $typehint = $param[Definition::PARAM_TYPEHINT];
            throw new Exception\InvalidBinding("$typeHint:$annotate");
        }
        if ($typehintBy[0] === Definition::PARAM_TYPEHINT_METHOD_IMPLEMETEDBY) {
            return [AbstractModule::TO => [AbstractModule::TO_CLASS, $typehintBy[1]]];
        }
        return [AbstractModule::TO => [AbstractModule::TO_PROVIDER, $typehintBy[1]]];
    }

    /**
     * Return module information.
     *
     * @return string
     */
    public function __toString()
    {
        $result = (string)($this->module);
        return $result;
    }
}
