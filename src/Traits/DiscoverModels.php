<?php

namespace Chareka\AutoMigrate\Traits;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

trait DiscoverModels
{
    /**
     * @throws ReflectionException
     */
    public function discoverModels(): Collection
    {
        $models = collect();

        foreach ($this->configuredModelPaths() as $path => $namespace) {
            foreach ($this->resolvePath($path, $namespace) as [$directory, $rootNamespace]) {
                $models->push(...$this->discoverModelsIn($directory, $rootNamespace));
            }
        }

        return $models->unique('name')->sortBy('order')->values();
    }

    /**
     * Config format (config/auto-migrate.php):
     *
     * 'model_paths' => [
     *     app_path('Models')              => 'App\\Models',
     *     app_path('Entities')            => 'App\\Entities',
     *     base_path('Modules/*\/Models')  => 'Modules\\*\\Models',
     * ],
     *
     * Each "*" in the path is a wildcard folder, and is substituted
     * (in order) into the matching "*" in the namespace.
     */
    private function configuredModelPaths(): array
    {
        return config('auto-migrate.model_paths', []);
    }

    /**
     * Expand a (possibly wildcard) path into concrete [directory, namespace] pairs.
     */
    private function resolvePath(string $pattern, string $namespace): array
    {
        $pattern = rtrim(str_replace('\\', '/', $pattern), '/');
        $namespace = trim($namespace, '\\');

        if (!str_contains($pattern, '*')) {
            return is_dir($pattern) ? [[$pattern, $namespace]] : [];
        }

        $patternParts = explode('/', $pattern);
        $resolved = [];

        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $directory) {
            $directory = str_replace('\\', '/', $directory);
            $dirParts = explode('/', $directory);

            // Collect the folder names matched by each wildcard segment
            $matches = [];
            foreach ($patternParts as $i => $part) {
                if (str_contains($part, '*') && isset($dirParts[$i])) {
                    $matches[] = $dirParts[$i];
                }
            }

            // Substitute them into the namespace, in order
            $resolvedNamespace = preg_replace_callback(
                '/\*/',
                static function () use (&$matches) {
                    return array_shift($matches) ?? '';
                },
                $namespace
            );

            $resolved[] = [$directory, $resolvedNamespace];
        }

        return $resolved;
    }

    /**
     * @throws ReflectionException
     */
    private function discoverModelsIn(string $directory, string $rootNamespace): array
    {
        $models = [];

        foreach ((new Finder)->files()->name('*.php')->in($directory) as $file) {
            $relative = str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            $class = $rootNamespace . '\\' . $relative;

            if (!$this->isValidModel($class)) {
                continue;
            }

            $models[] = [
                'name' => $class,
                'object' => $object = app($class),
                'order' => $object->migrationOrder ?? 0,
            ];
        }

        return $models;
    }

    /**
     * @throws ReflectionException
     */
    private function isValidModel($model): bool
    {
        if (!class_exists($model)) {
            return false;
        }

        $reflector = new ReflectionClass($model);

        return $reflector->isInstantiable() && method_exists($model, 'migration');
    }
}