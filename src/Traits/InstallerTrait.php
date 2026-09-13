<?php

namespace Module\Utils\Traits;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SystemException;
use Module\Utils\AbstractModule;
use Module\Utils\Exceptions\FileNotFoundException;
use Module\Utils\Exceptions\ClassNotFoundException;
use Module\Utils\Exceptions\InstallDutyException;
use Module\Utils\Install\Composer;
use Module\Utils\Install\DB;
use Module\Utils\Install\Events;
use Module\Utils\Install\File;
use Module\Utils\Install\Helper;
use Module\Utils\Install\InstallDutiesEnum;
use Module\Utils\Install\Module;
use Module\Utils\Models\EventModel;
use Module\Utils\Models\OrmEventModel;

trait InstallerTrait
{
    /** @var Helper[] */
    protected array $helpers = [];

    /** @var class-string<AbstractModule> $moduleClass */
    protected string $moduleClass;
    /**
     * @param class-string<AbstractModule>|null $moduleClass
     * @return void
     */
    public function initModule(string $moduleClass = null): void
    {
        try {
            if ($moduleClass === null) {
                $moduleClass = $this->getDefaultModuleClass();
            }

            $this->moduleClass = $moduleClass;
            $this->moduleClass::setModuleId(str_replace("_", ".", get_class($this)));

            $this->MODULE_ID = $this->moduleClass::getModuleId();
            $this->MODULE_VERSION = $this->moduleClass::getModuleVersion();
            $this->MODULE_NAME = $this->moduleClass::getModuleName();
            $this->MODULE_DESCRIPTION = $this->moduleClass::getModuleDescription();
            $this->MODULE_VERSION_DATE = $this->moduleClass::getModuleVersionDate();
        } catch (\Throwable $e) {
            Helper::setError($e->getMessage());
        }
    }

    /**
     * @throws ArgumentException
     * @throws InstallDutyException
     * @throws InvalidPathException
     * @throws SystemException
     */
    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        foreach ($this->getInstallDuties() as $duty) {
            /** @var Helper|File|Events|DB|Module|Composer $helper */
            $helper = $this->getHelper($duty);
            $installResult = match ($duty) {
                InstallDutiesEnum::MODULES => $helper::isModuleDependencySatisfied(...$this->moduleClass::getRequiredModuleNames()),
                InstallDutiesEnum::COMPOSER => $helper->installDependencies(),
                InstallDutiesEnum::ADMIN => $helper->installAdminFiles(),
                InstallDutiesEnum::FILES => $helper->installFiles(),
                InstallDutiesEnum::EVENTS => $helper::registerEvents(...$this->getEvents()),
                InstallDutiesEnum::ORM_EVENTS => $helper::registerOrmEvents(...$this->getOrmEvents()),
                InstallDutiesEnum::SQL => $helper->installSql(),
                InstallDutiesEnum::MIGRATIONS => $helper->installMigrations(),
                default => false
            };
            if (!$installResult) {
                $this->DoUninstall();
                return false;
            }
        }

        $this->InstallTasks();

        return true;
    }

    /**
     * @throws ArgumentException
     * @throws InstallDutyException
     * @throws InvalidPathException
     * @throws SystemException
     */
    public function DoUninstall()
    {
        foreach ($this->getInstallDuties() as $duty) {
            /** @var Helper|File|Events|DB|Module|Composer $helper */
            $helper = $this->getHelper($duty);
            $installResult = match ($duty) {
                InstallDutiesEnum::ADMIN => $helper->deleteAdminFiles(),
                InstallDutiesEnum::FILES => $helper->deleteFiles(),
                InstallDutiesEnum::EVENTS => $helper::unregisterEvents(...$this->getEvents()),
                InstallDutiesEnum::ORM_EVENTS => $helper::unregisterOrmEvents(...$this->getOrmEvents()),
                InstallDutiesEnum::SQL => $helper->uninstallSql(),
                InstallDutiesEnum::MIGRATIONS => $helper->uninstallMigrations(),
                default => true
            };
            if (!$installResult) {
                return false;
            }
        }

        ModuleManager::unRegisterModule($this->MODULE_ID);
        $this->InstallTasks();

        return true;
    }

    public function getHelper(InstallDutiesEnum $duty): Helper
    {
        return match ($duty) {
            InstallDutiesEnum::ADMIN, InstallDutiesEnum::FILES => $this->getHelperInstance(File::class),
            InstallDutiesEnum::EVENTS, InstallDutiesEnum::ORM_EVENTS => $this->getHelperInstance(Events::class),
            InstallDutiesEnum::SQL, InstallDutiesEnum::MIGRATIONS => $this->getHelperInstance(Db::class),
            InstallDutiesEnum::MODULES => $this->getHelperInstance(Module::class),
            InstallDutiesEnum::COMPOSER => $this->getHelperInstance(Composer::class),
            default => throw new InstallDutyException('Неизвестная задача установки.')
        };
    }

    public function getHelperInstance(string $helperClass): Helper
    {
        if (!isset($this->helpers[$helperClass])) {
            $this->helpers[$helperClass] = new $helperClass($this->moduleClass);
        }

        return $this->helpers[$helperClass];
    }

    /**
     * @return string Имя класса с неймспейсом
     * @throws FileNotFoundException
     * @throws InvalidPathException
     * @throws ClassNotFoundException
     * @throws \ReflectionException
     */
    protected function getDefaultModuleClass(): string
    {
        $reflector = new \ReflectionObject($this);
        $installerDir = dirname($reflector->getFileName());
        $filePath = realpath($installerDir . '/../lib/module.php');

        if (!$filePath) {
            throw new FileNotFoundException(
                modulePath: AbstractModule::getModulePath(true),
                filePath: $filePath
            );
        }

        require_once $filePath;

        foreach (get_declared_classes() as $className) {
            $reflector = new \ReflectionClass($className);
            if ($reflector->getFileName() === $filePath) {
                return $className;
            }
        }

        throw new ClassNotFoundException(
            modulePath: AbstractModule::getModulePath(true),
            classFilePath: $filePath
        );
    }

    /**
     * @return EventModel[]
     */
    public function getEvents(): array
    {
        return [];
    }

    /**
     * @return OrmEventModel[]
     */
    public function getOrmEvents(): array
    {
        return [];
    }

    /**
     * Пример, если нужно установить скрипт для БД и компоненты: [InstallDutiesEnum::SQL, InstallDutiesEnum::FILES]
     * @return InstallDutiesEnum[]
     */
    public function getInstallDuties(): array
    {
        return [];
    }

    public function GetModuleRightList(): array
    {
        return [
            "reference_id" => ["D", "R", "W"],
            "reference" => [
                "[D] доступ запрещен",
                "[R] чтение",
                "[W] запись",
            ],
        ];
    }
}