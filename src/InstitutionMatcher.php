<?php

namespace FsrioCrawler;

/**
 * Defines an Institution Matcher.
 *
 * Matches Institution names to those that already exist in the Research
 * Projects Database.
 */
class InstitutionMatcher implements InstitutionMatcherInterface {

  /**
   * A connection to the Research Projects Database.
   *
   * @var \PDO
   */
  protected $database;

  /**
   * An array of institution names keyed by ID.
   *
   * @var string[]
   */
  protected $institutions;

  /**
   * A hash of Institution names for fast lookup of exact matches.
   *
   * @var int[]
   */
  protected $instititionsHash;

  public function __construct(\PDO $database) {
    $this->database = $database;

    $this->queryInstitutions();
    $this->createInstitutionsHash();
  }

  /**
   * Populates the institutions array from the database.
   */
  protected function queryInstitutions() {
    $sql = 'SELECT ID, INSTITUTION_NAME FROM institution_data';
    try {
      foreach ($this->database->query($sql) as $institution) {
        $this->institutions[$institution['ID']] = $institution['INSTITUTION_NAME'];
      }
    }
    catch (\Exception $e) {
      echo 'Error: ' . $e->getMessage();
      exit;
    }
  }

  /**
   * Creates the institutionsHash from the institutions array.
   */
  protected function createInstitutionsHash() {
    $this->instititionsHash = array_flip($this->institutions);
  }

  /**
   * {@inheritdoc}
   */
  public function match($name) {
    // Attempt to find an exact match for the name in the hash.
    if (isset($this->instititionsHash[$name])) {
      return $this->instititionsHash[$name];
    }
    return 0;
  }

}
