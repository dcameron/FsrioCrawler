<?php

namespace FsrioCrawler;

/**
 * Defines an Investigator that is performing a food safety research project.
 */
class Investigator implements InvestigatorInterface {

  /**
   * The Investigator ID.
   *
   * @var int
   */
  protected $id = 0;

  /**
   * The Investigator's Instutition.
   *
   * @var \FsrioCrawler\Institution
   */
  protected $institution;

  /**
   * The Investigator name.
   *
   * @var string
   */
  protected $name;

  /**
   * Builds an Investigator object.
   *
   * @param string $name
   *   The name of the Investigator.
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The Institution with which this Investigator is associated.
   * @param type $id
   *   The ID number of the Investigator in the database.
   *
   * @todo This should throw an exception if the $institution is NULL.
   */
  public function __construct($name, InstitutionInterface $institution, $id = 0) {
    $this->name = $name;
    $this->institution = $institution;
    if ($id) {
      $this->id = $id;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstitution() {
    return $this->institution;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setId($id) {
    $this->id = $id;
  }

}
