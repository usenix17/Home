alter table courses add column `useCAS` int(1) NOT NULL DEFAULT '0' AFTER useEbiz;
alter table courses add column `cas_host` varchar(255) AFTER useCAS;
alter table courses add column `cas_port` int(5) AFTER cas_host;
alter table courses add column `cas_context` varchar(255) AFTER cas_port;
ALTER TABLE users MODIFY COLUMN `password` char(160) DEFAULT '';
ALTER TABLE users MODIFY COLUMN `realName` VARCHAR(100) DEFAULT '';

