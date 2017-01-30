<?php
declare(strict_types=1);

namespace WoohooLabs\Worm\Examples\Model;

use WoohooLabs\Worm\Model\AbstractModel;

class ClassStudentModel extends AbstractModel
{
    public static $id;
    public static $class_id;
    public static $student_id;

    public function getTable(): string
    {
        return "classes_students";
    }

    public function getPrimaryKey(): string
    {
        return self::$id;
    }

    public function getRelationships(): array
    {
        return [];
    }
}
