<?php

namespace Module\Utils\Install;

use Bitrix\Main\Error;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Result;

/**
 * Помощник для установки модулей. Не путать с Module в lib.
 */
class Module extends Helper
{
    public static function isModuleDependencySatisfied(string ...$moduleNames): bool
    {
        return static::processResult(static::checkAndInstallModuleList(...$moduleNames));
    }

    public static function checkAndInstallModuleList(string ...$moduleNames): Result
    {
        foreach ($moduleNames as $moduleName) {
            $checkResult = static::checkAndInstallModule($moduleName);
            if (!$checkResult->isSuccess()) {
                return $checkResult;
            }
        }

        return new Result();
    }

    public static function checkAndInstallModule(string $moduleName, ?string $errorMessage = null): Result
    {
        $result = new Result();
        if (static::hasInstalledModule($moduleName)) {
            return $result;
        }

        if (static::hasModuleInLocalSpace($moduleName)) {
            return static::installModule($moduleName);
        }

        return $result->addError(new Error($errorMessage ?: "Отсутствует модуль $moduleName"));
    }

    public static function hasModuleInLocalSpace(string $moduleName): bool
    {
        return static::getModuleIndexFilePath($moduleName) !== false;
    }

    public static function hasInstalledModule(string $moduleName): bool
    {
        return ModuleManager::isModuleInstalled($moduleName);
    }

    public static function installModule(string $moduleName): Result
    {
        $result = new Result();
        $modulePath = File::getPathWithDocumentRoot(static::getModuleIndexFilePath($moduleName));
        if (!$modulePath) {
            $result->addError(new Error("Отсутствует модуль $moduleName"));
        }
        require_once $modulePath;
        $moduleClass = str_replace('.', '_', $moduleName);

        /** @var \CModule $moduleInstaller */
        $moduleInstaller = new $moduleClass();
        if (!method_exists($moduleInstaller, 'DoInstall')) {
            return $result->addError(new Error("Отсутствует метод для установки модуля $moduleName"));
        }

        global $APPLICATION, $USER, $DB, $step;
        $moduleInstaller->DoInstall();

        if ($e = $APPLICATION->GetException()) {
            return $result->addError(new Error("Ошибка установки модуля $moduleName: " . $e->GetString()));
        }

        return $result;
    }

    public static function getModuleIndexFilePath(string $moduleName): string|false
    {
        return getLocalPath("modules/$moduleName/install/index.php");
    }
}