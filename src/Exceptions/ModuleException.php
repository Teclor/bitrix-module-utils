<?php

namespace Module\Utils\Exceptions;

class ModuleException extends \Exception
{
    public function __construct($message = "", $code = 0, \Exception $previous = null, protected ?string $modulePath = null)
    {
        if ($this->modulePath !== null) {
            $message .= (empty($message) ? '' : ' ') . 'Расположение модуля: ' . $this->modulePath . '.';
        }
        parent::__construct($message, $code, $previous);
    }

    public function getModulePath(): string
    {
        return $this->modulePath;
    }
}