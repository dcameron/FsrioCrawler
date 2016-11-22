<?php

namespace FsrioCrawler;

/**
 * Defines an interface for matching data to existing database records.
 */
interface MatcherInterface {

  /**
   * Matches a name to an existing database record.
   *
   * @param string $name
   *   The name to find.
   *
   * @return int
   *   The matching record ID number or 0 if there was no match.
   */
  public function match($name);

}
