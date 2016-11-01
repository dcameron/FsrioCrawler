<?php

namespace FsrioCrawler;

/**
 * Defines an interface for Institution Matchers.
 */
interface InstitutionMatcherInterface {

  /**
   * Matches an Institution name to an existing institution.
   *
   * @param string $name
   *   The institution name to find.
   *
   * @return int
   *   The matching Institution ID number or 0 if there was no match.
   */
  public function match($name);

}
