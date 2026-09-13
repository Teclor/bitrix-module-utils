<?php

namespace Module\Utils\Exceptions;

use Module\Utils\Install\InstallDutiesEnum;

class InstallDutyException extends ModuleException
{
    public function __construct($message = "", $code = 0, \Exception $previous = null, ?string $modulePath = null, protected ?InstallDutiesEnum $duty = null)
    {
        if ($this->duty !== null) {
            if (empty($message)) {
                $message = 'Ошибка выполнения задачи установки.';
            }
            $message .= ' Название задачи: ' . $this->duty->name . '.';
        }

        parent::__construct($message, $code, $previous, $modulePath);
    }

    public function getDuty(): InstallDutiesEnum
    {
        return $this->duty;
    }
}