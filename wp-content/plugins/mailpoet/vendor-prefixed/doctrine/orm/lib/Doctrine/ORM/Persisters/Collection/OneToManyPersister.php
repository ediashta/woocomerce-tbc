<?php
 namespace MailPoetVendor\Doctrine\ORM\Persisters\Collection; if (!defined('ABSPATH')) exit; use MailPoetVendor\Doctrine\Common\Collections\Criteria; use MailPoetVendor\Doctrine\DBAL\Types\Type; use MailPoetVendor\Doctrine\ORM\PersistentCollection; use MailPoetVendor\Doctrine\ORM\Utility\PersisterHelper; class OneToManyPersister extends \MailPoetVendor\Doctrine\ORM\Persisters\Collection\AbstractCollectionPersister { public function delete(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection) { $mapping = $collection->getMapping(); if (!$mapping['orphanRemoval']) { return; } $targetClass = $this->em->getClassMetadata($mapping['targetEntity']); return $targetClass->isInheritanceTypeJoined() ? $this->deleteJoinedEntityCollection($collection) : $this->deleteEntityCollection($collection); } public function update(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection) { return; } public function get(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection, $index) { $mapping = $collection->getMapping(); if (!isset($mapping['indexBy'])) { throw new \BadMethodCallException("Selecting a collection by index is only supported on indexed collections."); } $persister = $this->uow->getEntityPersister($mapping['targetEntity']); return $persister->load([$mapping['mappedBy'] => $collection->getOwner(), $mapping['indexBy'] => $index], null, $mapping, [], null, 1); } public function count(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection) { $mapping = $collection->getMapping(); $persister = $this->uow->getEntityPersister($mapping['targetEntity']); $criteria = new \MailPoetVendor\Doctrine\Common\Collections\Criteria(\MailPoetVendor\Doctrine\Common\Collections\Criteria::expr()->eq($mapping['mappedBy'], $collection->getOwner())); return $persister->count($criteria); } public function slice(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection, $offset, $length = null) { $mapping = $collection->getMapping(); $persister = $this->uow->getEntityPersister($mapping['targetEntity']); return $persister->getOneToManyCollection($mapping, $collection->getOwner(), $offset, $length); } public function containsKey(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection, $key) { $mapping = $collection->getMapping(); if (!isset($mapping['indexBy'])) { throw new \BadMethodCallException("Selecting a collection by index is only supported on indexed collections."); } $persister = $this->uow->getEntityPersister($mapping['targetEntity']); $criteria = new \MailPoetVendor\Doctrine\Common\Collections\Criteria(); $criteria->andWhere(\MailPoetVendor\Doctrine\Common\Collections\Criteria::expr()->eq($mapping['mappedBy'], $collection->getOwner())); $criteria->andWhere(\MailPoetVendor\Doctrine\Common\Collections\Criteria::expr()->eq($mapping['indexBy'], $key)); return (bool) $persister->count($criteria); } public function contains(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection, $element) { if (!$this->isValidEntityState($element)) { return \false; } $mapping = $collection->getMapping(); $persister = $this->uow->getEntityPersister($mapping['targetEntity']); $criteria = new \MailPoetVendor\Doctrine\Common\Collections\Criteria(\MailPoetVendor\Doctrine\Common\Collections\Criteria::expr()->eq($mapping['mappedBy'], $collection->getOwner())); return $persister->exists($element, $criteria); } public function loadCriteria(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection, \MailPoetVendor\Doctrine\Common\Collections\Criteria $criteria) { throw new \BadMethodCallException("Filtering a collection by Criteria is not supported by this CollectionPersister."); } private function deleteEntityCollection(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection) { $mapping = $collection->getMapping(); $identifier = $this->uow->getEntityIdentifier($collection->getOwner()); $sourceClass = $this->em->getClassMetadata($mapping['sourceEntity']); $targetClass = $this->em->getClassMetadata($mapping['targetEntity']); $columns = []; $parameters = []; foreach ($targetClass->associationMappings[$mapping['mappedBy']]['joinColumns'] as $joinColumn) { $columns[] = $this->quoteStrategy->getJoinColumnName($joinColumn, $targetClass, $this->platform); $parameters[] = $identifier[$sourceClass->getFieldForColumn($joinColumn['referencedColumnName'])]; } $statement = 'DELETE FROM ' . $this->quoteStrategy->getTableName($targetClass, $this->platform) . ' WHERE ' . \implode(' = ? AND ', $columns) . ' = ?'; return $this->conn->executeUpdate($statement, $parameters); } private function deleteJoinedEntityCollection(\MailPoetVendor\Doctrine\ORM\PersistentCollection $collection) { $mapping = $collection->getMapping(); $sourceClass = $this->em->getClassMetadata($mapping['sourceEntity']); $targetClass = $this->em->getClassMetadata($mapping['targetEntity']); $rootClass = $this->em->getClassMetadata($targetClass->rootEntityName); $tempTable = $this->platform->getTemporaryTableName($rootClass->getTemporaryIdTableName()); $idColumnNames = $rootClass->getIdentifierColumnNames(); $idColumnList = \implode(', ', $idColumnNames); $columnDefinitions = []; foreach ($idColumnNames as $idColumnName) { $columnDefinitions[$idColumnName] = ['notnull' => \true, 'type' => \MailPoetVendor\Doctrine\DBAL\Types\Type::getType(\MailPoetVendor\Doctrine\ORM\Utility\PersisterHelper::getTypeOfColumn($idColumnName, $rootClass, $this->em))]; } $statement = $this->platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' (' . $this->platform->getColumnDeclarationListSQL($columnDefinitions) . ')'; $this->conn->executeUpdate($statement); $query = $this->em->createQuery(' SELECT t0.' . \implode(', t0.', $rootClass->getIdentifierFieldNames()) . ' FROM ' . $targetClass->name . ' t0 WHERE t0.' . $mapping['mappedBy'] . ' = :owner')->setParameter('owner', $collection->getOwner()); $statement = 'INSERT INTO ' . $tempTable . ' (' . $idColumnList . ') ' . $query->getSQL(); $parameters = \array_values($sourceClass->getIdentifierValues($collection->getOwner())); $numDeleted = $this->conn->executeUpdate($statement, $parameters); $classNames = \array_merge($targetClass->parentClasses, [$targetClass->name], $targetClass->subClasses); foreach (\array_reverse($classNames) as $className) { $tableName = $this->quoteStrategy->getTableName($this->em->getClassMetadata($className), $this->platform); $statement = 'DELETE FROM ' . $tableName . ' WHERE (' . $idColumnList . ')' . ' IN (SELECT ' . $idColumnList . ' FROM ' . $tempTable . ')'; $this->conn->executeUpdate($statement); } $statement = $this->platform->getDropTemporaryTableSQL($tempTable); $this->conn->executeUpdate($statement); return $numDeleted; } } 