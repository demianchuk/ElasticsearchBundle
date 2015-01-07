<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\ORM;

use ONGR\ElasticsearchBundle\Client\Connection;
use ONGR\ElasticsearchBundle\Document\DocumentInterface;
use ONGR\ElasticsearchBundle\Mapping\MetadataCollector;
use ONGR\ElasticsearchBundle\Result\Converter;

/**
 * Manager class.
 */
class Manager
{
    /**
     * @var Connection Elasticsearch connection.
     */
    private $connection;

    /**
     * @var MetadataCollector
     */
    private $metadataCollector;

    /**
     * @var array Repository structure info.
     */
    private $bundlesMapping = [];

    /**
     * @var array Document type map to repositories.
     */
    private $typesMapping = [];

    /**
     * @var Converter
     */
    private $converter;

    /**
     * @param Connection|null        $connection
     * @param MetadataCollector|null $metadataCollector
     * @param array                  $typesMapping
     * @param array                  $bundlesMapping
     */
    public function __construct($connection, $metadataCollector, $typesMapping, $bundlesMapping)
    {
        $this->connection = $connection;
        $this->metadataCollector = $metadataCollector;
        $this->typesMapping = $typesMapping;
        $this->bundlesMapping = $bundlesMapping;
    }

    /**
     * Returns Elasticsearch connection.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns repository with one or several active selected type.
     *
     * @param string|string[] $type
     *
     * @return Repository
     */
    public function getRepository($type)
    {
        $type = is_array($type) ? $type : [$type];

        foreach ($type as $selectedType) {
            $this->checkRepositoryType($selectedType);
        }

        return $this->createRepository($type);
    }

    /**
     * Creates a repository.
     *
     * @param array $types
     *
     * @return Repository
     */
    private function createRepository(array $types)
    {
        return new Repository($this, $types);
    }

    /**
     * Adds document to next flush.
     *
     * @param DocumentInterface $object
     */
    public function persist(DocumentInterface $object)
    {
        $mapping = $this->getDocumentMapping($object);
        $document = $this->getConverter()->convertToArray($object);

        $this->getConnection()->bulk(
            'index',
            $mapping['type'],
            $document
        );
    }

    /**
     * Commits bulk batch to elasticsearch index.
     */
    public function commit()
    {
        $this->getConnection()->commit();
    }

    /**
     * Flushes elasticsearch index.
     */
    public function flush()
    {
        $this->getConnection()->flush();
    }

    /**
     * Refreshes elasticsearch index.
     */
    public function refresh()
    {
        $this->getConnection()->refresh();
    }

    /**
     * Returns repository metadata for document.
     *
     * @param object $document
     *
     * @return array|null
     */
    public function getDocumentMapping($document)
    {
        foreach ($this->bundlesMapping as $repository) {
            if (in_array(get_class($document), [$repository['namespace'], $repository['proxyNamespace']])) {
                return $repository;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getBundlesMapping()
    {
        return $this->bundlesMapping;
    }

    /**
     * @return MetadataCollector
     */
    public function getMetadataCollector()
    {
        return $this->metadataCollector;
    }

    /**
     * @return array
     */
    public function getTypesMapping()
    {
        return $this->typesMapping;
    }

    /**
     * Checks if specified repository and type is defined, throws exception otherwise.
     *
     * @param string $type
     *
     * @throws \InvalidArgumentException
     */
    private function checkRepositoryType($type)
    {
        if (!array_key_exists($type, $this->bundlesMapping)) {
            $exceptionMessage = "Undefined repository {$type}, valid repositories are: " .
                join(', ', array_keys($this->bundlesMapping)) . '.';
            throw new \InvalidArgumentException($exceptionMessage);
        }
    }

    /**
     * Returns converter instance.
     *
     * @return Converter
     */
    private function getConverter()
    {
        if (!$this->converter) {
            $this->converter = new Converter($this->getTypesMapping(), $this->getBundlesMapping());
        }

        return $this->converter;
    }
}
