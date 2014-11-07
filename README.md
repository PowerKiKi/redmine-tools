Redmine migrator
================

This script migrate Redmine project from one instance of Redmine to another one. It will
import the following objects:

  - project
  - categories
  - versions
  - issues
  - journals
  - logged time
  - documents
  - news
  - watchers
  - attachment
  - wikis & wiki_pages & wike_contents & wiki_content_versions
  - boards & messages
  - modules status (wether it is active for the project)

It relies on manual mapping for users and priorities as they should already exist in target database.

All modifications of the target database are **live**. So it is strongly advised to test against a dummy database first.

USAGE
-----

  1. update manual mapping for users, status and priorities (see $usersMapping and $prioritiesMapping)
  2. update database connection data and project ID to migrate
  3. run the script
