<?php
declare(strict_types=1);

namespace WoohooLabs\Worm\Model\Relationship;

use WoohooLabs\Larva\Query\Condition\ConditionBuilder;
use WoohooLabs\Larva\Query\Condition\ConditionBuilderInterface;
use WoohooLabs\Worm\Execution\IdentityMap;
use WoohooLabs\Worm\Execution\Persister;
use WoohooLabs\Worm\Model\ModelInterface;

abstract class AbstractRelationship implements RelationshipInterface
{
    /**
     * @var ModelInterface
     */
    protected $parentModel;

    /**
     * @var bool
     */
    protected $cascadedDelete;

    public function __construct(ModelInterface $parentModel, bool $isCascadedDelete)
    {
        $this->parentModel = $parentModel;
        $this->cascadedDelete = $isCascadedDelete;
    }

    public function getParentModel(): ModelInterface
    {
        return $this->parentModel;
    }

    public function cascadeDelete(Persister $persister, string $relationshipName, $parentId): void
    {
        if ($this->cascadedDelete === false) {
            return;
        }

        $identityMap = $persister->getIdentityMap();
        $relatedIds = $identityMap->getRelatedIds($this->parentModel->getTable(), $parentId, $relationshipName);

        foreach ($relatedIds as $relatedHash => $relatedId) {
            $type = $this->getModel()->getTable();
            $identityMap->setState($type, $relatedHash, IdentityMap::STATE_DELETED);
            $identityMap->setObject($type, $relatedHash, null);
            $identityMap->removeRelatedIdentity($type, $parentId, $relationshipName, $relatedHash);
            $this->getModel()->cascadeDelete($persister, $relatedId);
        }
    }

    protected function getWhereCondition(
        array $entities,
        string $entityKey,
        string $prefix,
        string $foreignKey
    ): ConditionBuilderInterface {
        $values = [];
        foreach ($entities as $entity) {
            if (isset($entity[$entityKey])) {
                $values[] = $entity[$entityKey];
            }
        }

        if (empty($values)) {
            $values[] = null;
        }

        return ConditionBuilder::create()
            ->inValues($foreignKey, $values, $prefix);
    }

    protected function insertOneRelationship(
        array $entities,
        string $relationshipName,
        array $relatedEntities,
        string $foreignKey,
        string $field,
        IdentityMap $identityMap
    ): array {
        $relatedEntityMap = $this->getEntityMapForOne($relatedEntities, $foreignKey);

        foreach ($entities as $key => $entity) {
            // Check if the entity has related entities
            if (isset($relatedEntityMap[$entity[$field]]) === false) {
                continue;
            }

            $relatedEntity = $relatedEntityMap[$entity[$field]];

            // Add the related entity to the entity
            $entities[$key][$relationshipName] = $relatedEntity;

            // Add the related entity to the identity map
            $this->addOneToEntityMap($identityMap, $relationshipName, $entity, $relatedEntity);
        }

        return $entities;
    }

    protected function insertManyRelationship(
        array $entities,
        string $relationshipName,
        array $relatedEntities,
        string $foreignKey,
        string $field,
        IdentityMap $identityMap
    ): array {
        $relatedEntityMap = $this->getEntityMapForMany($relatedEntities, $foreignKey);

        foreach ($entities as $key => $entity) {
            // Check if the entity has related entities
            if (isset($relatedEntityMap[$entity[$field]]) === false) {
                continue;
            }

            $relationship = $relatedEntityMap[$entity[$field]];

            // Add related entities to the entity
            $entities[$key][$relationshipName] = $relationship;

            // Add related entities to the identity map
            $this->addManyToEntityMap($identityMap, $relationshipName, $entity, $relationship);
        }

        return $entities;
    }

    protected function addManyToEntityMap(
        IdentityMap $identityMap,
        string $relationshipName,
        array $entity,
        array $relatedEntities
    ) {
        foreach ($relatedEntities as $relatedEntity) {
            $this->addOneToEntityMap($identityMap, $relationshipName, $entity, $relatedEntity);
        }
    }

    protected function addOneToEntityMap(
        IdentityMap $identityMap,
        string $relationshipName,
        array $entity,
        array $relatedEntity
    ): void {
        $relatedEntityType = $this->getModel()->getTable();
        $relatedEntityHash = $this->getModel()->getHash($relatedEntity);
        $relatedEntityId = $this->getModel()->getId($relatedEntity);
        if ($relatedEntityHash === "") {
            return;
        }

        // Add related entity to the identity map
        $identityMap->addIdentity($relatedEntityType, $relatedEntityHash);

        // Add relationship to the identity map
        $identityMap->addRelatedIdentity(
            $this->parentModel->getTable(),
            $this->parentModel->getHash($entity),
            $relationshipName,
            $relatedEntityType,
            $relatedEntityHash,
            $relatedEntityId
        );
    }

    private function getEntityMapForOne(array $entities, string $field): array
    {
        $entityMap = [];
        foreach ($entities as $entity) {
            if (isset($entity[$field]) === false) {
                continue;
            }

            $entityMap[$entity[$field]] = $entity;
        }

        return $entityMap;
    }

    private function getEntityMapForMany(array $entities, string $field): array
    {
        $entityMap = [];
        foreach ($entities as $entity) {
            if (isset($entity[$field]) === false) {
                continue;
            }

            $entityMap[$entity[$field]][] = $entity;
        }

        return $entityMap;
    }
}
