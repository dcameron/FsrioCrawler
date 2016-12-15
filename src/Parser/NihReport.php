<?php

namespace FsrioCrawler\Parser;

use FsrioCrawler\DataParserBase;
use FsrioCrawler\Institution;
use FsrioCrawler\InstitutionInterface;
use FsrioCrawler\Investigator;
use FsrioCrawler\MatcherInterface;
use FsrioCrawler\Project;

/**
 * Provides a parser for NIH RePORT search results.
 */
class NihReport extends DataParserBase {

  /**
   * An Institution Matcher.
   *
   * @var \FsrioCrawler\MatcherInterface
   */
  protected $institution_matcher;

  /**
   * An Investigator Matcher.
   *
   * @var \FsrioCrawler\MatcherInterface
   */
  protected $investigator_matcher;

  /**
   * A Funding Source Matcher.
   *
   * @var \FsrioCrawler\MatcherInterface
   */
  protected $funding_source_matcher;

  /**
   * The DOMDocument we are encapsulating.
   *
   * @var \DOMDocument
   */
  protected $document;

  /**
   * An array of URLs to project pages.
   *
   * @var string[]
   */
  protected $projectUrls = NULL;

  public function __construct($url, MatcherInterface $institution_matcher, MatcherInterface $investigator_matcher, MatcherInterface $funding_source_matcher) {
    parent::__construct($url);

    $this->document = new \DOMDocument();

    // Suppress errors during parsing, so we can pick them up after.
    libxml_use_internal_errors(TRUE);

    $this->institution_matcher = $institution_matcher;
    $this->investigator_matcher = $investigator_matcher;
    $this->funding_source_matcher = $funding_source_matcher;
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
    if (!isset($this->projectUrls) && !$this->parseSearchResults()) {
      // There is no data to parse.
      return;
    }
    $url = array_shift($this->projectUrls);
    if (empty($url)) {
      return;
    }

    $project = new Project();
    $project->__set('source_url', $url);
    $this->parseProjectDescriptionPage($url, $project);

    $this->currentItem = $project;
  }

  /**
   * Parses the search result rows in the document.
   *
   * @return bool
   *   TRUE if the data table was found.
   */
  protected function parseSearchResults() {
    $xpath = new \DOMXPath($this->document);
    $urls = $xpath->query("//table[@class='search_research']//a[@class='hyperlink']");
    if (!$urls->length) {
      return FALSE;
    }
    foreach ($urls as $url) {
      $this->projectUrls[] = $url->getAttribute('href');
    }
    return TRUE;
  }

  /**
   * Parses a project description page.
   *
   * @param string $url
   *   The URL of the project description page.
   * @param \FsrioCrawler\Project
   *   The project into which the data will be parsed.
   */
  protected function parseProjectDescriptionPage($url, $project) {
    // Open the project URL.
    $document = new \DOMDocument();
    $document->loadHTMLFile($url);
    $xpath = new \DOMXPath($document);

    // Parse the page by searching for the project information.
    if ($project_number = $this->findSearchCriteriaValue($xpath, 'Project Number:')) {
      // Remove any former project numbers.  Don't worry about strpos()
      // returning 0.  If for some reason "Former Number" is at the start of the
      // string, then we wouldn't want it anyway.
      if ($position = strpos($project_number, 'Former Number:')) {
        $project_number = substr($project_number, 0, $position);
        // Trim excess whitespace that may include &nbsp; characters.
        $project_number = trim($project_number, " \t\n\r\0\x0B\xC2\xA0");
      }
      $project->__set('project_number', $project_number);
    }
    if ($title = $this->findSearchCriteriaValue($xpath, 'Title:')) {
      $project->__set('title', $this->fixCapitalization($title));
    }
    if ($objective = $this->findObjective($xpath)) {
      $project->__set('objective', $objective);
    }
    // Parse the project details page.
    $details_url = $this->findProjectDetailsUrl($xpath);
    $this->parseProjectDetailsPage($details_url, $project);
  }

  /**
   * Parses table cells in the project description search_criteria div.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project description document.
   * @param type $label
   *   The text inside the cell immediately preceding the cell that contains
   *   the data we're looking for.
   *
   * @return string
   *   The parsed data value.
   */
  protected function findSearchCriteriaValue(\DOMXPath $xpath, $label) {
    $cells = $xpath->query("//div[@class='search_criteria']//td");
    if (!$cells->length) {
      return NULL;
    }
    $isValue = FALSE;
    foreach ($cells as $cell) {
      if ($isValue) {
        return trim($cell->nodeValue);
      }
      if (trim($cell->nodeValue) == $label) {
        // Set $isValue to indicate that the next cell is the value.
        $isValue = TRUE;
      }
    }
  }

  /**
   * Parses table rows in the project description to find the objective.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project description document.
   *
   * @return string
   *   The parsed objective value.
   */
  protected function findObjective(\DOMXPath $xpath) {
    $rows = $xpath->query("//table[@class='proj_info_cont']//tr");
    if (!$rows->length) {
      return NULL;
    }
    $isValue = FALSE;
    foreach ($rows as $row) {
      if ($isValue) {
        // Removed unwanted text from the start of the objective, including
        // encoded whitespace characters.
        if (strpos($row->nodeValue, 'DESCRIPTION (provided by applicant): ')) {
          return substr(trim($row->nodeValue, " \t\n\r\0\x0B\xC2\xA0\xEF\xBB\xBF"), 37);
        }
        return trim($row->nodeValue);
      }
      if (trim($row->nodeValue) == 'Abstract Text:') {
        // Set $isValue to indicate that the next row is the value.
        $isValue = TRUE;
      }
    }
  }

  /**
   * Parses anchors in the project description to build the details page URL.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project description document.
   *
   * @return string
   *   The URL of the project details page.
   */
  protected function findProjectDetailsUrl(\DOMXPath $xpath) {
    $anchors = $xpath->query("//a[@title='Details']");
    if (!$anchors->length) {
      return NULL;
    }
    foreach ($anchors as $anchor) {
      $javascript = $anchor->getAttribute('href');
      preg_match('/\((?<aid>\d+),(?<icde>\d+)\)/', $javascript, $matches);
      if ($matches) {
        return 'https://projectreporter.nih.gov/project_info_details.cfm?aid=' . $matches['aid'] . '&icde=' . $matches['icde'];
      }
    }
  }

  /**
   * Parses a project details page.
   *
   * @param string $url
   *   The URL of the project details page.
   * @param \FsrioCrawler\Project
   *   The project into which the data will be parsed.
   */
  protected function parseProjectDetailsPage($url, $project) {
    // Open the project details URL.
    $document = new \DOMDocument();
    $document->loadHTMLFile($url);
    $xpath = new \DOMXPath($document);

    if ($institution_data = $this->findProjectInstitution($xpath)) {
      if ($institution = $this->parseInstitution($institution_data['name'], $institution_data['city'])) {
        $project->addInstitution($institution);
      }
      else {
        $project->addComment("Institution:\n" . $this->fixCapitalization($institution_data['name']));
      }
    }
    if ($investigator_name = $this->findProjectInvestigator($xpath)) {
      // Fix the capitalization.
      $investigator_name = $this->fixCapitalization($investigator_name);
      if (isset($institution) && $investigator = $this->parseInvestigator($investigator_name, $institution)) {
        $project->addInvestigator($investigator);
      }
      else {
        $project->addComment("Investigator:\n" . $investigator_name);
      }
    }
    if ($start_date = $this->findProjectStartDate($xpath)) {
      $project->__set('start_date', $start_date);
    }
    if ($end_date = $this->findProjectEndDate($xpath)) {
      $project->__set('end_date', $end_date);
    }
    if ($funding_source_name = $this->findProjectFundingSource($xpath)) {
      $funding_source_name = $this->fixCapitalization($funding_source_name);
      if ($funding_source = $this->parseFundingSource($funding_source_name)) {
        $project->__set('funding_source', $funding_source);
      }
      else {
        $project->addComment("Funding Source:\n" . $funding_source_name);
      }
    }
  }

  /**
   * Parses a table cell in the project details to find the institution data.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   *
   * @return array
   *   A string array containing the keys 'name' and 'city'.
   */
  protected function findProjectInstitution(\DOMXPath $xpath) {
    // Find the Other Information row.
    $infoRow = $this->findProjectInfoRow($xpath, 'Organization:');
    if (!$infoRow) {
      return NULL;
    }
    $institution = [];
    // The project start date is in the second table cell of the Other
    // Information row.
    foreach ($infoRow->childNodes->item(0)->childNodes as $node) {
      if ($node->nodeValue == 'Name: ') {
        $institution['name'] = trim($node->nextSibling->nodeValue);
      }
      if ($node->nodeValue == 'City: ') {
        // Trim excess whitespace that may include &nbsp; characters.
        $institution['city'] = trim($node->nextSibling->nodeValue, " \t\n\r\0\x0B\xC2\xA0");
      }
    }
    return $institution;
  }

  /**
   * Parses anchors in the project details to find the investigator name.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   *
   * @return string
   *   The parsed investigator name.
   */
  protected function findProjectInvestigator(\DOMXPath $xpath) {
    // Find PI info anchors.
    $anchors = $xpath->query("//a[@title='Click to view Contact PI/Project Leader Profile']");
    foreach ($anchors as $anchor) {
      if (!empty($anchor->nodeValue)) {
        return $anchor->nodeValue;
      }
    }
  }

  /**
   * Parses a table cell in the project details to find the start date.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   *
   * @return string
   *   The parsed start date value.
   */
  protected function findProjectStartDate(\DOMXPath $xpath) {
    // Find the Other Information row.
    $infoRow = $this->findProjectInfoRow($xpath, 'Other Information:');
    if (!$infoRow) {
      return NULL;
    }
    // The project start date is in the second table cell of the Other
    // Information row.
    foreach ($infoRow->childNodes->item(2)->childNodes as $node) {
      if ($node->nodeValue == 'Project Start Date') {
        return substr(trim($node->nextSibling->nodeValue), -4);
      }
    }
  }

  /**
   * Parses a table cell in the project details to find the end date.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   *
   * @return string
   *   The parsed end date value.
   */
  protected function findProjectEndDate(\DOMXPath $xpath) {
    // Find the Other Information row.
    $infoRow = $this->findProjectInfoRow($xpath, 'Other Information:');
    if (!$infoRow) {
      return NULL;
    }
    // The project end date is in the third table cell of the Other
    // Information row.
    foreach ($infoRow->childNodes->item(4)->childNodes as $node) {
      if ($node->nodeValue == 'Project End Date') {
        return substr(trim($node->nextSibling->nodeValue), -4);
      }
    }
  }

  /**
   * Locates a table row in the project details containing start and end dates.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   * @param string $header_string
   *   A unique string from the beginning of the header row that precedes the
   *   data row.
   *
   * @return \DOMElement|NULL
   *   The table row that contains the data or NULL if the row wasn't found.
   */
  protected function findProjectInfoRow(\DOMXPath $xpath, $header_string) {
    $rows = $xpath->query("//table[@class='proj_info_cont']/tr");
    $isInfoRow = FALSE;
    foreach ($rows as $row) {
      if ($isInfoRow && !empty($row)) {
        return $row;
      }
      if (trim(substr($row->nodeValue, 0, strlen($header_string))) == $header_string) {
        $isInfoRow = TRUE;
      }
    }
    return NULL;
  }

  /**
   * Parses a table row in the project details to find the Funding Source name.
   *
   * @param \DOMXPath $xpath
   *   The XPath of the project details document.
   *
   * @return int
   *   The ID number of the matching Funding Source or 0 if no match was found.
   */
  protected function findProjectFundingSource(\DOMXPath $xpath) {
    $rows = $xpath->query("//table[@class='proj_info_cont']/tr");
    $isInfoRow = FALSE;
    foreach ($rows as $row) {
      if ($isInfoRow && !empty($row)) {
        return trim($row->nodeValue);
      }
      if (trim($row->nodeValue) == 'Administering Institutes or Centers:') {
        $isInfoRow = TRUE;
      }
    }
    return NULL;
  }

  /**
   * Parses an Institution name, matching it to an existing one if found.
   *
   * @param string $name
   *   The name of the institution.
   * @param string $city
   *   The name of the institution's city.
   *
   * @return \FsrioCrawler\Institution|NULL
   *   The Institution or NULL if no match was found.
   */
  protected function parseInstitution($name, $city) {
    // Make sure "University" is spelled-out.
    if ((strpos($name, 'UNIV') !== FALSE) && (strpos($name, 'UNIVERSITY') === FALSE)) {
      $name = str_replace('UNIV', 'UNIVERSITY', $name);
    }
    $id = $this->institution_matcher->match($name, $city);
    if ($id) {
      return new Institution($name, $id);
    }
    return NULL;
  }

  /**
   * Parses an Investigator name, matching it to existing one if found.
   *
   * @param string $name
   *   The name of the Investigator.
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The Institution at which this Investigator worked.
   *
   * @return \FsrioCrawler\Investigator
   *   The parsed Investigator.
   */
  protected function parseInvestigator($name, InstitutionInterface $institution) {
    $id = $this->investigator_matcher->match($name, $institution->getId());
    return new Investigator($name, $institution, $id);
  }

  /**
   * Parses a Funding Source name, matching it to existing one if found.
   *
   * @param string $name
   *   The name of the Funding Source.
   *
   * @return int
   *   The ID number of the parsed Funding Source.
   */
  protected function parseFundingSource($name) {
    return $this->funding_source_matcher->match($name);
  }

  /**
   * Fixes the capitalization of all-uppercase strings.
   *
   * Many of the strings in the NIH RePORT are completely uppercase.  This
   * changes the words to lowercase and capitalizes only the first letter of
   * each word, with a few exceptions.
   *
   * @param type $string
   * @return type
   */
  protected function fixCapitalization($string) {
    $string = ucwords(strtolower($string));
    return str_replace([' Of ', ' And ', ' In ', ' To '], [' of ', ' and ', ' in ', ' to '], $string);
  }

}
