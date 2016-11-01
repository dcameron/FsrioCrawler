<?php

namespace FsrioCrawler;

/**
 * Defines an Institution that is performing a food safety research project.
 */
class Institution implements InstitutionInterface {

  /**
   * The institution ID.
   *
   * @var int
   */
  protected $id = 0;

  /**
   * The institution name.
   *
   * @var string
   */
  protected $name;

  public function __construct($name, $id = 0) {
    $this->name = $name;
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
  public function getName() {
    return $this->name;
  }

}
