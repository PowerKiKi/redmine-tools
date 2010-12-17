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
  *   1. update manual mapping for users and priorities (see $usersMapping and $prioritiesMapping)
  *   2. update database connection data and project ID to migrate
  *   3. run the script
  */


require_once(dirname(__FILE__) . '/Library/DBMysql.php');

class Migrator
{
	var $dbOld = null;
	var $dbNew = null;
	
	var $usersMapping = array(
			1 => 10,
			5 => 35,
			7 => 10,
			6 => 10,
		);
		
	var $prioritiesMapping = array(
			15 => 3,
			16 => 4,
			17 => 5,
			18 => 6,
			19 => 7,
		);
	
	var $projectsMapping = array();
	var $categoriesMapping = array();
	var $versionsMapping = array();
	var $journalsMapping = array();
	var $issuesMapping = array();
	var $timeEntriesMapping = array();
	var $modulesMapping = array();
	
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
			unset($versionOld['status']);
			unset($versionOld['sharing']);
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
	
	private function migrateIssues($idProjectOld)
	{
		$result = $this->dbOld->select('issues', array('project_id' => $idProjectOld));
		$issuesOld = $this->dbOld->getAssocArrays($result);
		foreach ($issuesOld as $issueOld)
		{
			$idIssueOld = $issueOld['id'];
			unset($issueOld['id']);
			unset($issueOld['parent_id']);
			unset($issueOld['root_id']);
			unset($issueOld['lft']);
			unset($issueOld['rgt']);
			
			// Update fields for new version of issue
			$issueOld['project_id'] = $this->projectsMapping[$idProjectOld];
			$issueOld['assigned_to_id'] = $this->replaceUser($issueOld['assigned_to_id']);
			$issueOld['author_id'] = $this->replaceUser($issueOld['author_id']);
			$issueOld['priority_id'] = $this->replacePriority($issueOld['priority_id']);
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
			unset($projectOld['lft']);
			unset($projectOld['rgt']);
			$idProjectNew = $this->dbNew->insert('projects', $projectOld);
			$this->projectsMapping[$idProjectOld] = $idProjectNew;
			
			$this->migrateCategories($idProjectOld);
			$this->migrateVersions($idProjectOld);
			$this->migrateIssues($idProjectOld);
			$this->migrateTimeEntries($idProjectOld);
			$this->migrateModules($idProjectOld);
		}
		
		echo 'projects: ' . count($this->projectsMapping) . " <br>\n";
		echo 'issues: ' . count($this->issuesMapping) . " <br>\n";
		echo 'categories: ' . count($this->categoriesMapping) . " <br>\n";
		echo 'versions: ' . count($this->versionsMapping) . " <br>\n";
		echo 'journals: ' . count($this->journalsMapping) . " <br>\n";
		echo 'time entries: ' . count($this->timeEntriesMapping) . " <br>\n";
		echo 'modules enabled: ' . count($this->modulesMapping) . " <br>\n";		
	}
}



$migrator = new Migrator(	'localhost', 'old_redmine', 'root', 'password',
							'localhost', 'new_redmine', 'root', 'password');
$migrator->migrateProject(4);

