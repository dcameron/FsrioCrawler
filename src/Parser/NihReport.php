<?php

namespace FsrioCrawler\Parser;

use FsrioCrawler\DataParserBase;
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
  protected $projectUrls = [];

  public function __construct($url, MatcherInterface $institution_matcher, MatcherInterface $investigator_matcher) {
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
    if (empty($this->proejctUrls) && !$this->parseSearchResults()) {
      // There is no data to parse.
      return;
    }
    $url = array_shift($this->projectUrls);
    if (empty($url)) {
      return;
    }
    $this->currentItem = $this->parseProjectPage($url);
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
   * Parses a project page.
   *
   * @param string $url
   *   The URL of the project page.
   *
   * @return \FsrioCrawler\Project
   *   The parsed project.
   */
  protected function parseProjectPage($url) {
    $project = new Project();

    // Open the project URL.
    $document = new \DOMDocument();
    $document->loadHTMLFile($url);
    $xpath = new \DOMXPath($document);

    if ($project_number = $this->findProjectNumber($xpath)) {
      $project->__set('project_number', $project_number);
    }

    return $project;
  }

  protected function findProjectNumber(\DOMXPath $xpath) {
    $cells = $xpath->query("//div[@class='search_criteria']//td");
    if (!$cells->length) {
      return NULL;
    }
    $isNumber = FALSE;
    foreach ($cells as $cell) {
      if ($isNumber) {
        return trim($cell->nodeValue);
      }
      if (trim($cell->nodeValue) == 'Project Number:') {
        // Set $isNumber to indicate that the next cell is the project number.
        $isNumber = TRUE;
      }
    }
  }

}
