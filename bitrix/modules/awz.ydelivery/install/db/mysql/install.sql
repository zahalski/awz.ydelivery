create table if not exists b_awz_ydelivery_offer (
	ID int(18) NOT NULL auto_increment,
	ORDER_ID int(18) NOT NULL,
	OFFER_ID varchar(255) NOT NULL,
    HISTORY varchar(6255) DEFAULT NULL,
    HISTORY_FIN varchar(1) DEFAULT NULL,
    CREATE_DATE datetime NOT NULL,
    LAST_DATE datetime NOT NULL,
    LAST_STATUS varchar(255) DEFAULT NULL,
	primary key (ID),
	unique IX_OFFER_ID (OFFER_ID),
	index IX_ORDER_ID (ORDER_ID),
	index IX_CHECK_DATE (LAST_DATE, HISTORY_FIN)
);
CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_pvz` (
    ID int(18) NOT NULL AUTO_INCREMENT,
    PVZ_ID varchar(255) NOT NULL,
    DOST_DAY int(7) DEFAULT NULL,
    LAST_UP datetime DEFAULT NULL,
    PRM longtext,
    PRIMARY KEY (`ID`),
    unique IX_PVZ_ID (PVZ_ID)
);
CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_pvz_ext` (
    ID int(18) NOT NULL AUTO_INCREMENT,
    PVZ_ID varchar(255) NOT NULL,
    EXT_ID varchar(255) NOT NULL,
    PRIMARY KEY (`ID`),
    unique IX_EXT_ID (EXT_ID),
    index IX_PVZ_ID (PVZ_ID)
);