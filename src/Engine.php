<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle;

use function count;
use MeiliSearch\Client;
use MeiliSearch\Exceptions\ApiException;

/**
 * Class Engine.
 */
final class Engine
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Add new objects to an index.
     * This method allows you to create records on your index by sending one or more objects.
     * Each object contains a set of attributes and values, which represents a full record on an index.
     *
     * @param array|SearchableEntity $searchableEntities
     *
     * @throws ApiException
     */
    public function index($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            $searchableEntities = [$searchableEntities];
        }

        $data = [];
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (null === $searchableArray || 0 === \count($searchableArray)) {
                continue;
            }

            $indexName = $entity->getIndexName();

            if (!isset($data[$indexName])) {
                $data[$indexName] = [];
            }

            $data[$indexName][] = $searchableArray + ['objectID' => $entity->getId()];
        }

        $result = [];
        foreach ($data as $indexName => $objects) {
            $result[$indexName] = $this->client
                ->getOrCreateIndex($indexName, ['primaryKey' => 'objectID'])
                ->addDocuments($objects);
        }

        return $result;
    }

    /**
     * Remove objects from an index using their object UIDs.
     * This method enables you to remove one or more objects from an index.
     *
     * @param array|SearchableEntity $searchableEntities
     */
    public function remove($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            $searchableEntities = [$searchableEntities];
        }

        $data = [];
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (null === $searchableArray || 0 === \count($searchableArray)) {
                continue;
            }
            $indexName = $entity->getIndexName();

            if (!isset($data[$indexName])) {
                $data[$indexName] = [];
            }

            $data[$indexName][] = $entity->getId();
        }

        $result = [];
        foreach ($data as $indexName => $objects) {
            $result[$indexName] = $this->client
                ->index($indexName)
                ->deleteDocument(\reset($objects));
        }

        return $result;
    }

    /**
     * Clear the records of an index.
     * This method enables you to delete an index’s contents (records).
     *
     * @throws ApiException
     */
    public function clear(string $indexName): array
    {
        $index = $this->client->getOrCreateIndex($indexName);
        $return = $index->deleteAllDocuments();

        return $index->getUpdateStatus($return['updateId']);
    }

    public function delete(string $indexName): ?array
    {
        return $this->client->deleteIndex($indexName);
    }

    /**
     * Method used for querying an index.
     */
    public function search(string $query, string $indexName, array $searchParams): array
    {
        if ('' === $query) {
            $query = null;
        }

        return $this->client->index($indexName)->rawSearch($query, $searchParams);
    }

    /**
     * Search the index and returns the number of results.
     */
    public function count(string $query, string $indexName, array $requestOptions): int
    {
        return (int) $this->client->index($indexName)->search($query, $requestOptions)['nbHits'];
    }
}
