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
   * Returns the Institution at which the Investigator worked.
   *
   * @return \FsrioCrawler\InstitutionInterface
   *   The Institution.
   */
  public function getInstitution();

  /**
   * Returns the name of the Investigator.
   *
   * @return string
   *   The Investigator name.
   */
  public function getName();

  /**
   * Sets the ID number of the Investigator.
   *
   * @param int $id
   *   The new ID number.
   */
  public function setId($id);

}
