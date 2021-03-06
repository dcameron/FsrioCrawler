<?php

namespace FsrioCrawler;

/**
 * Defines an Institution Matcher.
 *
 * Matches Institution names to those that already exist in the Research
 * Projects Database.
 */
class InstitutionMatcher implements InstitutionMatcherInterface {

  use MatcherTrait;

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
        // Normalize all names to lowercase.
        $this->institutions[$institution['ID']] = $this->normalizeString($institution['INSTITUTION_NAME']);
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
  public function match($name, $city = '') {
    // Attempt to find an exact match for the name in the hash.
    $name = $this->normalizeString($name);
    if (isset($this->instititionsHash[$name])) {
      return $this->instititionsHash[$name];
    }

    // If a city name is given, attempt to find a match based on it.
    if ($city) {
      // Many university Institution names will have the city appended to the
      // school's name.  Attempt a hash lookup based on that.
      $city = $this->normalizeString($city);
      if (isset($this->instititionsHash[$name . ' ' . $city])) {
        return $this->instititionsHash[$name . ' ' . $city];
      }

      // Attempt to find a close match among institutions located in the given
      // city.
      if ($id = $this->matchInList($name, $this->queryCityInstitutions($city))) {
        return $id;
      }
    }

    // Atempt to find a close match within the array of all Institutions.
    $id = $this->matchInList($name, $this->institutions, 0.1);

    return 0;
  }

  /**
   * Queries the database for existing Institutions within a given city.
   *
   * @param string $city
   *   The city name.
   *
   * @return string[]
   *   An array of Institution names from the database keyed by ID.
   */
  protected function queryCityInstitutions($city) {
    static $city_institutions = [];

    if (!isset($city_institutions[$city])) {
      $city_institutions[$city] = [];

      $sql = 'SELECT ID, INSTITUTION_NAME FROM institution_data WHERE INSTITUTION_CITY = ?';
      try {
        $query = $this->database->prepare($sql);
        $query->execute([$city]);
        while ($institution = $query->fetch()) {
          // Normalize all names to lowercase.
          $city_institutions[$city][$institution['ID']] = $this->normalizeString($institution['INSTITUTION_NAME']);
        }
      }
      catch (\Exception $e) {
        echo 'Error: ' . $e->getMessage();
        exit;
      }
    }
    return $city_institutions[$city];
  }

}
