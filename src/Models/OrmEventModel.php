<?php

namespace Module\Utils\Models;

/**
 * Модель регистрации обработчика событий ORM (D7)
 */
readonly class OrmEventModel
{
    /**
     * @param string $entity Имя класса таблицы (например, '\Bitrix\Main\UserTable::class').
     * @param string $eventType Тип события. Безопаснее получать через константы DataManager или его наследников (например, \Bitrix\Main\ORM\Data\DataManager::EVENT_ON_BEFORE_ADD).
     * @param string $toModuleId ID модуля, обрабатывающего событие.
     * @param string $toClass Полное имя класса-обработчика (с неймспейсом).
     * @param string $toMethod Название метода в классе-обработчике.
     * @param int $sort Индекс сортировки для определения порядка выполнения (по умолчанию 100).
     * @param string $toPath Путь к файлу с классом от DOCUMENT_ROOT. Оставлять пустым при использовании автозагрузки (PSR-4).
     * @param array $toMethodArg Дополнительные аргументы, передаваемые в метод-обработчик.
     */
    public function __construct(
        public string $entity,
        public string $eventType,
        public string $toModuleId,
        public string $toClass = '',
        public string $toMethod = '',
        public int $sort = 100,
        public string $toPath = '',
        public array $toMethodArg = [],
    ) {
    }
}
