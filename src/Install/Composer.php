<?php

namespace Module\Utils\Install;

use Bitrix\Main\Error;
use Bitrix\Main\Result;

class Composer extends Helper
{
    public function installDependencies(): bool
    {
        return static::processResult(static::runComposerInstall($this->getModuleClass()::getModulePath()));
    }

    public function runComposerInstall(string $composerJsonDirectory): Result
    {
        $result = new Result();

        $composerJsonPath = $composerJsonDirectory . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return $result;
        }

        $vendorPath = $composerJsonDirectory . "/vendor";
        if (file_exists("$vendorPath/autoload.php")) {
            return $result; // Уже установлено
        }

        if (!function_exists('exec')) {
            return $result->addError(new Error('Функция exec отключена на сервере. Невозможно запустить Composer.'));
        }

        $command = 'cd ' . escapeshellarg($composerJsonDirectory) . ' && composer install --no-dev --optimize-autoloader 2>&1';
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $result->addError(new Error("Ошибка Composer: " . implode("<br>", $output) . ' Код ошибки: ' . $returnCode));
        }

        return $result;
    }
}