<?php

/**
 * See README.md for instructions
 */

require_once dirname(__FILE__) . '/Library/DBMysql.php';

class migrator
{
    /**
     * @var DBMysql
     */
    private $dbOld = null;

    /**
     * @var DBMysql
     */
    private $dbNew = null;

    private $usersMapping = array(
            1 =>  1,
            7  =>  247,
        );

    private $prioritiesMapping = array(
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
        );

    private $statusMapping = array(
            1   =>  1,
            2   =>  2,
            3   =>  3,
            4   =>  4,
            5   =>  5,
            6   =>  6,
            7   =>  7,
            8   =>  8,
            9   =>  9,
            10  =>  10,
            11  =>  11,
            12  =>  12,
    );

    private $trackersMapping = array(
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
    );

    private $projectsMapping = array();
    private $categoriesMapping = array();
    private $versionsMapping = array();
    private $journalsMapping = array();
    private $issuesMapping = array();
    private $issuesParentsMapping = array();
    private $issuesRelationsMapping = array();
    private $timeEntriesMapping = array();
    private $modulesMapping = array();

    private $watchersMapping = array();
    private $boardsMapping = array();
    private $messagesMapping = array();
    private $newsMapping = array();
    private $documentsMapping = array();
    private $wikisMapping = array();
    private $wikipagesMapping = array();
    private $wikiContentVersionsMapping = array();
    private $wikiContentsMapping = array();

    private $nbAt = 0;

    public function __construct($host1, $db1, $user1, $pass1, $host2, $db2, $user2, $pass2)
    {
        $this->dbOld = new DBMysql($host1, $user1, $pass1);
        $this->dbOld->connect($db1);

        $this->dbNew = new DBMysql($host2, $user2, $pass2);
        $this->dbNew->connect($db2);
    }

    private function replaceUser($idUserOld)
    {
        if ($idUserOld == null) {
            return null;
        }

        if (!isset($this->usersMapping[$idUserOld])) {
            throw new Exception("No mapping defined for old user id '$idUserOld'");
        } else {
            return $this->usersMapping[$idUserOld];
        }
    }

    private function replaceMessage($idMessageOld)
    {
        if ($idMessageOld == null) {
            return null;
        }

        if (!isset($this->messagesMapping[$idMessageOld])) {
            throw new Exception("No mapping defined for old message id '$idMessageOld'");
        } else {
            return $this->messagesMapping[$idMessageOld];
        }
    }

    private function replacePriority($idPriorityOld)
    {
        if ($idPriorityOld == null) {
            return null;
        }

        if (!isset($this->prioritiesMapping[$idPriorityOld])) {
            throw new Exception("No mapping defined for old priority id '$idPriorityOld'");
        } else {
            return $this->prioritiesMapping[$idPriorityOld];
        }
    }

    private function replaceIssue($idIssueOld)
    {
        if ($idIssueOld == null) {
            return null;
        }

        if (!isset($this->issuesMapping[$idIssueOld])) {
            throw new Exception("No status defined for old status id '$idIssueOld'");
        } else {
            return $this->issuesMapping[$idIssueOld];
        }
    }

    private function replaceStatus($idStatusOld)
    {
        if ($idStatusOld == null) {
            return null;
        }

        if (!isset($this->statusMapping[$idStatusOld])) {
            throw new Exception("No status defined for old status id '$idStatusOld'");
        } else {
            return $this->statusMapping[$idStatusOld];
        }
    }

    private function replaceTracker($idTrackerOld)
    {
        if ($idTrackerOld == null) {
            return null;
        }

        if (!isset($this->trackersMapping[$idTrackerOld])) {
            throw new Exception("No status defined for old tracker id '$idTrackerOld'");
        } else {
            return $this->trackersMapping[$idTrackerOld];
        }
    }

    private function replaceCategory($idCategoryOld)
    {
        if ($idCategoryOld == null) {
            return null;
        }

        if (!isset($this->categoriesMapping[$idCategoryOld])) {
            throw new Exception("No category defined for old category id '$idCategoryOld'");
        } else {
            return $this->categoriesMapping[$idCategoryOld];
        }
    }

    private function migrateCategories($idProjectOld)
    {
        $result = $this->dbOld->select('issue_categories', array('project_id' => $idProjectOld));
        $categoriesOld = $this->dbOld->getAssocArrays($result);
        foreach ($categoriesOld as $categoryOld) {
            $idCategoryOld = $categoryOld['id'];
            unset($categoryOld['id']);
            $categoryOld['project_id'] = $this->projectsMapping[$idProjectOld];
            $categoryOld['assigned_to_id'] = $this->replaceUser($categoryOld['assigned_to_id']);

            $idCategoryNew = $this->dbNew->insert('issue_categories', $categoryOld);
            $this->categoriesMapping[$idCategoryOld] = $idCategoryNew;
        }
    }

    private function migrateVersions($idProjectOld)
    {
        $result = $this->dbOld->select('versions', array('project_id' => $idProjectOld));
        $versionsOld = $this->dbOld->getAssocArrays($result);
        foreach ($versionsOld as $versionOld) {
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
        foreach ($journalsOld as $journal) {
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
        foreach ($timeEntriesOld as $timeEntry) {
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
        foreach ($modulesOld as $module) {
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
        foreach ($journalDetailsOld as $journalDetail) {
            unset($journalDetail['id']);

            // Update fields
            $journalDetail['journal_id'] = $this->journalsMapping[$idJournalOld];

            $this->dbNew->insert('journal_details', $journalDetail);
        }
    }

    private function migrateAttachments($idProjectOld)
    {
        // $result = $this->dbOld->select('attachments', array('container_type'=> 'Issue'));
        $result = $this->dbOld->select('attachments');
        $attachmentsOld = $this->dbOld->getAssocArrays($result);
        foreach ($attachmentsOld as $aOld) {
            if ($aOld['container_type'] == 'Issue' && count($this->issuesMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->issuesMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->replaceIssue($aOld['container_id']);
                }
            } elseif ($aOld['container_type'] == 'Version' && count($this->versionsMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->versionsMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->versionsMapping[$aOld['container_id']];
                }
            } elseif ($aOld['container_type'] == 'Project' && count($this->projectsMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->projectsMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->projectsMapping[$aOld['container_id']];
                }
            } elseif ($aOld['container_type'] == 'Message' && count($this->messagesMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->messagesMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->messagesMapping[$aOld['container_id']];
                }
            } elseif ($aOld['container_type'] == 'WikiPage' && count($this->wikipagesMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->wikipagesMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->wikipagesMapping[$aOld['container_id']];
                }
            } elseif ($aOld['container_type'] == 'Document' && count($this->documentsMapping) > 0) {
                if (!isset($this->usersMapping[$aOld['author_id']]) || !isset($this->documentsMapping[$aOld['container_id']])) {
                    continue;
                } else {
                    $aOld['container_id'] = $this->documentsMapping[$aOld['container_id']];
                }
            } else {
                continue;
            }

            $idAOld = $aOld['id'];
            unset($aOld['id']);

            // Update fields for new version of issue
            $aOld['author_id'] = $this->replaceUser($aOld['author_id']);

            $idANew = $this->dbNew->insert('attachments', $aOld);
            $this->nbAt++;
        }
    }

    private function migrateWatchers($idProjectOld)
    {
        $result = $this->dbOld->select('watchers');
        $watchersOld = $this->dbOld->getAssocArrays($result);
        foreach ($watchersOld as $watcher) {
            $idWatcherOld = $watcher['id'];
            unset($watcher['id']);

            if ($watcher['watchable_type'] == 'Issue' && count($this->issuesMapping) > 0) {
                if (!isset($this->usersMapping[$watcher['user_id']]) || !isset($this->issuesMapping[$watcher['watchable_id']])) {
                    continue;
                } else {
                    $watcher['watchable_id'] = $this->issuesMapping[$watcher['watchable_id']];
                }
            } elseif ($watcher['watchable_type'] == 'Board' && count($this->boardsMapping) > 0) {
                if (!isset($this->usersMapping[$watcher['user_id']]) || !isset($this->boardsMapping[$watcher['watchable_id']])) {
                    continue;
                } else {
                    $watcher['watchable_id'] = $this->boardsMapping[$watcher['watchable_id']];
                }
            } elseif ($watcher['watchable_type'] == 'News' && count($this->newsMapping) > 0) {
                if (!isset($this->usersMapping[$watcher['user_id']]) || !isset($this->newsMapping[$watcher['watchable_id']])) {
                    continue;
                } else {
                    $watcher['watchable_id'] = $this->newsMapping[$watcher['watchable_id']];
                }
            } elseif ($watcher['watchable_type'] == 'Message' && count($this->messagesMapping) > 0) {
                if (!isset($this->usersMapping[$watcher['user_id']]) || !isset($this->messagesMapping[$watcher['watchable_id']])) {
                    continue;
                } else {
                    $watcher['watchable_id'] = $this->messagesMapping[$watcher['watchable_id']];
                }
            } elseif ($watcher['watchable_type'] == 'WikiPage' && count($this->wikipagesMapping) > 0) {
                if (!isset($this->usersMapping[$watcher['user_id']]) || !isset($this->wikipagesMapping[$watcher['watchable_id']])) {
                    continue;
                } else {
                    $watcher['watchable_id'] = $this->wikipagesMapping[$watcher['watchable_id']];
                }
            } else {
                continue;
            }

            // Update fields for watchers
            $watcher['user_id'] = $this->replaceUser($watcher['user_id']);

            $idWatcherNew = $this->dbNew->insert('watchers', $watcher);
            $this->watchersMapping[$idWatcherOld] = $idWatcherNew;
        }
    }

    // messages shows could be empty, parent_id need to set null if it is 0
    private function migrateMessages($idBoardOld)
    {
        $result = $this->dbOld->select('messages', array('board_id' => $idBoardOld));
        $messagesOld = $this->dbOld->getAssocArrays($result);
        foreach ($messagesOld as $message) {
            $idMessageOld = $message['id'];
            unset($message['id']);

            // Update fields
            $message['author_id'] = $this->replaceUser($message['author_id']);
            $message['board_id'] = $this->boardsMapping[$idBoardOld];
            $message['parent_id'] = $this->replaceMessage($message['parent_id']);
            // last_reply_id not processed

            $idMessageNew = $this->dbNew->insert('messages', $message);
            $this->messagesMapping[$idMessageOld] = $idMessageNew;
        }
    }

    private function migrateBoards($idProjectOld)
    {
        $result = $this->dbOld->select('boards', array('project_id' => $idProjectOld));
        $boardsOld = $this->dbOld->getAssocArrays($result);
        foreach ($boardsOld as $boardOld) {
            $idBoardOld = $boardOld['id'];
            unset($boardOld['id']);

            // Update fields for new version of board
            $boardOld['project_id'] = $this->projectsMapping[$idProjectOld];

            $idBoardNew = $this->dbNew->insert('boards', $boardOld);
            $this->boardsMapping[$idBoardOld] = $idBoardNew;

            $this->migrateMessages($idBoardOld);
        }
    }

    private function migrateNews($idProjectOld)
    {
        $result = $this->dbOld->select('news', array('project_id' => $idProjectOld));
        $newssOld = $this->dbOld->getAssocArrays($result);
        foreach ($newssOld as $newsOld) {
            $idNewsOld = $newsOld['id'];
            unset($newsOld['id']);

            // Update fields for new version of news
            $newsOld['project_id'] = $this->projectsMapping[$idProjectOld];
            $newsOld['author_id'] = $this->replaceUser($newsOld['author_id']);

            $idNewsNew = $this->dbNew->insert('news', $newsOld);
            $this->newsMapping[$idNewsOld] = $idNewsNew;
        }
    }

    private function migrateDocuments($idProjectOld)
    {
        $result = $this->dbOld->select('documents', array('project_id' => $idProjectOld));
        $documentsOld = $this->dbOld->getAssocArrays($result);
        foreach ($documentsOld as $documentOld) {
            $idDocumentsOld = $documentOld['id'];
            unset($documentOld['id']);

            // Update fields for new version of document
            $documentOld['project_id'] = $this->projectsMapping[$idProjectOld];

            $idDocumentsNew = $this->dbNew->insert('documents', $documentOld);
            $this->documentsMapping[$idDocumentsOld] = $idDocumentsNew;
        }
    }

    private function migrateWikiContentVersions($idWikiPageOld, $idWikiContentOld)
    {
        $result = $this->dbOld->select('wiki_content_versions', array('page_id' => $idWikiPageOld, 'wiki_content_id' => $idWikiContentOld));
        $wikiContentVersionsOld = $this->dbOld->getAssocArrays($result);
        foreach ($wikiContentVersionsOld as $wikiContentVersionOld) {
            $idWikiContentVersionOld = $wikiContentVersionOld['id'];
            unset($wikiContentVersionOld['id']);

            // Update fields for new version of wiki content versions
            $wikiContentVersionOld['page_id'] = $this->wikipagesMapping[$idWikiPageOld];
            $wikiContentVersionOld['author_id'] = $this->replaceUser($wikiContentVersionOld['author_id']);
            $wikiContentVersionOld['wiki_content_id'] = $this->wikiContentsMapping[$idWikiContentOld];

            $idWikiContentVersionNew = $this->dbNew->insert('wiki_content_versions', $wikiContentVersionOld);
            $this->wikiContentVersionsMapping[$idWikiContentVersionOld] = $idWikiContentVersionNew;
        }
    }

    private function migrateWikiContents($idWikiPageOld)
    {
        $result = $this->dbOld->select('wiki_contents', array('page_id' => $idWikiPageOld));
        $wikiContentsOld = $this->dbOld->getAssocArrays($result);
        foreach ($wikiContentsOld as $wikiContentOld) {
            $idWikiContentOld = $wikiContentOld['id'];
            unset($wikiContentOld['id']);

            // Update fields for new version of wiki content
            $wikiContentOld['page_id'] = $this->wikipagesMapping[$idWikiPageOld];
            $wikiContentOld['author_id'] = $this->replaceUser($wikiContentOld['author_id']);

            $idWikiContentNew = $this->dbNew->insert('wiki_contents', $wikiContentOld);
            $this->wikiContentsMapping[$idWikiContentOld] = $idWikiContentNew;

            $this->migrateWikiContentVersions($idWikiPageOld, $idWikiContentOld);
        }
    }

    private function migrateWikiPageParents($idWikiOld)
    {
        $result = $this->dbOld->query("SELECT * FROM wiki_pages" . " WHERE wiki_id =" . $idWikiOld . " and parent_id > 0");
        $wikipagesOld = $this->dbOld->getAssocArrays($result);
        foreach ($wikipagesOld as $wikipageOld) {
            $idWikiPageNew = $this->wikipagesMapping[$wikipageOld['id']];
            unset($wikipageOld['id']);

            // Update fields for new version of wiki page parent_id
            $wikipageOld['wiki_id'] = $this->wikisMapping[$idWikiOld];
            $wikipageOld['parent_id'] = $this->wikipagesMapping[$wikipageOld['parent_id']];

            $idWikiPageNew = $this->dbNew->update('wiki_pages', $wikipageOld, array('id' => idWikiPageNew));
        }
    }

    private function migrateWikiPages($idWikiOld)
    {
        $result = $this->dbOld->select('wiki_pages', array('wiki_id' => $idWikiOld));
        $wikipagesOld = $this->dbOld->getAssocArrays($result);
        foreach ($wikipagesOld as $wikipageOld) {
            $idWikiPageOld = $wikipageOld['id'];
            unset($wikipageOld['id']);

            // Update fields for new version of wiki pages
            $wikipageOld['wiki_id'] = $this->wikisMapping[$idWikiOld];
            $wikipageOld['parent_id'] = null;        // can not imagine the mapping

            $idWikiPageNew = $this->dbNew->insert('wiki_pages', $wikipageOld);
            $this->wikipagesMapping[$idWikiPageOld] = $idWikiPageNew;

            $this->migrateWikiContents($idWikiPageOld);
        }

        $this->migrateWikiPageParents($idWikiPageOld);
    }

    private function migrateWikis($idProjectOld)
    {
        $result = $this->dbOld->select('wikis', array('project_id' => $idProjectOld));
        $wikisOld = $this->dbOld->getAssocArrays($result);
        foreach ($wikisOld as $wikiOld) {
            $idWikiOld = $wikiOld['id'];
            unset($wikiOld['id']);

            // Update fields for new version of wiki
            $wikiOld['project_id'] = $this->projectsMapping[$idProjectOld];

            $idWikiNew = $this->dbNew->insert('wikis', $wikiOld);
            $this->wikisMapping[$idWikiOld] = $idWikiNew;

            $this->migrateWikiPages($idWikiOld);
        }
    }

    private function migrateIssues($idProjectOld)
    {
        $result = $this->dbOld->select('issues', array('project_id' => $idProjectOld));
        $issuesOld = $this->dbOld->getAssocArrays($result);
        foreach ($issuesOld as $issueOld) {
            $idIssueOld = $issueOld['id'];
            unset($issueOld['id']);

            // Update fields for new version of issue
            $issueOld['project_id'] = $this->projectsMapping[$idProjectOld];
            $issueOld['assigned_to_id'] = $this->replaceUser($issueOld['assigned_to_id']);
            $issueOld['author_id'] = $this->replaceUser($issueOld['author_id']);
            $issueOld['priority_id'] = $this->replacePriority($issueOld['priority_id']);
            $issueOld['status_id'] = $this->replaceStatus($issueOld['status_id']);
            $issueOld['category_id'] = $this->replaceCategory($issueOld['category_id']);
            $issueOld['tracker_id'] = $this->replaceTracker($issueOld['tracker_id']);

            if ($issueOld['fixed_version_id']) {
                $issueOld['fixed_version_id'] = $this->versionsMapping[$issueOld['fixed_version_id']];
            }

            $idIssueNew = $this->dbNew->insert('issues', $issueOld);
            $this->issuesMapping[$idIssueOld] = $idIssueNew;

            $this->migrateJournals($idIssueOld);
        }
    }

        private function migrateIssuesParents($idProjectOld)
    {
        $result = $this->dbOld->select('issues', array('project_id' => $idProjectOld));
        $issuesOld = $this->dbOld->getAssocArrays($result);
        foreach ($issuesOld as $issueOld) {
            $idIssueOld = $issueOld['id'];
            if ($issueOld['parent_id'] > 0) {

                // Update parents for issues
                $issueUpdate['parent_id'] = $this->replaceIssue($issueOld['parent_id']);

                $idParentIssueNew = $this->dbNew->update('issues', $issueUpdate, array('id' => $this->issuesMapping[$issueOld['id']]));
                $this->issuesParentsMapping[$idIssueOld] = $idParentIssueNew;
            } 
        }
    }

        private function migrateIssueRelations($idIssueOld)
    {
        $result = $this->dbOld->select('issue_relations');
        $relationsOld = $this->dbOld->getAssocArrays($result);
        foreach ($relationsOld as $relation) {
            $idRelationOld = $relation['id'];
            unset($relation['id']);
       
            // Update fields for relations
            $relation['issue_from_id'] = $this->replaceIssue($relation['issue_from_id']);
            $relation['issue_to_id'] = $this->replaceIssue($relation['issue_to_id']);
            
            $idRelationNew = $this->dbNew->insert('issue_relations', $relation);
            $this->issuesRelationsMapping[$idRelationOld] = $idRelationNew;
        }
    }

    public function migrateProject($idProjectOld)
    {
        $result = $this->dbOld->select('projects', array('id' => $idProjectOld));
        $projectsOld = $this->dbOld->getAssocArrays($result);

        foreach ($projectsOld as $projectOld) {
            unset($projectOld['id']);
            $idProjectNew = $this->dbNew->insert('projects', $projectOld);
            $this->projectsMapping[$idProjectOld] = $idProjectNew;
            echo "migrating old redmine $idProjectOld => to new redmine $idProjectNew <br>\n";
            $this->migrateVersions($idProjectOld);
            $this->migrateCategories($idProjectOld);
            $this->migrateIssues($idProjectOld);
            $this->migrateIssuesParents($idProjectOld);
            $this->migrateIssueRelations($idProjectOld);
            $this->migrateNews($idProjectOld);
            $this->migrateDocuments($idProjectOld);
            $this->migrateBoards($idProjectOld);
            $this->migrateTimeEntries($idProjectOld);
            $this->migrateModules($idProjectOld);
            $this->migrateWikis($idProjectOld);
            $this->migrateAttachments($idProjectOld);
            $this->migrateWatchers($idProjectOld);
        }

        echo 'projects: ' . count($this->projectsMapping) . " <br>\n";
        echo 'issues: ' . count($this->issuesMapping) . " <br>\n";
        echo 'issue parents: ' . count($this->issuesParentsMapping) . " <br>\n";
        echo 'issue relations: ' . count($this->issuesRelationsMapping) . " <br>\n";
        echo 'attachments: ' . $this->nbAt . " <br>\n";
        echo 'categories: ' . count($this->categoriesMapping) . " <br>\n";
        echo 'versions: ' . count($this->versionsMapping) . " <br>\n";
        echo 'news: ' . count($this->newsMapping) . " <br>\n";
        echo 'documents: ' . count($this->documentsMapping) . " <br>\n";
        echo 'journals: ' . count($this->journalsMapping) . " <br>\n";
        echo 'watchers: ' . count($this->watchersMapping) . " <br>\n";
        echo 'boards: ' . count($this->boardsMapping) . " <br>\n";
        echo 'messages: ' . count($this->messagesMapping) . " <br>\n";
        echo 'time entries: ' . count($this->timeEntriesMapping) . " <br>\n";
        echo 'modules enabled: ' . count($this->modulesMapping) . " <br>\n";
        echo 'wikis: ' . count($this->wikisMapping) . " <br>\n";
        echo 'wiki pages: ' . count($this->wikipagesMapping) . " <br>\n";
        echo 'wiki contents: ' . count($this->wikiContentsMapping) . " <br>\n";
        echo 'wiki content versions: ' . count($this->wikiContentVersionsMapping) . " <br>\n";
    }
}


$migrator = new migrator('localhost', 'redmine', 'root', '',
                            'localhost', 'redmine_new',   'root', '');
$migrator->migrateProject(86);
