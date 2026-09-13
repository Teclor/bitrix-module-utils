<?php

namespace Module\Utils\Install;


use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File as IOFile;
use Bitrix\Main\IO\InvalidPathException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class File extends Helper
{
    public static function getClassFromFile(string $filePath): string
    {
        $filePath = static::getPathWithDocumentRoot($filePath);

        $classesBefore = get_declared_classes();
        /**
         * @psalm-suppress UnresolvableInclude
         */
        require_once $filePath;
        $classesAfter = get_declared_classes();

        // 1. Если файл подключен впервые
        $diff = array_diff($classesAfter, $classesBefore);
        if (!empty($diff)) {
            return reset($diff);
        }

        // 2. Если файл уже был подключен ранее
        foreach ($classesAfter as $className) {
            $reflector = new \ReflectionClass($className);
            if ($reflector->getFileName() === $filePath) {
                return $className;
            }
        }

        return '';
    }

    public static function getPathWithDocumentRoot(string $filePath): string
    {
        if (!static::isPathWithDocumentRoot($filePath)) {
            $filePath = Application::getDocumentRoot() . $filePath;
        }

        return realpath($filePath) ?: $filePath;
    }

    public static function isPathWithDocumentRoot(string $filePath): bool
    {
        return str_starts_with($filePath, Application::getDocumentRoot());
    }

    public function getListModuleAdminFiles(array $filesToSkip = []): array
    {
        $listModuleAdminFiles = [];

        $moduleAdminPath = $this->getModuleAdminPath();
        if (Directory::isDirectoryExists($moduleAdminPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($moduleAdminPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                $fileName = $item->getFilename();
                if ($item->isFile() && !in_array($fileName, $filesToSkip)) {
                    $listModuleAdminFiles[] = $fileName;
                }
            }
        }

        return $listModuleAdminFiles;
    }

    public function installAdminFiles(): bool
    {
        $listModuleAdminFiles = $this->getListModuleAdminFiles();
        foreach ($listModuleAdminFiles as $fileName) {
            $putResult = IOFile::putFileContents(
                self::getAdminPageDirectory() . '/' . $this->getModuleId() . '_' . $fileName,
                '<? require($_SERVER["DOCUMENT_ROOT"]."' . $this->getModuleAdminPath(true) . $fileName . '"); ?>',
            );
            if ($putResult === false) {
                static::setError('Не удалось установить файл для админпанели. Название файла: ' . $fileName);
                return false;
            }
        }

        return true;
    }

    public function deleteAdminFiles(): bool
    {
        $listModuleAdminFiles = $this->getListModuleAdminFiles();

        foreach ($listModuleAdminFiles as $fileName) {
            $deleteResult = IOFile::deleteFile(self::getAdminPageDirectory() . '/' . $this->getModuleId() . '_' . $fileName);
            if ($deleteResult === false) {
                static::setError('Не удалось удалить файл из админпанели. Название файла: ' . $fileName);
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidPathException
     */
    public function installFiles(array $directoriesToSkip = ['db', 'migrations']): bool
    {
        $installPath = rtrim($this->getModuleInstallPath(), '/\\');
        $docRoot = rtrim(Application::getDocumentRoot(), '/\\');

        foreach ($this->getModuleInstallDirectoryNames($directoriesToSkip) as $sourceDirName) {
            $result = CopyDirFiles(
                "$installPath/$sourceDirName",
                "$docRoot/$sourceDirName",
                true,
                true
            );
            if (!$result) {
                static::setError('Не удалось установить файлы из директории ' . $sourceDirName);
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidPathException
     */
    public function deleteFiles(array $directoriesToSkip = ['db', 'migrations']): bool
    {
        $installPath = rtrim($this->getModuleInstallPath(), '/\\');
        $docRoot = rtrim(Application::getDocumentRoot(), '/\\');

        foreach ($this->getModuleInstallDirectoryNames($directoriesToSkip) as $sourceDirName) {
            $this->removeModuleFiles(
                "$installPath/$sourceDirName",
                "$docRoot/$sourceDirName"
            );
        }

        return true;
    }

    /**
     * @throws InvalidPathException
     */
    protected function getModuleInstallDirectoryNames(array $directoriesToSkip = ['db', 'migrations']): array
    {
        $directories = [];
        $installPath = $this->getModuleInstallPath();

        if (!is_dir($installPath)) {
            return $directories;
        }

        foreach (scandir($installPath) as $dirName) {
            if (
                $dirName === '.' ||
                $dirName === '..' ||
                in_array($dirName, $directoriesToSkip, true) ||
                !is_dir("$installPath/$dirName")
            ) {
                continue;
            }

            $directories[] = $dirName;
        }

        return $directories;
    }

    protected function removeModuleFiles(string $sourcePath, string $targetPath): void
    {
        if (!file_exists($sourcePath)) {
            return;
        }

        if (is_file($sourcePath)) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            return;
        }

        if (is_dir($sourcePath)) {
            $dir = opendir($sourcePath);
            while (($item = readdir($dir)) !== false) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->removeModuleFiles("$sourcePath/$item", "$targetPath/$item");
            }
            closedir($dir);

            // Удалит директорию только в том случае, если она пуста.
            // Заполненные файлами папки проигнорируются.
            if (is_dir($targetPath)) {
                @rmdir($targetPath);
            }
        }
    }
}
