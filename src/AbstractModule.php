<?php

namespace Module\Utils;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Application;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\IO\Path;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

/**
 * Наследником данного класса должен являться класс конкретного модуля
 */
abstract class AbstractModule
{
    protected static string $moduleId;

    abstract public static function getModuleVersion(): string;
    abstract public static function getModuleVersionDate(): string;
    abstract public static function getModuleDescription(): string;

    public static function setModuleId(string $moduleId): void
    {
        static::$moduleId = $moduleId;
    }

    public static function getModuleId(): string
    {
        if (!isset(static::$moduleId)) {
            static::setModuleId(static::getModuleIdByPath());
        }
        return static::$moduleId;
    }

    public static function getModuleName(): string
    {
        return static::getModuleId();
    }

    public static function getSiteId(): string
    {
        return 's1';
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public static function getOption(string $name, mixed $default = null): mixed
    {
        return trim(Option::get(static::getModuleId(), strtolower($name), $default));
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public static function getConfig(string $name, mixed $default = null): mixed
    {
        return Configuration::getInstance(static::getModuleId())->get($name) ?? $default;
    }

    /**
     * @throws InvalidPathException
     */
    public static function getModulePath(bool $notDocumentRoot = false): string
    {
        $childClassReflection = new \ReflectionClass(static::class);
        $childFileDir = dirname($childClassReflection->getFileName());
        $moduleDir = dirname($childFileDir);

        if ($notDocumentRoot) {
            $normalizedDir = Path::normalize($moduleDir);
            $normalizedRoot = Path::normalize(Application::getDocumentRoot());

            return str_replace($normalizedRoot, '', $normalizedDir);
        }

        return $moduleDir;
    }

    /**
     * @throws InvalidPathException
     */
    public static function getModuleIdByPath(): string
    {
        return basename(static::getModulePath());
    }

    /**
     * @return string[]
     */
    public static function getRequiredModuleNames(): array
    {
        return [];
    }

    /**
     * @throws LoaderException
     */
    public static function requireModules(): void
    {
        foreach (static::getRequiredModuleNames() as $moduleName) {
            Loader::requireModule($moduleName);
        }
    }
}
