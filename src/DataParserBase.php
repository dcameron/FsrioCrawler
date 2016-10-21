<?php

namespace FsrioCrawler;

use FsrioCrawler\DataParserInterface;

/**
 * Provides a base class for parsing data files.
 */
abstract class DataParserBase implements DataParserInterface {

  /**
   * The URL of the data file.
   *
   * @var string
   */
  protected $url;

  /**
   * The value of the ID of the current item when iterating.
   *
   * @var mixed
   */
  protected $currentID = NULL;

  /**
   * The current item when iterating.
   *
   * @var mixed
   */
  protected $currentItem = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($url) {
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function current() {
    return $this->currentItem;
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->currentID;
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    $this->currentID = $this->currentItem = NULL;
    if (!$this->isSourceOpen() && !$this->openSourceURL()) {
      // There is no data to parse.
      return;
    }
    $this->nextRow();
  }

  /**
   * Populates currentItem the next row in the data file.
   */
  abstract protected function nextRow();

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->openSourceURL();
    $this->next();
  }

  /**
   * Checks to see if the source URL has been opened.
   *
   * @return bool
   *   TRUE of the source URL has been opened.
   */
  abstract protected function isSourceOpen();

  /**
   * Opens a data file for parsing.
   *
   * @return bool
   *   TRUE if the source URL was successfully opened.
   */
  abstract protected function openSourceURL();

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return !empty($this->currentItem);
  }

}
