<?php

namespace FsrioCrawler\Parser;

use FsrioCrawler\DataParserBase;
use FsrioCrawler\Institution;
use FsrioCrawler\InstitutionInterface;
use FsrioCrawler\InstitutionMatcherInterface;
use FsrioCrawler\Investigator;
use FsrioCrawler\InvestigatorMatcherInterface;
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
  protected $institution_matcher;

  /**
   * An Investigator Matcher.
   *
   * @var \FsrioCrawler\InvestigatorMatcherInterface
   */
  protected $investigator_matcher;

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

  public function __construct($url, InstitutionMatcherInterface $institution_matcher, InvestigatorMatcherInterface $investigator_matcher) {
    parent::__construct($url);

    $this->document = new \DOMDocument();

    // Suppress errors during parsing, so we can pick them up after.
    libxml_use_internal_errors(TRUE);

    $this->institution_matcher = $institution_matcher;
    $this->investigator_matcher = $investigator_matcher;
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
    $institution = NULL;
    $investigators = '';
    foreach ($cells as $key => $cell) {
      // If the column contains project data, set the appropriate project
      // property.
      if ($this->dataColumns[$key]) {
        // Institutions are added to an array in Project objects and must be
        // handled separately from other properties.
        if ($this->dataColumns[$key] == 'institution') {
          $institution = $this->parseInstitutionAddress($this->innerHTML($cell));
          if ($institution) {
            $project->addInstitution($institution);
          }
          // No matching Institution was found.  Append the address to the
          // project's comments for manual entry.
          else {
            $address = str_replace('<br>', "\n", $this->innerHTML($cell));
            $project->addComment("Institution address:\n" . $address);
          }
        }
        // Investigators are only matched if an Institution has also been
        // matched, but it's possible that the Investigators field will be found
        // before the Institution, depending on how the search display was set
        // up.  Save the Investigators for later parsing.
        elseif ($this->dataColumns[$key] == 'investigators') {
          $investigators = $this->innerHTML($cell);
        }
        else {
          $project->__set($this->dataColumns[$key], $this->innerHTML($cell));
        }
      }
    }
    // Parse the Investigators if an Institution was matched.
    if ($institution && $investigators) {
      $parsed_investigators = $this->parseInvestigators($investigators, $institution);
      foreach ($parsed_investigators as $investigator) {
        $project->addInvestigator($investigator);
      }
    }
    // Add Investigators to the project comments if no Institution was found.
    elseif ($investigators) {
      $project->addComment("\nInvestigators:\n" . $investigators);
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
   * @return \FsrioCrawler\Institution|NULL
   *   The Institution or NULL if no match was found.
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

      $id = $this->institution_matcher->match($part, $city);
      if ($id) {
        return new Institution($part, $id);
      }
    }
    return NULL;
  }

  /**
   * Parses Investigator names, matching them to existing ones if found.
   *
   * @param string $names
   *   A string containing a semicolon-separated list of names.
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The Institution at which this Investigator worked.
   *
   * @return Investigator[]
   *   An array of parsed Investigators.
   */
  protected function parseInvestigators($names, InstitutionInterface $institution) {
    $investigators = [];
    foreach (explode(';', $names) as $name) {
      // Trim ", ." off the end of any names, which seems to indicate the
      // absence of a middle initial.
      if (substr($name, -3) == ', .') {
        $name = substr($name, 0, -3);
      }
      $id = $this->investigator_matcher->match($name, $institution->getId());
      $investigators[] = new Investigator($name, $institution, $id);
    }
    return $investigators;
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
