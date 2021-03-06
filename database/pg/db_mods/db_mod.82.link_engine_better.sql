BEGIN;

/*
 * db_mod.TEMPLATE
 */
 
/*
NOTE:  I believe it is not possible to know the
git SHA ID before actually making a commit.

It is possible to know a git tag, and include
that in the commit.
*/

INSERT INTO tbl_db_revision_history 
	(
	db_revision_code,
	db_revision_description,
	agency_flavor_code,
	git_sha,
	git_tag,
	applied_at,
	comment,
	added_by,
	changed_by
	)

	 VALUES (
		'LINK_ENGINE_BETTER', /*UNIQUE_DB_MOD_NAME */
		'Adds optional rec_init as 5th argument to link_engine',
		'AGENCY_CORE', /* Which flavor of AGENCY.  AGENCY_CORE applies to all installations */
		'', /* git SHA ID, if applicable */
		'db_mod.82', /* git tag, if applicable */
		current_timestamp, /* Applied at */
		'', /* comment */
		sys_user(),
		sys_user()
	 );

\i ../agency_core/functions/create.functions.link_engine.sql

COMMIT;

