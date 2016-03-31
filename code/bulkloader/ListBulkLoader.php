<?php

/**
 * Peforms bulk loading, but works with a given DataList.
 */
class ListBulkLoader extends BetterBulkLoader
{

    /**
     * The list to insert new DataObjects into.
     * @var DataList
     */
    protected $list;

    public function __construct(DataList $list)
    {
        $this->list = $list;
        //TODO: user error if list is null
        parent::__construct($this->list->dataClass());
    }

    public function setList(DataList $list)
    {
        $this->list = $list;

        return $this;
    }

    /**
     * Get the DataList of objects this loader applies to.
     * @return DataList
     */
    public function getDataList()
    {
        return $this->list;
    }

    /**
     * Add records to the list.
     */
    protected function processAll($filepath, $preview = false)
    {
        $iterator = $this->getSource()->getIterator();
        $results = new BetterBulkLoader_Result();
        foreach ($iterator as $record) {
            if ($id = $this->processRecord($record, $this->columnMap, $results, $preview)) {
                $this->list->add($id);
            }
        }

        return $results;
    }

    /**
     * Override the default deleteExistingRecords method.
     */
    public function deleteExistingRecords()
    {
        foreach ($this->list as $item) {
            $item->delete();
            $item->destroy();
        }
    }
}
