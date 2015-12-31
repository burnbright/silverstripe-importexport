<?php

use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;

/**
 * CSV file bulk loading source
 */
class CsvBulkLoaderSource extends BulkLoaderSource
{

    protected $filepath;

    protected $delimiter = ',';

    protected $enclosure = '"';

    protected $hasheader = true;

    public function setFilePath($path)
    {
        $this->filepath = $path;

        return $this;
    }

    public function getFilePath()
    {
        return $this->filepath;
    }

    public function setFieldDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    public function getFieldDelimiter()
    {
        return $this->delimiter;
    }

    public function setFieldEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function getFieldEnclosure()
    {
        return $this->enclosure;
    }

    public function setHasHeader($hasheader)
    {
        $this->hasheader = $hasheader;

        return $this;
    }

    public function getHasHeader()
    {
        return $this->hasheader;
    }

    /**
     * Get a new CSVParser using defined settings.
     * @return Iterator
     */
    public function getIterator()
    {
        if (!file_exists($this->filepath)) {
            //TODO: throw exception instead?
            return null;
        }
        $header = $this->hasheader ? $this->getFirstRow() : null;
        $output = array();

        $config = new LexerConfig();
        $config->setDelimiter($this->delimiter);
        $config->setEnclosure($this->enclosure);
        $config->setIgnoreHeaderLine($this->hasheader);

        $interpreter = new Interpreter();
        // Ignore row column count consistency
        $interpreter->unstrict();
        $interpreter->addObserver(function (array $row) use (&$output, $header) {
            if ($header) {
                //create new row using headings as keys
                $newrow = array();
                foreach ($header as $k => $heading) {
                    if (isset($row[$k])) {
                        $newrow[$heading] = $row[$k];
                    }
                }
                $row = $newrow;
            }
            $output[] = $row;
        });

        $lexer = new Lexer($config);
        $lexer->parse($this->filepath, $interpreter);

        return new ArrayIterator($output);
    }

    public function getFirstRow()
    {
        $handle = fopen($this->filepath, 'r');
        $header = fgetcsv(
            $handle,
            0,
            $this->delimiter,
            $this->enclosure
        );
        fclose($handle);

        return $header;
    }
}
