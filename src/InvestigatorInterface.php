<?php

namespace FsrioCrawler;

/**
 * Defines an interface for research Investigators.
 */
interface InvestigatorInterface {

  /**
   * Returns the ID number of the Investigator.
   *
   * @return int
   *   The ID number.
   */
  public function getId();

  /**
   * Returns the name of the Investigator.
   *
   * @return string
   *   The Investigator name.
   */
  public function getName();

}
