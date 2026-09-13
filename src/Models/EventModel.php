<?php

namespace Module\Utils\Models;

/**
 * Модель регистрации обработчика стандартных событий Битрикс.
 */
readonly class EventModel
{
    /**
     * @param string $fromModuleId ID модуля, инициирующего событие (например, 'main', 'iblock').
     * @param string $eventType Название события (например, 'OnAfterUserAdd').
     * @param string $toModuleId ID модуля, обрабатывающего событие.
     * @param string $toClass Полное имя класса-обработчика (с неймспейсом).
     * @param string $toMethod Название метода в классе-обработчике.
     * @param int $sort Индекс сортировки для определения порядка выполнения (по умолчанию 100).
     * @param string $toPath Путь к файлу с классом от DOCUMENT_ROOT. Оставлять пустым при использовании автозагрузки (PSR-4).
     * @param array $toMethodArg Дополнительные аргументы. При регистрации массив сериализуется и сохраняется в базу данных b_module_to_module.
     *                           При срабатывании события ядро десериализует этот массив и передает его в параметры вызываемого метода-обработчика.
     *                           Используется для передачи статического контекста (настроек, флагов, идентификаторов).
     */
    public function __construct(
        public string $fromModuleId,
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