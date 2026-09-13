<?php

namespace Module\Utils\Install;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\Result;
use Module\Utils\AbstractModule;

class Helper
{
    final public static function getAdminPageDirectory(): string
    {
        return Application::getDocumentRoot() . '/bitrix/admin';
    }

    /**
     * @throws InvalidPathException
     */
    public function getModuleAdminPath(bool $notDocumentRoot = false): string
    {
        return $this->moduleClass::getModulePath($notDocumentRoot) . '/admin';
    }

    /**
     * @throws InvalidPathException
     */
    public function getModuleInstallPath(bool $notDocumentRoot = false): string
    {
        return $this->moduleClass::getModulePath($notDocumentRoot) . '/install';
    }

    /**
     * @throws InvalidPathException
     */
    public function getModuleLibPath(bool $notDocumentRoot = false): string
    {
        return $this->moduleClass::getModulePath($notDocumentRoot) . '/lib';
    }

    /**
     * @param class-string<AbstractModule> $moduleClass
     * @throws ArgumentTypeException
     */
    public function __construct(protected string $moduleClass)
    {
        if (!is_subclass_of($moduleClass, AbstractModule::class, true)) {
            throw new ArgumentTypeException('moduleClass', AbstractModule::class);
        }
    }

    /**
     * @return class-string<AbstractModule>
     */
    public function getModuleClass(): string
    {
        return $this->moduleClass;
    }

    public function getModuleId(): string
    {
        return $this->getModuleClass()::getModuleId();
    }

    public static function setError(string $message): void
    {
        global $APPLICATION;
        $APPLICATION->ThrowException($message);
    }

    public static function processResult(Result $result): bool
    {
        if (!$result->isSuccess()) {
            static::setError(implode(', ', $result->getErrorMessages()));
            return false;
        }

        return true;
    }
}