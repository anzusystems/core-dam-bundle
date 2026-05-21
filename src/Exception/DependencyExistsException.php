<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;

/**
 * Thrown by domain managers when a hard delete would orphan dependent rows. Surfaced to the admin
 * via {@see Handler\DependencyExistsExceptionHandler} (HTTP 422 + `dependencies: string[]`).
 */
final class DependencyExistsException extends Exception
{
    public const string ERROR_MESSAGE = 'dependency_exists_error';

    /**
     * @var list<string>
     */
    private array $dependencies;

    /**
     * @param list<string> $dependencies
     */
    public function __construct(array $dependencies = [])
    {
        parent::__construct(self::ERROR_MESSAGE);
        $this->dependencies = $dependencies;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependency(string $dependency): self
    {
        $this->dependencies[] = $dependency;

        return $this;
    }

    public function hasDependencies(): bool
    {
        return [] !== $this->dependencies;
    }
}
