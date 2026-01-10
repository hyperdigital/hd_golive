CREATE TABLE tx_hdgolive_session (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  shared tinyint(4) unsigned DEFAULT '0' NOT NULL,
  closed tinyint(4) unsigned DEFAULT '0' NOT NULL,
  closed_time int(11) unsigned DEFAULT '0' NOT NULL,
  closed_by int(11) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY closed (closed)
);

CREATE TABLE tx_hdgolive_pagecheck (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  session int(11) unsigned DEFAULT '0' NOT NULL,
  page int(11) unsigned DEFAULT '0' NOT NULL,
  status tinyint(4) unsigned DEFAULT '0' NOT NULL,
  checked tinyint(4) unsigned DEFAULT '0' NOT NULL,
  checked_time int(11) unsigned DEFAULT '0' NOT NULL,
  checked_by int(11) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY session (session),
  KEY page (page)
);

CREATE TABLE tx_hdgolive_checkitem (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  item_key varchar(128) DEFAULT '' NOT NULL,
  description text,
  site_identifier varchar(255) DEFAULT '' NOT NULL,
  sorting int(11) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY item_key (item_key),
  KEY site_identifier (site_identifier)
);

CREATE TABLE tx_hdgolive_itemcheck (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  session int(11) unsigned DEFAULT '0' NOT NULL,
  item_key varchar(128) DEFAULT '' NOT NULL,
  status tinyint(4) unsigned DEFAULT '0' NOT NULL,
  checked tinyint(4) unsigned DEFAULT '0' NOT NULL,
  checked_time int(11) unsigned DEFAULT '0' NOT NULL,
  checked_by int(11) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY session (session),
  KEY item_key (item_key)
);

CREATE TABLE tx_hdgolive_note (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  sorting int(11) unsigned DEFAULT '0' NOT NULL,
  itemcheck int(11) unsigned DEFAULT '0' NOT NULL,
  note_text text,
  note_status tinyint(4) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY itemcheck (itemcheck)
);

CREATE TABLE tx_hdgolive_pagenote (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
  sorting int(11) unsigned DEFAULT '0' NOT NULL,
  pagecheck int(11) unsigned DEFAULT '0' NOT NULL,
  note_text text,
  note_status tinyint(4) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY pagecheck (pagecheck)
);

CREATE TABLE pages (
  tx_hdgolive_exclude_from_list tinyint(4) unsigned DEFAULT '0' NOT NULL,
  tx_hdgolive_include_in_list tinyint(4) unsigned DEFAULT '0' NOT NULL
);
