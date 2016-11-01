<?php

namespace FsrioCrawler;

/**
 * Defines an interface for research Institutions.
 */
interface InstitutionInterface {

  /**
   * Returns the ID number of the institution.
   *
   * @return int
   *   The ID number.
   */
  public function getId();

  /**
   * Returns the name of the institution.
   *
   * @return string
   *   The institution name.
   */
  public function getName();

}
