/* ================== HLstatsZ-Classics ==================
   ========= SimpleAdmin -> SourceBans migration =========
                     sa_bans -> sb_bans
   =======================================================
*/

SET @sourceDB    = 'kpsbans';
SET @sourceTable = 'sa_bans';

SET @targetDB    = 'sourcebans';
SET @targetTable = 'sb_bans';
SET @targetServerID = '0';
SET @ureason = 'Migrated from SimpleAdmin'; /*unban reason*/

START TRANSACTION;

SET @sql = CONCAT(
'INSERT INTO `', @targetDB, '`.`', @targetTable, '` ',
'    (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, RemovedBy, RemoveType, RemovedOn, ureason, type) ',
'SELECT ',
'    NULLIF(TRIM(b.player_ip), ''''), ',
'    NULLIF(TRIM(b.player_steamid), ''''), ',
'    COALESCE(b.player_name, ''unnamed''), ',
'    UNIX_TIMESTAMP(b.created), ',
    /* Handle permanent bans (duration = 0) and NULL ends */
'    CASE ',
'        WHEN b.duration = 0 THEN 0 ',
'        WHEN b.ends IS NULL THEN 0 ',
'        ELSE UNIX_TIMESTAMP(b.ends) ',
'    END, ',
    /* Preserve duration field - 0 means permanent */
'    CASE ',
'        WHEN b.duration = 0 THEN 0 ',
'        ELSE b.duration ',
'    END, ',
'    COALESCE(b.reason, ''No reason provided''), ',
    /* Match admin by Steam ID from sb_admins table */
'    COALESCE( ',
'        (SELECT aid FROM `', @targetDB, '`.sb_admins ',
'         WHERE authid COLLATE utf8mb4_general_ci = b.admin_steamid COLLATE utf8mb4_general_ci ',
'         LIMIT 1), ',
'        0 ',
'    ), ',
'    COALESCE(b.admin_name, ''Unknown Admin''), ',
'    @targetServerID, ',
    /* RemovedBy - set to unban_id if unbanned */
'    CASE ',
'        WHEN b.status = ''UNBANNED'' THEN COALESCE(b.unban_id, 0) ',
'        ELSE NULL ',
'    END, ',
    /* RemoveType - U for unbanned, E for expired */
'    CASE ',
'        WHEN b.status = ''UNBANNED'' THEN ''U'' ',
'        WHEN b.status = ''EXPIRED'' THEN ''E'' ',
'        ELSE NULL ',
'    END, ',
    /* RemovedOn timestamp */
'    CASE ',
'        WHEN b.status = ''UNBANNED'' OR b.status = ''EXPIRED'' THEN UNIX_TIMESTAMP(COALESCE(b.ends, NOW())) ',
'        ELSE NULL ',
'    END, ',
    /* Unban reason */
'    CASE ',
'        WHEN b.status = ''UNBANNED'' THEN @ureason ',
'        ELSE NULL ',
'    END, ',
    /* Type - always 0 for Steam ID bans */
'    0 ',
'FROM `', @sourceDB, '`.`', @sourceTable, '` b'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display migration results
SELECT 
    ROW_COUNT() AS total_bans_migrated,
    (SELECT COUNT(*) FROM sourcebans.sb_bans WHERE aid > 0 AND created >= UNIX_TIMESTAMP(NOW() - INTERVAL 1 MINUTE)) AS matched_admins,
    (SELECT COUNT(*) FROM sourcebans.sb_bans WHERE aid = 0 AND created >= UNIX_TIMESTAMP(NOW() - INTERVAL 1 MINUTE)) AS unmatched_admins,
    (SELECT COUNT(*) FROM sourcebans.sb_bans WHERE length = 0 AND created >= UNIX_TIMESTAMP(NOW() - INTERVAL 1 MINUTE)) AS permanent_bans;

COMMIT;