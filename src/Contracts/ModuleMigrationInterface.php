<?php

namespace Module\Utils\Contracts;

use Bitrix\Main\Result;

interface ModuleMigrationInterface
{
    public function getOrderIndex(): int;
    public function up(): Result;
    public function down(): Result;
}
