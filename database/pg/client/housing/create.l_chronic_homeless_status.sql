CREATE TABLE tbl_l_chronic_homeless_status (
	chronic_homeless_status_code	VARCHAR(10) PRIMARY KEY,
	description VARCHAR(100) NOT NULL UNIQUE,
    --system fields
    added_by                        INTEGER NOT NULL REFERENCES tbl_staff (staff_id),
    added_at                        TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by                      INTEGER NOT NULL  REFERENCES tbl_staff (staff_id),
    changed_at                      TIMESTAMP(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_deleted                      BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at                      TIMESTAMP(0) CHECK ((NOT is_deleted AND deleted_at IS
 NULL) OR (is_deleted AND deleted_at IS NOT NULL)),
    deleted_by                      INTEGER REFERENCES tbl_staff(staff_id)
                                       CHECK ((NOT is_deleted AND deleted_by IS NULL) OR (is_deleted AND deleted_by IS NOT NULL)),
    deleted_comment         TEXT,
    sys_log                 TEXT
);

INSERT INTO tbl_l_chronic_homeless_status VALUES ('NO','No',sys_user(),current_timestamp,sys_user(),current_timestamp);
INSERT INTO tbl_l_chronic_homeless_status VALUES ('YES_1','Yes, homeless for 1+ years',sys_user(),current_timestamp,sys_user(),current_timestamp);
INSERT INTO tbl_l_chronic_homeless_status VALUES ('YES_3','Yes, 4 or more episodes in 3 years',sys_user(),current_timestamp,sys_user(),current_timestamp);
INSERT INTO tbl_l_chronic_homeless_status VALUES ('YES_BOTH','Yes, homeless for 1+ years, and 4 or more episodes in last 3 years',sys_user(),current_timestamp,sys_user(),current_timestamp);
INSERT INTO tbl_l_chronic_homeless_status VALUES ('UNKNOWN','Unknown',sys_user(),current_timestamp,sys_user(),current_timestamp);

CREATE VIEW l_chronic_homeless_status AS (SELECT * FROM tbl_l_chronic_homeless_status WHERE NOT is_deleted);

