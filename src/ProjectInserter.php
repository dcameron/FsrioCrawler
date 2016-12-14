<?php

namespace FsrioCrawler;

/**
 * Defines a class that inserts Projects into the database.
 */
class ProjectInserter implements ProjectInserterInterface {

  /**
   * A connection to the Research Projects Database.
   *
   * @var \PDO
   */
  protected $database;

  public function __construct(\PDO $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function insertProject(ProjectInterface $project) {
    // Do not insert duplicate projects.
    if ($this->isDuplicateProject($project)) {
      return [];
    }
    $project_id = $this->__insertProject($project);
    $this->__insertInstitutionReferences($project_id, $project->__get('institutions'));
    $investigator_ids = $this->__insertInvestigatorReferences($project_id, $project->__get('investigators'));
    $this->__insertFundingSourceReference($project_id, $project->__get('funding_source'));
    return ['project' => $project_id, 'investigators' => $investigator_ids];
  }

  /**
   * Checks to see if a project already exists in the database.
   *
   * @param \FsrioCrawler\ProjectInterface $project
   *   The project it's searching for.
   *
   * @return bool
   *   TRUE if the project is already in the database, FALSE if not.
   */
  protected function isDuplicateProject(ProjectInterface $project) {
    try {
      $query = $this->database->prepare("SELECT ID FROM project WHERE PROJECT_NUMBER = ? AND PROJECT_TITLE = ?");
      $query->execute([$project->__get('project_number'), $project->__get('title')]);
      while ($project = $query->fetch()) {
        // If a project was found, return TRUE.
        return TRUE;
      }
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
    }
    return FALSE;
  }

  protected function __insertProject(ProjectInterface $project) {
    try {
      $query = $this->database->prepare("INSERT INTO project (PROJECT_NUMBER, PROJECT_TITLE, source_url, PROJECT_START_DATE, PROJECT_END_DATE, PROJECT_MORE_INFO, PROJECT_OBJECTIVE, PROJECT_ACCESSION_NUMBER, ACTIVITY_STATUS, DATE_ENTERED, COMMENTS, archive, LAST_UPDATE, LAST_UPDATE_BY) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, now(), ?, 1, now(), 'dcameron')");
      $params = [
        $project->__get('project_number'),
        $project->__get('title'),
        $project->__get('source_url'),
        $project->__get('start_date'),
        $project->__get('end_date'),
        $project->__get('more_info'),
        $project->__get('objective'),
        $project->__get('accession_number'),
        $project->__get('comments'),
      ];
      $query->execute($params);
      return $this->database->lastInsertId();
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
      return 0;
    }
  }

  protected function __insertInstitutionReferences($project_id, $institutions) {
    try {
      $query = $this->database->prepare("INSERT INTO institution_index (pid, inst_id) VALUES (?, ?)");
      foreach ($institutions as $institution) {
        // We don't reference Institutions that are not already in the database,
        // so skip any that have an ID of 0.
        if ($institution->getId() == 0) {
          continue;
        }
        $query->execute([$project_id, $institution->getId()]);
      }
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
    }
  }

  protected function __insertInvestigatorReferences($project_id, $investigators) {
    $new_investigators = [];
    try {
      $query = $this->database->prepare("INSERT INTO investigator_index (pid, inv_id) VALUES (?, ?)");
      foreach ($investigators as $investigator) {
        // Insert any new Investigators into the database.
        if ($investigator->getId() == 0) {
          $this->__insertInvestigator($investigator);
          $new_investigators[] = $investigator->getId();
        }
        $query->execute([$project_id, $investigator->getId()]);
      }
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
    }
    return $new_investigators;
  }

  protected function __insertInvestigator(InvestigatorInterface $investigator) {
    try {
      $query = $this->database->prepare("INSERT INTO investigator_data (name, INSTITUTION, DATE_ENTERED) VALUES (?, ?, now())");
      $query->execute([$investigator->getName(), $investigator->getInstitution()->getId()]);
      // Set the new ID of the Investigator.
      $investigator->setId($this->database->lastInsertId());
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
    }
  }

  protected function __insertFundingSourceReference($project_id, $funding_source) {
    // Skip if the $funding_source is empty.
    if (empty($funding_source)) {
      return;
    }
    try {
      $query = $this->database->prepare("INSERT INTO agency_index (pid, aid) VALUES (?, ?)");
      $query->execute([$project_id, $funding_source]);
    }
    catch (Exception $e) {
      echo 'Error: ' . $e->getMessage();
    }
  }

}
