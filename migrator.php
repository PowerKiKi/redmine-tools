<?php

/**
  * This script migrate redmine project from one instance of redmine to another one. It will
  * import the following objects:
  *   - project
  *   - categories
  *   - versions
  *   - issues
  *   - journals
  *   - logged time
  *   - modules status (wether it is active for the project)
  *
  * It relies on manual mapping for users and priorities as they should already exist in target database.
  * All modifications of the target database are live. So it is strongly advised to test against a dummy database first.
  *
  * USAGE:
  *   1. update manual mapping for users, status and priorities (see $usersMapping and $prioritiesMapping)
  *   2. update database connection data and project ID to migrate
  *   3. run the script
  */


require_once(dirname(__FILE__) . '/Library/DBMysql.php');

class Migrator
{
    var $dbOld = null;
    var $dbNew = null;

    var $usersMapping = array(
            52 =>  203,
            8  =>  14,
            58 =>  207,
            71 =>  223,
            44 =>  23,
            36 =>  241,
            10 =>  132,
            53 =>  205,
            55 =>  242,
            12 =>  139,
            13 =>  243,
            14 =>  244,
            49 =>  207,
            7  =>  247
        );

    var $prioritiesMapping = array(
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
        );

    var $statusMapping = array(
            1   =>  1,
            15  =>  2,
            2   =>  10,
            12  =>  11,
            16  =>  12,
            13  =>  13,
            20  =>  14,
            14  =>  15,
            17  =>  16,
            18  =>  17,
            19  =>  18,
            4   =>  4,
            6   =>  6,
            11  =>  8,
            5   =>  5,
            10  =>  7,
            3   =>  12
    );

    var $projectsMapping = array();
    var $categoriesMapping = array();
    var $versionsMapping = array();
    var $journalsMapping = array();
    var $issuesMapping = array();
    var $timeEntriesMapping = array();
    var $modulesMapping = array();
    var $nbAt = 0;

    function __construct($host1, $db1, $user1, $pass1, $host2, $db2, $user2, $pass2)
    {
        $this->dbOld = new DBMysql($host1, $user1, $pass1);
        $this->dbOld->connect($db1);

        $this->dbNew = new DBMysql($host2, $user2, $pass2);
        $this->dbNew->connect($db2);
    }

    private function replaceUser($idUserOld)
    {
        if ($idUserOld == null)
            return null;

        if (!isset($this->usersMapping[$idUserOld]))
            throw new Exception("No mapping defined for old user id '$idUserOld'");
        else
            return $this->usersMapping[$idUserOld];
    }

    private function replacePriority($idPriorityOld)
    {
        if ($idPriorityOld == null)
            return null;

        if (!isset($this->prioritiesMapping[$idPriorityOld]))
            throw new Exception("No mapping defined for old priority id '$idPriorityOld'");
        else
            return $this->prioritiesMapping[$idPriorityOld];
    }


    private function replaceIssue($idIssueOld)
    {
        if ($idIssueOld == null)
            return null;

        if (!isset($this->issuesMapping[$idIssueOld]))
            throw new Exception("No status defined for old status id '$idIssueOld'");
        else
            return $this->issuesMapping[$idIssueOld];
    }


    private function replaceStatus($idStatusOld)
    {
        if ($idStatusOld == null)
            return null;

        if (!isset($this->statusMapping[$idStatusOld]))
            throw new Exception("No status defined for old status id '$idStatusOld'");
        else
            return $this->statusMapping[$idStatusOld];
    }

    private function migrateCategories($idProjectOld)
    {
        $result = $this->dbOld->select('issue_categories', array('project_id' => $idProjectOld));
        $categoriesOld = $this->dbOld->getAssocArrays($result);
        foreach ($categoriesOld as $categoryOld)
        {
            $idCategoryOld = $categoryOld['id'];
            unset($categoryOld['id']);
            $categoryOld['project_id'] = $this->projectsMapping[$idProjectOld];
            $categoryOld['assigned_to_id'] = replaceUser($categoryOld['assigned_to_id']);

            $idCategoryNew = $this->dbNew->insert('issue_categories', $categoryOld);
            $this->categoriesMapping[$idCategoryOld] = $idCategoryNew;
        }
    }

    private function migrateVersions($idProjectOld)
    {
        $result = $this->dbOld->select('versions', array('project_id' => $idProjectOld));
        $versionsOld = $this->dbOld->getAssocArrays($result);
        foreach ($versionsOld as $versionOld)
        {
            $idVersionOld = $versionOld['id'];
            unset($versionOld['id']);
            $versionOld['project_id'] = $this->projectsMapping[$idProjectOld];

            $idVersionNew = $this->dbNew->insert('versions', $versionOld);
            $this->versionsMapping[$idVersionOld] = $idVersionNew;
        }
    }

    private function migrateJournals($idIssueOld)
    {
        $result = $this->dbOld->select('journals', array('journalized_id' => $idIssueOld));
        $journalsOld = $this->dbOld->getAssocArrays($result);
        foreach ($journalsOld as $journal)
        {
            $idJournalOld = $journal['id'];
            unset($journal['id']);

            // Update fields
            $journal['user_id'] = $this->replaceUser($journal['user_id']);
            $journal['journalized_id'] = $this->issuesMapping[$idIssueOld];

            $idJournalNew = $this->dbNew->insert('journals', $journal);
            $this->journalsMapping[$idJournalOld] = $idJournalNew;

            $this->migrateJournalsDetails($idJournalOld);
        }
    }

    private function migrateTimeEntries($idProjectOld)
    {
        $result = $this->dbOld->select('time_entries', array('project_id' => $idProjectOld));
        $timeEntriesOld = $this->dbOld->getAssocArrays($result);
        foreach ($timeEntriesOld as $timeEntry)
        {
            $idTimeEntryOld = $timeEntry['id'];
            unset($timeEntry['id']);

            // Update fields
            $timeEntry['project_id'] = $this->projectsMapping[$timeEntry['project_id']];
            $timeEntry['issue_id'] = $this->issuesMapping[$timeEntry['issue_id']];
            $timeEntry['user_id'] = $this->replaceUser($timeEntry['user_id']);

            $idTimeEntryNew = $this->dbNew->insert('time_entries', $timeEntry);
            $this->timeEntriesMapping[$idTimeEntryOld] = $idTimeEntryNew;
        }
    }

    private function migratemodules($idProjectOld)
    {
        $result = $this->dbOld->select('enabled_modules', array('project_id' => $idProjectOld));
        $modulesOld = $this->dbOld->getAssocArrays($result);
        foreach ($modulesOld as $module)
        {
            $idModuleOld = $module['id'];
            unset($module['id']);

            // Update fields
            $module['project_id'] = $this->projectsMapping[$module['project_id']];

            $idModuleNew = $this->dbNew->insert('enabled_modules', $module);
            $this->modulesMapping[$idModuleOld] = $idModuleNew;
        }
    }

    private function migrateJournalsDetails($idJournalOld)
    {
        $result = $this->dbOld->select('journal_details', array('journal_id' => $idJournalOld));
        $journalDetailsOld = $this->dbOld->getAssocArrays($result);
        foreach ($journalDetailsOld as $journalDetail)
        {
            unset($journalDetail['id']);

            // Update fields
            $journalDetail['journal_id'] = $this->journalsMapping[$idJournalOld];

            $this->dbNew->insert('journal_details', $journalDetail);
        }
    }

    private function migrateAttachments($idProjectOld)
    {
        $result = $this->dbOld->select('attachments', array('container_type'=> 'Issue'));
        $attachmentsOld = $this->dbOld->getAssocArrays($result);
        foreach ($attachmentsOld as $aOld)
        {
            if(!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->issuesMapping[$aOld['container_id']])){
                continue;
            }
            $idAOld = $aOld['id'];
            unset($aOld['id']);

            // Update fields for new version of issue
            $aOld['author_id'] = $this->replaceUser($aOld['author_id']);
            $aOld['container_id'] = $this->replaceIssue($aOld['container_id']);

            $idANew = $this->dbNew->insert('attachments', $aOld);
            $this->nbAt++;
        }
    }


    private function migrateIssues($idProjectOld)
    {
        $result = $this->dbOld->select('issues', array('project_id' => $idProjectOld));
        $issuesOld = $this->dbOld->getAssocArrays($result);
        foreach ($issuesOld as $issueOld)
        {
            $idIssueOld = $issueOld['id'];
            unset($issueOld['id']);

            // Update fields for new version of issue
            $issueOld['project_id'] = $this->projectsMapping[$idProjectOld];
            $issueOld['assigned_to_id'] = $this->replaceUser($issueOld['assigned_to_id']);
            $issueOld['author_id'] = $this->replaceUser($issueOld['author_id']);
            $issueOld['priority_id'] = $this->replacePriority($issueOld['priority_id']);
            $issueOld['status_id'] = $this->replaceStatus($issueOld['status_id']);

            if ($issueOld['fixed_version_id']) $issueOld['fixed_version_id'] = $this->versionsMapping[$issueOld['fixed_version_id']];

            $idIssueNew = $this->dbNew->insert('issues', $issueOld);
            $this->issuesMapping[$idIssueOld] = $idIssueNew;

            $this->migrateJournals($idIssueOld);
        }
    }

    function migrateProject($idProjectOld)
    {
        $result = $this->dbOld->select('projects', array('id' => $idProjectOld));
        $projectsOld = $this->dbOld->getAssocArrays($result);

        foreach ($projectsOld as $projectOld)
        {
            unset($projectOld['id']);
            $idProjectNew = $this->dbNew->insert('projects', $projectOld);
            $this->projectsMapping[$idProjectOld] = $idProjectNew;
            echo "migrating old redmine $idProjectOld => to new redmine $idProjectNew";
            $this->migrateCategories($idProjectOld);
            $this->migrateVersions($idProjectOld);
            $this->migrateIssues($idProjectOld);
            $this->migrateAttachments($idProjectOld);
            $this->migrateTimeEntries($idProjectOld);
            $this->migrateModules($idProjectOld);


        }
        echo 'projects: ' . count($this->projectsMapping) . " <br>\n";
        echo 'issues: ' . count($this->issuesMapping) . " <br>\n";
        echo 'attachments: ' . $this->nbAt . " <br>\n";
        echo 'categories: ' . count($this->categoriesMapping) . " <br>\n";
        echo 'versions: ' . count($this->versionsMapping) . " <br>\n";
        echo 'journals: ' . count($this->journalsMapping) . " <br>\n";
        echo 'time entries: ' . count($this->timeEntriesMapping) . " <br>\n";
        echo 'modules enabled: ' . count($this->modulesMapping) . " <br>\n";
    }
}



$migrator = new Migrator(   'localhost', 'old_redmine', 'root', 'password',
                            'localhost', 'new_redmine', 'root', 'password');
$migrator->migrateProject(5);

