<?php

namespace Module\Utils;

final class Module extends AbstractModule
{
    public static function getModuleVersion(): string
    {
        return '1.0.0';
    }

    public static function getModuleVersionDate(): string
    {
        return '2026-09-13';
    }

    public static function getModuleName(): string
    {
        return 'Инструменты для модулей';
    }

    public static function getModuleDescription(): string
    {
        return 'Полезный переиспользуемый функционал для реализации модулей битрикса';
    }
}
