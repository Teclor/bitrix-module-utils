<?php

namespace Module\Utils\Install;

enum InstallDutiesEnum
{
    case EVENTS;
    case ORM_EVENTS;

    case ADMIN;
    case FILES;

    case SQL;
    case MIGRATIONS;

    case MODULES;
    case COMPOSER;
}