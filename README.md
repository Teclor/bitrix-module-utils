# Утилиты для разработки модулей (Module Utils)

Набор классов, моделей и хелперов для упрощения разработки, инсталляции и управления настройками модулей 1С-Битрикс. Модуль инкапсулирует рутинную логику создания установочных скриптов, регистрации событий, выполнения миграций и формирования настроек.

## Архитектура и возможности

* **Установка в один клик**: Трейт `InstallerTrait` берет на себя вызов необходимых хелперов (Файлы, БД, Зависимости, События, Composer) на основе декларативного списка задач.
* **Управление событиями**: Регистрация через строго типизированные DTO-модели `EventModel` и `OrmEventModel`.
* **Миграции**: Итератор миграций автоматически находит и выполняет классы, реализующие `ModuleMigrationInterface`.
* **Страница настроек**: Класс `Options` позволяет генерировать `options.php` без дублирования HTML-кода вкладок.
* **Обработка ошибок**: Иерархия исключений, наследуемая от `ModuleException`, включающая `NotFoundException`, `FileNotFoundException`, `ClassNotFoundException` и `InstallDutyException`.

---

## Примеры использования

### 1. Подготовка базового класса модуля
В папке `lib/` создайте основной класс модуля, наследующий `AbstractModule`:

```php
namespace Vendor\Name;

use Module\Utils\AbstractModule;

class Module extends AbstractModule
{
    public static function getSiteId(): string
    {
        return 's1';
    }

    public static function getModuleVersion(): string
    {
        return '1.0.0';
    }

    public static function getModuleDescription(): string
    {
        return 'Описание функционала модуля';
    }

    public static function getRequiredModuleNames(): array
    {
        return ['iblock', 'highloadblock'];
    }
}
```

### 2. Создание инсталлятора (install/index.php)
Используйте `InstallerTrait` для автоматизации методов `DoInstall` и `DoUninstall`.

```php
use Module\Utils\InstallerTrait;
use Module\Utils\Install\InstallDutiesEnum;
use Module\Utils\EventModel;
use Module\Utils\OrmEventModel;

class vendor_name extends CModule
{
    use InstallerTrait;

    public function __construct()
    {
        $this->initModule();
    }

    public function getInstallDuties(): array
    {
        // Указываем, какие шаги нужны при установке
        return [
            InstallDutiesEnum::MODULES,
            InstallDutiesEnum::COMPOSER,
            InstallDutiesEnum::SQL,
            InstallDutiesEnum::MIGRATIONS,
            InstallDutiesEnum::FILES,
            InstallDutiesEnum::EVENTS,
            InstallDutiesEnum::ORM_EVENTS,
        ];
    }

    public function getEvents(): array
    {
        return [
            new EventModel(
                fromModuleId: 'main',
                eventType: 'OnAfterUserAdd',
                toModuleId: $this->MODULE_ID,
                toClass: \Vendor\Name\Handlers\UserHandler::class,
                toMethod: 'onAfterUserAdd'
            )
        ];
    }

    public function getOrmEvents(): array
    {
        return [
            new OrmEventModel(
                entity: \Bitrix\Main\UserTable::class,
                eventType: \Bitrix\Main\ORM\Data\DataManager::EVENT_ON_BEFORE_UPDATE,
                toModuleId: $this->MODULE_ID,
                toClass: \Vendor\Name\Handlers\UserHandler::class,
                toMethod: 'onBeforeUserUpdate'
            )
        ];
    }
}
```

Также можно использовать методы `createEventModel` и `createOrmEventModel` для создания моделей событий в классе Events.

### 3. Миграции базы данных
Поместите классы миграций в директорию `install/migrations/`. Класс должен реализовывать интерфейс `ModuleMigrationInterface`.

```php
namespace Vendor\Name\Install\Migrations;

use Bitrix\Main\Result;
use Module\Utils\Contracts\ModuleMigrationInterface;

class AddBooksIblockMigration implements ModuleMigrationInterface
{
    public function getOrderIndex(): int
    {
        return 100;
    }

    public function up(): Result
    {
        // Логика применения
        return new Result(); 
    }

    public function down(): Result
    {
        // Логика отката
        return new Result();
    }
}
```

### 4. Страница настроек (options.php)
Используйте класс `Options` для построения интерфейса управления параметрами. Код размещается в корне модуля в файле `options.php`.

```php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;
use Module\Utils\Options;
use Vendor\Name\Module;

$moduleId = Module::getModuleId();
Loader::requireModule($moduleId);

$options = new Options($moduleId);

$options
    ->addTab(
        div: 'main_settings',
        tab: 'Основные настройки',
        title: 'Настройка интеграции',
        options: [
            ['api_key', 'API Ключ', '', ['text', 50]],
            ['use_cache', 'Использовать кеширование', 'Y', ['checkbox']],
        ]
    )
    ->addRightsTab();

$options->process();
```

## Исключения (Exceptions)

Модуль предоставляет набор исключений для безопасной работы хелперов установки.
*   `ModuleException` — базовое исключение модуля, хранит путь к модулю.
*   `NotFoundException` — базовое исключение для ненайденных сущностей.
*   `FileNotFoundException` — выбрасывается, если требуемый файл отсутствует на диске.
*   `ClassNotFoundException` — выбрасывается, если файл подключен, но искомый класс в нем отсутствует.
*   `InstallDutyException` — возникает при передаче неизвестной задачи в `getInstallDuties`. Можно также использовать для ошибок в процессе выполнения задач.