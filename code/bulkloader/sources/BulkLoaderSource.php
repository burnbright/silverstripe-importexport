<?php

/**
 * An abstract source to bulk load records from.
 * Provides an iterator for retrieving records from.
 * 
 * Useful for holiding source configuration state.
 */
abstract class BulkLoaderSource implements IteratorAggregate
{

    /**
     * Provide iterator for bulk loading from.
     * Records are expected to be 1 dimensional key-value arrays.
     * @return Iterator
     */
    abstract public function getIterator();
}
