<?php

namespace Module\Utils\Exceptions;

class FileNotFoundException extends NotFoundException
{
    public function __construct($message = "", $code = 0, \Exception $previous = null, ?string $modulePath = null, protected ?string $filePath = null)
    {
        if ($this->filePath !== null && empty($message)) {
            $message = 'Файл не найден. Расположение файла: ' . $this->filePath . '.';
        }

        parent::__construct($message, $code, $previous, $modulePath);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}