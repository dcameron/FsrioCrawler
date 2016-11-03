<?php

namespace FsrioCrawler;

/**
 * Provides an interface for defining a project.
 */
interface ProjectInterface {

  /**
   * Adds text to the administrative comments.
   *
   * @param string $comment
   *   The new comment text.
   */
  public function addComment($comment);

  /**
   * Adds a new Institution to the project's institutions array.
   *
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The new Institution.
   */
  public function addInstitution(InstitutionInterface $institution);

}
