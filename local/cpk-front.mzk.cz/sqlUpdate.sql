/* Set longer username in user table */
ALTER TABLE user CHANGE username username VARCHAR(64);

/* Create indexed eppn for purposes of connected federative identities */
ALTER TABLE user_card ADD COLUMN eppn VARCHAR(64);
CREATE UNIQUE INDEX user_card_eppn_uq ON user_card(eppn);

/* Create row for storing user's read notifications */
ALTER TABLE user ADD read_notifications VARCHAR(512) NULL DEFAULT NULL;

/* Create and fill table representing citation styles */
CREATE TABLE IF NOT EXISTS `citation_style` (
  `id` int(11) NOT NULL,
  `description` VARCHAR(32),
  `value` VARCHAR(8)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

ALTER TABLE `citation_style`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `citation_style`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

INSERT INTO `vufind`.`citation_style` (`id`, `description`, `value`) 
  VALUES (NULL, 'ČSN ISO 690', '38673'),
         (NULL, 'Harvard', '3'), 
         (NULL, 'NISO/ANSI Z39.29 (2005)', '4'),
         (NULL, 'MLA (7th edition)', '5'), 
         (NULL, 'Turabian (7th edition)', '6'), 
         (NULL, 'Chicago (16th edition)', '7'), 
         (NULL, 'IEEE', '8'), 
         (NULL, 'CSE', '9'), 
         (NULL, 'CSE NY', '10'), 
         (NULL, 'APA', '11'), 
         (NULL, 'ISO 690', '12');


/* Create table for users preferences */
CREATE TABLE IF NOT EXISTS `user_preference` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `citation_style` int(8)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

ALTER TABLE `user_preference`
  ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`);

ALTER TABLE `user_preference`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

ALTER TABLE `user_preference`
  ADD FOREIGN KEY (user_id) REFERENCES user(id);
  
ALTER TABLE `user_preference`
  ADD FOREIGN KEY (citation_style) REFERENCES citation_style(id);
