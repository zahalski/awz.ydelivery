create table if not exists b_awz_ydelivery_offer (
	ID int(18) NOT NULL auto_increment,
	ORDER_ID int(18) NOT NULL,
	OFFER_ID varchar(255) NOT NULL,
    HISTORY varchar(6255) DEFAULT NULL,
    HISTORY_FIN varchar(1) DEFAULT NULL,
    CREATE_DATE datetime NOT NULL,
    LAST_DATE datetime NOT NULL,
	primary key (ID),
	unique IX_OFFER_ID (OFFER_ID),
	index IX_ORDER_ID (ORDER_ID),
	index IX_CHECK_DATE (LAST_DATE, HISTORY_FIN)
);
CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_pvz` (
    ID int(18) NOT NULL AUTO_INCREMENT,
    PVZ_ID varchar(255) NOT NULL,
    PRM varchar(6255) DEFAULT NULL,
    PRIMARY KEY (`ID`),
    unique IX_PVZ_ID (PVZ_ID)
);