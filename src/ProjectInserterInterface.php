<?php

namespace FsrioCrawler;

/**
 * Defines an interface for inserting projects into the database.
 */
interface ProjectInserterInterface {

  /**
   * Inserts a Project into the database.
   *
   * @param \FsrioCrawler\ProjectInterface $project
   *   The project to be inserted.
   *
   * @return array
   *   An array of ID numbers for new Project and Investigator records that were
   *   inserted into the database.
   */
  public function insertProject(ProjectInterface $project);

}
