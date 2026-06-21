<?php

declare(strict_types=1);

namespace Klax\Container;

use Closure;
use Klax\Container\Contract\ContainerInterface;
use Klax\Container\Exception\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as PsrContainer;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

class Container implements ContainerInterface
{
    /** @var array<string, object|string|Closure> */
    protected array $singletons = [];

    /** @var array<string, object> */
    protected array $cached = [];

    /** @var array<string, bool> */
    protected array $processed = [];

    /**
     * @throws ContainerExceptionInterface
     */
    public function set(string $id, null|object|string $service = null): void
    {
        if ($service === $this || $id === PsrContainer::class) {
            throw new ContainerException('Cannot set current container');
        }

        if (null === $service && !class_exists($id)) {
            throw new ContainerException(sprintf('Service "%s" cannot be registered', $id));
        }

        $this->singletons[$id] = $service ?? $id;
    }

    public function get(string $id)
    {
        if ($id === PsrContainer::class && !array_key_exists($id, $this->cached)) {
            $container = $this;

            $this->cached[$id] = static function () use ($container): PsrContainer {
                return $container;
            };
        }

        if (array_key_exists($id, $this->cached)) {
            return $this->cached[$id];
        }

        if (($this->processed[$id] ?? false) === true) {
            throw new ContainerException(sprintf('Circular reference detected for service "%s"', $id));
        }

        if (!array_key_exists($id, $this->singletons)) {
            if (!class_exists($id)) {
                throw new ContainerException(sprintf('Service "%s" not found', $id));
            }

            $this->singletons[$id] = $id;
        }

        $service = $this->singletons[$id];
        if (is_string($service) && array_key_exists($service, $this->cached)) {
            return $this->cached[$id] = $this->cached[$service];
        }

        if (is_object($service) && !($service instanceof Closure)) {
            return $this->cached[$id] = $service;
        }

        if ($service instanceof Closure) {
            return $this->cached[$id] = $service($this);
        }

        if (is_string($service)) {
            $this->processed[$id] = true;

            try {
                $instance = $this->autowire($service);

                $this->cached[$id] = $instance;
                if ($id !== $service) {
                    $this->cached[$service] = $instance;
                }
            } finally {
                unset($this->processed[$id]);
            }
        }

        return $this->cached[$id];
    }

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->cached) || array_key_exists($id, $this->singletons)) {
            return true;
        }

        if (class_exists($id)) {
            return (new ReflectionClass($id))->isInstantiable();
        }

        return false;
    }

    /**
     * @throws ContainerException
     */
    protected function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new ContainerException(sprintf('Service class "%s" does not exists', $id));
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf('Autowire class "%s" is not instantiable', $id));
        }

        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            return new $id();
        }

        try {
            return $reflection->newInstanceArgs(array_map(
                fn (ReflectionParameter $parameter) => $this->resolveArgument($id, $parameter),
                $constructor->getParameters(),
            ));
        } catch (ReflectionException) {
            throw new ContainerException(sprintf(
                'Failed to autowire service "%s"',
                $id,
            ));
        }
    }

    /**
     * @throws ContainerExceptionInterface
     */
    protected function resolveArgument(string $id, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if (null === $type) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(sprintf(
                'Cannot autowire service "%s"',
                $id,
            ));
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw new ContainerException(sprintf(
            'Cannot autowire service "%s"',
            $id,
        ));
    }
}
