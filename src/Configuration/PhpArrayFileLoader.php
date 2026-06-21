<?php

declare(strict_types=1);

namespace Klax\Container\Configuration;

use Klax\Container\Contract\Configuration\ArrayFileLoaderInterface;
use RuntimeException;

class PhpArrayFileLoader implements ArrayFileLoaderInterface
{
    public function load(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Services file "%s" does not exist.', $file));
        }

        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Services file "%s" is not readable.', $file));
        }

        $services = (static fn (string $file): mixed => require($file))($file);
        if (!is_array($services)) {
            throw new RuntimeException(sprintf('Services file "%s" should be an array.', $file));
        }

        return $services;
    }
}
