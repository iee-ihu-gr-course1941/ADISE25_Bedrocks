

DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  `pos` tinyint(4) NOT NULL,          
  `stack_order` tinyint(4) NOT NULL,  
  `piece_color` enum('W','B') NOT NULL,
  PRIMARY KEY (`pos`,`stack_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



LOCK TABLES `board` WRITE;
INSERT INTO `board` VALUES 

(1,0,'W'),(1,1,'W'),(1,2,'W'),(1,3,'W'),(1,4,'W'),(1,5,'W'),(1,6,'W'),(1,7,'W'),(1,8,'W'),(1,9,'W'),(1,10,'W'),(1,11,'W'),(1,12,'W'),(1,13,'W'),(1,14,'W'),

(24,0,'B'),(24,1,'B'),(24,2,'B'),(24,3,'B'),(24,4,'B'),(24,5,'B'),(24,6,'B'),(24,7,'B'),(24,8,'B'),(24,9,'B'),(24,10,'B'),(24,11,'B'),(24,12,'B'),(24,13,'B'),(24,14,'B');
UNLOCK TABLES;



DROP TABLE IF EXISTS `board_empty`;
CREATE TABLE `board_empty` (
  `pos` tinyint(4) NOT NULL,
  `stack_order` tinyint(4) NOT NULL,
  `piece_color` enum('W','B') NOT NULL,
  PRIMARY KEY (`pos`,`stack_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `board_empty` WRITE;
INSERT INTO `board_empty` SELECT * FROM `board`;
UNLOCK TABLES;



DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','initialized','started','ended','aborded') NOT NULL DEFAULT 'not active',
  `p_turn` enum('W','B') DEFAULT NULL,
  `result` enum('B','W') DEFAULT NULL,
  `last_change` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `game_status` (`status`, `p_turn`) VALUES ('started', 'W');



DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) DEFAULT NULL,
  `piece_color` enum('B','W') NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_action` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`piece_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DELIMITER ;;


CREATE PROCEDURE `clean_board`()
BEGIN
  DELETE FROM board;
  INSERT INTO board SELECT * FROM board_empty;
  UPDATE `players` SET username=NULL, token=NULL;
  UPDATE `game_status` SET `status`='not active', `p_turn`='W', `result`=NULL;
END ;;


CREATE PROCEDURE `move_piece`(p_from tinyint, p_to tinyint)
BEGIN
  DECLARE top_piece_order tinyint;
  DECLARE top_piece_color enum('W','B');
  DECLARE target_stack_order tinyint;

  
  SELECT stack_order, piece_color INTO top_piece_order, top_piece_color 
  FROM board 
  WHERE pos = p_from 
  ORDER BY stack_order DESC LIMIT 1;

  
  SELECT IFNULL(MAX(stack_order) + 1, 0) INTO target_stack_order 
  FROM board 
  WHERE pos = p_to;

  
  IF top_piece_color IS NOT NULL THEN
    INSERT INTO board (pos, stack_order, piece_color) 
    VALUES (p_to, target_stack_order, top_piece_color);

    DELETE FROM board 
    WHERE pos = p_from AND stack_order = top_piece_order;

    
    UPDATE game_status SET p_turn = IF(top_piece_color='W','B','W');
  END IF;
END ;;

DELIMITER ;