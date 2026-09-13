<?php

namespace Module\Utils\Install;


use Bitrix\Main\Error;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use EmptyIterator;
use Iterator;
use Module\Utils\Contracts\ModuleMigrationInterface;

class DB extends Helper
{
    public static function executeSqlFromFile(string $filePath): Result
    {
        $result = new Result();
        $sqlFilePath = File::getPathWithDocumentRoot($filePath);

        global $DB;
        $errorList = $DB->RunSQLBatch($sqlFilePath);

        if (is_array($errorList)) {
            foreach ($errorList as $error) {
                $result->addError(new Error($error));
            }
        }

        return $result;
    }

    public static function runMigrations(string $path, bool $isUp = true): bool
    {
        Loader::requireModule('sprint.migration');
        foreach (static::getMigrationIterator($path) as $migration) {
            $result = $isUp ? $migration->up() : $migration->down();
            if (!static::processResult($result)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $path Путь содержащий классы имплементирующие интерфейс миграций
     * @see ModuleMigrationInterface
     * @return Iterator|ModuleMigrationInterface[]
     */
    public static function getMigrationIterator(string $path): Iterator
    {
        $migrations = [];
        $files = glob("$path/*.php");

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $class = File::getClassFromFile($file);
            if (!is_subclass_of($class, ModuleMigrationInterface::class, true)) {
                continue;
            }

            $migration = new $class();
            $index = $migration->getOrderIndex();
            $migrations[$index][] = $migration;
        }

        // Применяем сортировку по индексам
        ksort($migrations);

        foreach ($migrations as $indexes) {
            foreach ($indexes as $migration) {
                yield $migration;
            }
        }
    }

    /**
     * @throws InvalidPathException
     */
    public function installSql(): bool
    {
        $sqlFilePath = $this->getModuleInstallPath() . '/db/install.sql';
        return static::processResult($this->executeSqlFromFile($sqlFilePath));
    }

    /**
     * @throws InvalidPathException
     */
    public function uninstallSql(): bool
    {
        $sqlFilePath = $this->getModuleInstallPath() . '/db/uninstall.sql';
        return static::processResult($this->executeSqlFromFile($sqlFilePath));
    }

    /**
     * @throws InvalidPathException
     */
    public function installMigrations(): bool
    {
        return static::runMigrations($this->getModuleMigrationsPath(), true);
    }

    /**
     * @throws InvalidPathException
     */
    public function uninstallMigrations(): bool
    {
        return static::runMigrations($this->getModuleMigrationsPath(), false);
    }

    /**
     * @throws InvalidPathException
     */
    protected function getModuleMigrationsPath(): string
    {
        return $this->getModuleInstallPath() . '/migrations';
    }
}