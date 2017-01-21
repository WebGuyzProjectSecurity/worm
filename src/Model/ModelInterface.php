<?php
declare(strict_types=1);

namespace WoohooLabs\Worm\Model;

interface ModelInterface
{
    public function getTable(): string;

    public function getPrimaryKey(): string;

    public function getRelationships(): array;
}
