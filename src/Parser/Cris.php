<?php

namespace FsrioCrawler\Parser;

use FsrioCrawler\DataParserBase;
use FsrioCrawler\Institution;
use FsrioCrawler\InstitutionMatcherInterface;
use FsrioCrawler\Project;

/**
 * Provides a parser for CRIS search results.
 */
class Cris extends DataParserBase {

  /**
   * An Institution Matcher.
   *
   * @var \FsrioCrawler\InstitutionMatcherInterface
   */
  protected $matcher;

  /**
   * The DOMDocument we are encapsulating.
   *
   * @var \DOMDocument
   */
  protected $document;

  /**
   * An array of DOMElements containing the search results data rows.
   *
   * @var \DOMElement[]
   */
  protected $dataRows = NULL;

  /**
   * A list of data columns that contain project metadata.
   *
   * @var array
   */
  protected $dataColumns = [];

  public function __construct($url, InstitutionMatcherInterface $matcher) {
    parent::__construct($url);

    $this->document = new \DOMDocument();

    // Suppress errors during parsing, so we can pick them up after.
    libxml_use_internal_errors(TRUE);

    $this->matcher = $matcher;
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceURL() {
    // (Re)open the provided URL.
    echo 'Opening source.';
    return $this->document->loadHTMLFile($this->url);
  }

  /**
   * {@inheritdoc}
   */
  protected function isSourceOpen() {
    return !empty($this->document->getElementsByTagName('html'));
  }

  /**
   * {@inheritdoc}
   */
  public function nextRow() {
    if (!isset($this->dataRows) && !$this->parseDataRows()) {
      // There is no data to parse.
      return;
    }
    $row = array_shift($this->dataRows);
    if (empty($row)) {
      return;
    }

    // Parse the cells in the row.
    $cells = $row->getElementsByTagName('td');
    $project = new Project();
    foreach ($cells as $key => $cell) {
      // If the column contains project data, set the appropriate project
      // property.
      if ($this->dataColumns[$key]) {
        if ($this->dataColumns[$key] == 'institution') {
          $project->addInstitution($this->parseInstitutionAddress($this->innerHTML($cell)));
        }
        else {
          $project->__set($this->dataColumns[$key], $this->innerHTML($cell));
        }
      }
    }
    $this->currentItem = $project;
  }

  /**
   * Parses the data rows in the document.
   *
   * @return bool
   *   TRUE if the data table was found.
   */
  protected function parseDataRows() {
    $xpath = new \DOMXPath($this->document);
    $rows = $xpath->query("//table[@cellpadding='3']/tr");
    if (empty($rows)) {
      return FALSE;
    }
    foreach ($rows as $row) {
      $this->dataRows[] = $row;
    }
    // Remove the header row.
    $this->parseHeader(array_shift($this->dataRows));
    return TRUE;
  }

  /**
   * Determines the type of project metadata available in the table cells.
   *
   * @param \DOMElement $header
   *   The header row of the data table.
   */
  protected function parseHeader($header) {
    $cells = $header->getElementsByTagName('th');
    foreach ($cells as $key => $cell) {
      switch (trim($cell->nodeValue)) {
        case 'Acc. No.':
          $this->dataColumns[$key] = 'accession_number';
          break;
        case 'Term. Date':
          $this->dataColumns[$key] = 'end_date';
          break;
        case 'Location':
          $this->dataColumns[$key] = 'institution';
          break;
        case 'Investigators':
          $this->dataColumns[$key] = 'investigators';
          break;
        case 'Non-Tech. Summary':
          $this->dataColumns[$key] = 'more_info';
          break;
        case 'Objectives':
          $this->dataColumns[$key] = 'objective';
          break;
        case 'Project No.':
          $this->dataColumns[$key] = 'project_number';
          break;
        case 'ProjectType':
          $this->dataColumns[$key] = 'project_type';
          break;
        case 'Start Date':
          $this->dataColumns[$key] = 'start_date';
          break;
        case 'Title':
          $this->dataColumns[$key] = 'title';
          break;
        default:
          $this->dataColumns[$key] = FALSE;
      }
    }
  }

  /**
   * Parses an Institution address, matching it to an existing one if found.
   *
   * @param string $address
   *   The address of the institution.  The address may contain up to four lines
   *   of text separated by <br> tags.
   *
   * @return \FsrioCrawler\Institution
   *   The Institution.
   */
  protected function parseInstitutionAddress($address) {
    // We can't know which line of the address contains the institution name.
    // Split the address into separate lines and try to match each one.
    $address_parts = explode('<br>', $address);

    // Attempt to get a city name from the address, usually in the last line.
    $last_index = count($address_parts) - 1;
    $city = '';
    if (isset($address_parts[$last_index])) {
      preg_match('/^([\w\s]+),/', $address_parts[$last_index], $matches);
      if (isset($matches[1])) {
        $city = $matches[1];
      }
    }

    foreach ($address_parts as $part) {
      // Make sure "University" is spelled-out.
      if ((strpos($part, 'UNIV') !== FALSE) && (strpos($part, 'UNIVERSITY') === FALSE)) {
        $part = str_replace('UNIV', 'UNIVERSITY', $part);
      }

      // Replace '&amp;' with '&'.
      $part = str_replace('&amp;', '&', $part);

      $id = $this->matcher->match($part, $city);
      if ($id) {
        return new Institution($part, $id);
      }
    }
    return new Institution($address, 0);
  }

  /**
   * Returns the inner HTML of a DOMElement.
   *
   * Also strips unwanted tags from the HTML and trims whitespace before
   * returning.
   *
   * @param \DOMElement $element
   *   The DOMElement.
   *
   * @return string
   *   The inner HTML of the DOMElement.
   */
  protected function innerHTML(\DOMElement $element) {
    $html = '';
    foreach($element->childNodes as $child) {
      $html .= $this->document->saveHTML($child);
    }
    return trim(strip_tags($html, '<b><br><em><i><p><strong>'));
  }

}
