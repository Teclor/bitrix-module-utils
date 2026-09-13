<?php

namespace Module\Utils\Install;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\EventManager;
use Bitrix\Main\ORM\EventManager as OrmEventManager;
use Bitrix\Main\SystemException;
use Module\Utils\Models\EventModel;
use Module\Utils\Models\OrmEventModel;

class Events extends Helper
{
    public static function registerEvents(EventModel ...$events): true
    {
        foreach ($events as $event) {
            EventManager::getInstance()->registerEventHandler(
                $event->fromModuleId,
                $event->eventType,
                $event->toModuleId,
                $event->toClass,
                $event->toMethod,
                $event->sort,
                $event->toPath,
                $event->toMethodArg
            );
        }

        return true;
    }

    public static function unregisterEvents(EventModel ...$events): true
    {
        foreach ($events as $event) {
            EventManager::getInstance()->unRegisterEventHandler(
                $event->fromModuleId,
                $event->eventType,
                $event->toModuleId,
                $event->toClass,
                $event->toMethod,
                $event->toPath,
                $event->toMethodArg
            );
        }

        return true;
    }

    /**
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function registerOrmEvents(OrmEventModel ...$ormEvents): true
    {
        foreach ($ormEvents as $ormEvent) {
            OrmEventManager::getInstance()->registerEventHandler(
                $ormEvent->entity,
                $ormEvent->eventType,
                $ormEvent->toModuleId,
                $ormEvent->toClass,
                $ormEvent->toMethod,
                $ormEvent->sort,
                $ormEvent->toPath,
                $ormEvent->toMethodArg
            );
        }

        return true;
    }

    /**
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function unregisterOrmEvents(OrmEventModel ...$ormEvents): true
    {
        foreach ($ormEvents as $ormEvent) {
            OrmEventManager::getInstance()->unRegisterEventHandler(
                $ormEvent->entity,
                $ormEvent->eventType,
                $ormEvent->toModuleId,
                $ormEvent->toClass,
                $ormEvent->toMethod,
                $ormEvent->toPath,
                $ormEvent->toMethodArg
            );
        }

        return true;
    }

    public function createEventModel(string $fromModuleId, string $eventName, string $toClass, string $toMethod): EventModel
    {
        return new EventModel($fromModuleId, $eventName, $this->getModuleId(), $toClass, $toMethod);
    }

    public function createOrmEventModel(string $entityClass, string $eventName, string $toClass, string $toMethod): OrmEventModel
    {
        return new OrmEventModel($entityClass, $eventName, $this->getModuleId(), $toClass, $toMethod);
    }
}