<?php

declare(strict_types=1);

namespace Klax\Container\Configuration;

use Closure;
use Klax\Container\Contract\Configuration\ClosureFileLoaderInterface;
use RuntimeException;

class PhpClosureFileLoader implements ClosureFileLoaderInterface
{
    public function load(string $file): Closure
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Services file "%s" does not exist.', $file));
        }

        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Services file "%s" is not readable.', $file));
        }

        $closure = (static fn (string $file): mixed => require($file))($file);
        if (!$closure instanceof Closure) {
            throw new RuntimeException(sprintf('Services file "%s" should be a \Closure.', $file));
        }

        return $closure;
    }
}
