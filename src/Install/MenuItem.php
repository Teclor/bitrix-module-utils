<?php

namespace Module\Utils\Install;

/**
 * Хелпер для построения административного меню 1С-Битрикс.
 * Инкапсулирует логику формирования массива параметров меню,
 * предоставляя типизированный интерфейс.
 */
class MenuItem
{
    protected array $item = [];
    /** @var MenuItem[] */
    protected array $subItems = [];

    /**
     * @param string $text Текст пункта меню (название)
     * @param int $sort Индекс сортировки (чем меньше, тем выше пункт)
     */
    public function __construct(string $text, int $sort = 100)
    {
        $this->item['text'] = $text;
        $this->item['sort'] = $sort;
    }

    /**
     * Статический конструктор для удобного построения цепочек (Fluent Interface).
     */
    public static function make(string $text, int $sort = 100): self
    {
        return new self($text, $sort);
    }

    /**
     * Привязка к главному разделу административного меню (используется только для корневых пунктов).
     * Стандартные разделы ядра:
     * - global_menu_content (Контент)
     * - global_menu_settings (Настройки)
     * - global_menu_store (Магазин)
     * - global_menu_services (Сервисы)
     * - global_menu_statistics (Аналитика)
     * - global_menu_marketplace (Маркетплейс)
     */
    public function setParentMenu(string $parentMenu): self
    {
        $this->item['parent_menu'] = $parentMenu;
        return $this;
    }

    /**
     * ID ветки меню. Необходим для сохранения состояния (свернуто/развернуто)
     * и динамической подгрузки дочерних пунктов (AJAX).
     */
    public function setItemsId(string $itemsId): self
    {
        $this->item['items_id'] = $itemsId;
        return $this;
    }

    /**
     * Ссылка на страницу при клике на пункт меню.
     */
    public function setUrl(string $url): self
    {
        $this->item['url'] = $url;
        return $this;
    }

    /**
     * Всплывающая подсказка (title) при наведении курсора на пункт меню.
     */
    public function setTitle(string $title): self
    {
        $this->item['title'] = $title;
        return $this;
    }

    /**
     * ID модуля для автоматической проверки прав доступа (по умолчанию требуется право >= 'R').
     * Если не указано, пункт меню отображается всем пользователям с доступом к админке.
     */
    public function setModuleId(string $moduleId): self
    {
        $this->item['module_id'] = $moduleId;
        return $this;
    }

    /**
     * CSS-класс иконки слева от пункта меню.
     * Популярные иконки ядра:
     * - sys_menu_icon (шестеренка, настройки)
     * - util_menu_icon (утилиты, инструменты)
     * - default_menu_icon (стандартная папка/файл)
     * - iblock_menu_icon_types (иконка инфоблоков)
     * - form_menu_icon (веб-формы)
     * - fileman_menu_icon (структура сайта)
     */
    public function setIcon(string $iconClass): self
    {
        $this->item['icon'] = $iconClass;
        return $this;
    }

    /**
     * CSS-класс большой иконки, отображаемой в заголовке открытой страницы.
     */
    public function setPageIcon(string $pageIconClass): self
    {
        $this->item['page_icon'] = $pageIconClass;
        return $this;
    }

    /**
     * Добавление дополнительных URL.
     * Если администратор находится на странице, URL которой совпадает
     * с одним из добавленных здесь, текущий пункт меню будет подсвечен как активный.
     * Часто используется для страниц редактирования/добавления сущности (например, item_edit.php).
     */
    public function addMoreUrl(string $url): self
    {
        if (!isset($this->item['more_url'])) {
            $this->item['more_url'] = [];
        }
        $this->item['more_url'][] = $url;
        return $this;
    }

    /**
     * Флаг динамического меню (подгрузка подразделов через AJAX по клику).
     * Для работы требуется установленный items_id.
     */
    public function setDynamic(bool $isDynamic = true): self
    {
        $this->item['dynamic'] = $isDynamic;
        return $this;
    }

    /**
     * Добавление дочернего пункта меню (подраздела).
     */
    public function addItem(MenuItem $item): self
    {
        $this->subItems[] = $item;
        return $this;
    }

    /**
     * Сборка итогового массива для возврата в ядро Битрикс.
     */
    public function toArray(): array
    {
        $result = $this->item;

        if (!empty($this->subItems)) {
            $result['items'] = array_map(
                static fn(MenuItem $item) => $item->toArray(),
                $this->subItems
            );
        }

        return $result;
    }
}