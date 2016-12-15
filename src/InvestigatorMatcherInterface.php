<?php

namespace FsrioCrawler;

/**
 * Defines an interface for matching Investigator records in the database.
 */
interface InvestigatorMatcherInterface extends MatcherInterface {

  /**
   * Updates the list of Investigators for a specific Institution.
   *
   * This is intended to be used by crawlers to inform the Matcher that an
   * Institution has had new Investigators added during runtime.  That way the
   * Matcher can update its lists and avoid having a situation where a new
   * Investigator that has multiple new projects has a new Investigator record
   * inserted over and over again.
   *
   * @param \FsrioCrawler\InstitutionInterface $institution
   *   The Institution whose lists need to be refreshed.
   */
  public function updateInvestigators(InstitutionInterface $institution);

}
