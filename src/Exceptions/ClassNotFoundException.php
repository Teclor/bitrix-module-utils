<?php

namespace Module\Utils\Exceptions;

class ClassNotFoundException extends NotFoundException
{
    public function __construct($message = "", $code = 0, \Exception $previous = null, ?string $modulePath = null, protected ?string $classFilePath = null)
    {
        if ($this->classFilePath !== null && empty($message)) {
            $message = 'Класс не найден. Расположение файла класса: ' . $this->classFilePath . '.';
        }

        parent::__construct($message, $code, $previous, $modulePath);
    }

    public function getClassFilePath(): string
    {
        return $this->classFilePath;
    }
}