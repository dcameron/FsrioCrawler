<?php

namespace FsrioCrawler;
/**
 * Contains basic functions for matching data to existing database records.
 */
abstract class MatcherBase implements MatcherInterface {

  /**
   * A connection to the Research Projects Database.
   *
   * @var \PDO
   */
  protected $database;

  public function __construct(\PDO $database) {
    $this->database = $database;
  }

  /**
   * Attempts to find a closely-matching name within a list of names.
   *
   * @param string $name
   *   The name to find.
   * @param string[] $list
   *   An array of names from the database keyed by ID.
   * @param float $cutoff_ratio
   *   The maximum percentage, expressed as a float, of characters that we allow
   *   to be replaced in order to match names.
   *
   * @return int
   *   The matching ID number or 0 if there was no match.
   */
  protected function matchInList($name, $list, $cutoff_ratio = 0.25) {
    $min_ratio = 1;
    $min_ratio_id = 0;
    foreach ($list as $id => $record_name) {
      $ratio = $this->calculateLevenshteinRatio($name, $record_name);
      // Ignore any name that must be replaced more than the cutoff ratio.
      if ($ratio > $cutoff_ratio) {
        continue;
      }
      elseif ($ratio < $min_ratio) {
        $min_ratio = $ratio;
        $min_ratio_id = $id;
      }
    }
    return $min_ratio_id;
  }

  /**
   * Normalizes strings to a common format for better matching.
   *
   * All characters are made lowercase.  Punctuation and extra whitespace is
   * removed.
   *
   * @param string $string
   *   The string to be normalized.
   *
   * @return string
   *   The normalized string.
   */
  protected function normalizeString($string) {
    $string = strtolower($string);
    // Remove periods, commas, and hyphens.
    $string = str_replace(['.', ',', '-'], '', $string);
    // Remove extra whitespace.
    $string = trim(preg_replace('/\s+/', ' ', $string));
    return $string;
  }

  /**
   * Calculates the Levenshtein ratio of two strings.
   *
   * @param string $first
   *   The first string.
   * @param string $second
   *   The second string.
   *
   * @return float
   *   The ratio of the Levenshtein distance between the two strings divided by
   *   the length of the longest string.
   */
  protected function calculateLevenshteinRatio($first, $second) {
    $length = strlen($first) > strlen($second) ? strlen($first) : strlen($second);
    $distance = levenshtein($first, $second);
    return $distance / $length;
  }

}
