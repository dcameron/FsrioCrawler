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
    // Remove commas and hyphens.
    $string = str_replace([',', '-'], '', $string);
    // Remove extra whitespace.
    $string = preg_replace('/\s+/', ' ', $string);
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
