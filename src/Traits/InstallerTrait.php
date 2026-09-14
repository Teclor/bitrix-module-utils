<?php

namespace Module\Utils\Traits;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SystemException;
use CAdminMessage;
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
    public function DoUninstall(): void
    {
        global $APPLICATION, $step;
        $step = (int)$step;

        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $saveData = $request->get('saveData') === 'Y';
        $isScript = $request->get('isScriptInstallation') === 'Y' || $request->get('ajax') === 'Y';

        $duties = $this->getInstallDuties();
        $hasDbDuties = in_array(InstallDutiesEnum::SQL, $duties, true) || in_array(InstallDutiesEnum::MIGRATIONS, $duties, true);

        try {
            // Шаг 1: Форма подтверждения
            if ($hasDbDuties && $step < 2 && !$isScript) {
                $this->renderUninstallConfirmationForm();
            }

            // Шаг 2: Фактическое удаление
            if (!$hasDbDuties || $step === 2 || $isScript) {
                foreach ($duties as $duty) {
                    if ($saveData && in_array($duty, [InstallDutiesEnum::SQL, InstallDutiesEnum::MIGRATIONS], true)) {
                        continue;
                    }

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
                        throw new SystemException('Ошибка при выполнении задачи установки: ' . $duty->name);
                    }
                }

                ModuleManager::unRegisterModule($this->MODULE_ID);
                $this->InstallTasks();

                if (!$isScript) {
                    $this->renderUninstallResultForm(true);
                }
            }
        } catch (\Throwable $exception) {
            Helper::setError($exception->getMessage());
            if (!$isScript) {
                $this->renderUninstallResultForm(false);
            }
        }
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

    protected function renderUninstallConfirmationForm(): void
    {
        // Обязательное объявление глобальных переменных для корректной работы файлов ядра Битрикс
        global $USER, $APPLICATION, $DB, $USER_FIELD_MANAGER, $adminPage, $adminMenu, $adminChain;

        $APPLICATION->SetTitle(\Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_WARN") . ' ' . $this->MODULE_NAME);
        require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php";

        ?>
        <form action="<?= $APPLICATION->GetCurPage() ?>">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
            <input type="hidden" name="id" value="<?= htmlspecialcharsbx($this->MODULE_ID) ?>">
            <input type="hidden" name="uninstall" value="Y">
            <input type="hidden" name="step" value="2">

            <?php \CAdminMessage::ShowMessage(\Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_WARN")); ?>

            <p><?= \Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_SAVE") ?></p>
            <p>
                <input type="checkbox" name="saveData" id="saveData" value="Y" checked>
                <label for="saveData"><?= \Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_SAVE_TABLES") ?></label>
            </p>

            <input type="submit" name="inst" value="<?= \Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_DEL") ?>" class="adm-btn-save">
        </form>
        <?php

        require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
        die(); // Прерываем скрипт, чтобы не дать ядру запустить LocalRedirect
    }

    protected function renderUninstallResultForm(bool $isSuccess): void
    {
        global $USER, $APPLICATION, $DB, $USER_FIELD_MANAGER, $adminPage, $adminMenu, $adminChain;

        $APPLICATION->SetTitle('Удаление модуля ' . $this->MODULE_NAME);
        require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php";

        if ($isSuccess) {
            \CAdminMessage::ShowNote(\Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_OK"));
        } else {
            $details = '';
            if ($e = $APPLICATION->GetException()) {
                $details = $e->GetString();
            }
            \CAdminMessage::ShowMessage([
                'TYPE' => 'ERROR',
                'MESSAGE' => \Bitrix\Main\Localization\Loc::getMessage("MOD_UNINST_ERR"),
                'DETAILS' => $details,
                'HTML' => true
            ]);
        }

        ?>
        <form action="<?= $APPLICATION->GetCurPage() ?>">
            <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
            <input type="submit" name="" value="<?= \Bitrix\Main\Localization\Loc::getMessage("MOD_BACK") ?>" class="adm-btn-save">
        </form>
        <?php

        require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
        die();
    }
}