<?php

namespace FsrioCrawler;

/**
 * Defines a food safety project.
 */
class Project implements ProjectInterface {

  /**
   * The project's accession number, valid only for USDA projects.
   *
   * @var string 
   */
  protected $accession_number;

  /**
   * Administrative information about the project.
   *
   * @var string
   */
  protected $comments;

  /**
   * The project's end date.
   *
   * @var string 
   */
  protected $end_date;

  /**
   * The project's institutions.
   *
   * @var \FsrioCrawler\InstitutionInterface[]
   */
  protected $institutions;

  /**
   * The project's investigators.
   *
   * @var string 
   */
  protected $investigators;

  /**
   * The project's additional information.
   *
   * @var string 
   */
  protected $more_info;

  /**
   * The project's objective.
   *
   * @var string 
   */
  protected $objective;

  /**
   * The project number.
   *
   * @var string 
   */
  protected $project_number;

  /**
   * The project's type.
   *
   * @var string 
   */
  protected $project_type;

  /**
   * The project's start date.
   *
   * @var string 
   */
  protected $start_date;

  /**
   * The project's title.
   *
   * @var string 
   */
  protected $title;

  public function __set($name, $value) {
    if ($name == 'institutions') {
      return;
    }
    $this->$name = $value;
  }

  public function __toString() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function addComment($comment) {
    if (empty($this->comments)) {
      $this->comments = $comment;
    }
    else {
      $this->comments .= "\n\n" . $comment;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addInstitution(InstitutionInterface $institution) {
    $this->institutions[] = $institution;
  }

}
