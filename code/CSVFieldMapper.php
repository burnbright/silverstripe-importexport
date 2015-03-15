<?php

/**
 * A visual interface for mapping field names
 */
class CSVFieldMapper extends CSVPreviewer{

	protected $mappablecols;

	public function setMappableCols($cols) {
		$this->mappablecols = $cols;
	}
	
	/**
	 * Provide heading dropdowns for creating mappings
	 * @return ArrayList
	 */
	public function getMapHeadings(){
		if(!$this->headings && !$this->mappablecols) return;

		$out = new Arraylist();
		foreach ($this->headings as $heading) {
			$out->push(new ArrayData(array(
				"Heading" => $heading,
				"Dropdown" => $this->createHeadingDropdown($heading)
			)));
		}

		return $out;
	}

	protected function createHeadingDropdown($heading) {
		return DropdownField::create("headingvals[".$heading."]", 
			"Dropdown", $this->mappablecols
		)->setHasEmptyDefault(true)
		->setEmptyString("Unmapped");
	}

}