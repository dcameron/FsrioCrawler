<?php

namespace FsrioCrawler;

/**
 * Defines an Investigator Matcher.
 *
 * Matches Investigator names to those that already exist in the Research
 * Projects Database.
 */
class InvestigatorMatcher extends MatcherBase {

  /**
   * A nested array of investigator names.
   *
   * The primary array is keyed by Institution ID number.  Each value is an
   * array of Investigator names, keyed by their Investigator ID number.
   *
   * @var array
   */
  protected $investigators;

  /**
   * A hash of Investigator names for fast lookup of exact matches.
   *
   * The primary array is keyed by Institution ID number.  Each value is the
   * flipped array from the $investigators.
   *
   * @var array
   */
  protected $investigatorsHash;

  public function __construct(\PDO $database) {
    parent::__construct($database);

    $this->queryInvestigators();
    $this->createInvestigatorsHash();
  }

  /**
   * Populates the investigators array from the database.
   */
  protected function queryInvestigators() {
    $sql = 'SELECT ID, name, INSTITUTION FROM investigator_data ORDER BY name';
    try {
      foreach ($this->database->query($sql) as $investigator) {
        // Normalize all names to lowercase.
        $this->investigators[$investigator['INSTITUTION']][$investigator['ID']] = $this->normalizeString($investigator['name']);
      }
    }
    catch (\Exception $e) {
      echo 'Error: ' . $e->getMessage();
      exit;
    }
  }

  /**
   * Creates the investigatorsHash from the investigators array.
   */
  protected function createInvestigatorsHash() {
    foreach ($this->investigators as $institutionID => $investigators) {
      $this->investigatorsHash[$institutionID] = array_flip($investigators);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param int $institution_id
   *   The ID number of the Institution at which this Investigator worked.
   */
  public function match($name, $institution_id = 0) {
    $name = $this->normalizeString($name);
    // Quit early if there are no investigators listed for this Institution.
    if (!isset($this->investigators[$institution_id])) {
      return 0;
    }
    // Attempt to find an exact match for the name in the hash.
    if (isset($this->investigatorsHash[$institution_id][$name])) {
      return $this->investigatorsHash[$institution_id][$name];
    }
    // Attempt to find an exact match between the name and the beginning of
    // name strings in the database.
    if ($id = $this->findAbbreviatedName($name, $institution_id)) {
      return $id;
    }
    // Finally, attempt to find a closely matching name in the list of
    // investigators.
    if ($id = $this->matchInList($name, $this->investigators[$institution_id], 0.4)) {
      return $id;
    }
    return 0;
  }

  /**
   * Matches an Investigator name to the beginning of names in the database.
   *
   * This function basically attempts to find matches for names that are
   * abbreviated.  Specifically, it should help with names that might be
   * otherwise ignored by the Levenschtein function in matchInList().  For
   * example, a name in the database might contain a short last name followed by
   * a long first name.  If the name to be matched is the last name and first
   * initial, that name would have a high Levenschtein ratio and not be matched.
   *
   * @param string $name
   *   The name to be matched.
   * @param type $institution_id
   *   The ID number of the Institution at which this Investigator worked.
   *
   * @return int
   *   The matching ID number or 0 if there was no match.
   */
  protected function findAbbreviatedName($name, $institution_id) {
    foreach ($this->investigators[$institution_id] as $record_id => $record_name) {
      if (substr($record_name, strlen($name)) == $name) {
        return $record_id;
      }
    }
    return 0;
  }

  /**
   * Updates the list of Investigators for a specific Institution.
   *
   * This is intended to be used by crawlers to inform the Matcher that an
   * Institution has had new Investigators added during runtime.  That way the
   * Matcher can update its lists and avoid having a situation where a new
   * Investigator that has multiple new projects has a new Investigator record
   * inserted over and over again.
   *
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The Institution whose lists need to be refreshed.
   */
  public function updateInvestigators(InstitutionInterface $institution) {
    // Get a new list of Investigators from the Institution.
    $query = $this->database->prepare('SELECT ID, name FROM investigator_data WHERE INSTITUTION = ? ORDER BY name');
    $query->execute([$institution->getId()]);
    $investigators = [];
    while ($investigator = $query->fetch(\PDO::FETCH_ASSOC)) {
      $investigators[$investigator['ID']] = $investigator['name'];
    }
    // Replace the existing list arrays for the Instutution.
    $this->investigators[$institution->getId()] = $investigators;
    $this->investigatorsHash[$institution->getId()] = array_flip($investigators);
  }

}
