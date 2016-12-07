<?php

namespace FsrioCrawler;

/**
 * Defines a Funding Source Matcher.
 *
 * Matches Funding Source names to those that already exist in the Research
 * Projects Database.
 */
class FundingSourceMatcher extends MatcherBase {

  /**
   * An array of Funding Source names keyed by ID.
   *
   * @var string[]
   */
  protected $fundingSources;

  /**
   * A hash of Funding Source names for fast lookup of exact matches.
   *
   * @var int[]
   */
  protected $fundingSourcesHash;

  public function __construct(\PDO $database) {
    parent::__construct($database);

    $this->queryFundingSources();
    $this->createFundingSourcesHash();
  }

  /**
   * Populates the fundingSources array from the database.
   */
  protected function queryFundingSources() {
    $sql = 'SELECT ID, AGENCY_FULL_NAME FROM agency_data';
    try {
      foreach ($this->database->query($sql) as $source) {
        // Normalize all names to lowercase.
        $this->fundingSources[$source['ID']] = $this->normalizeString($source['AGENCY_FULL_NAME']);
      }
    }
    catch (\Exception $e) {
      echo 'Error: ' . $e->getMessage();
      exit;
    }
  }

  /**
   * Creates the fundingSourcesHash from the institutions array.
   */
  protected function createFundingSourcesHash() {
    $this->fundingSourcesHash = array_flip($this->fundingSources);
  }

  /**
   * {@inheritdoc}
   */
  public function match($name, $city = '') {
    // Attempt to find an exact match for the name in the hash.
    $name = $this->normalizeString($name);
    if (isset($this->fundingSourcesHash[$name])) {
      return $this->fundingSourcesHash[$name];
    }

    return 0;
  }

}
