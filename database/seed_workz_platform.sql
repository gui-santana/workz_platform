-- Seed de dados de teste para Workz Platform (MySQL 8)
-- Idempotente via ON DUPLICATE KEY UPDATE (no-op)

-- =====================
-- workz_data
-- =====================
USE `workz_data`;

-- Usuários (hus)
INSERT INTO `hus` (`id`,`tt`,`ml`,`pw`,`dt`,`provider`,`st`,`im`,`bk`,`un`,`cf`,`page_privacy`,`feed_privacy`,`gender`,`birth`,`contacts`)
VALUES
  (1,'Alice Silva','alice@example.com','$2y$10$vBafMr4RLD64R0LmmfDggu.93lbTQOPy7CPSvZzH3wneOFLlHQoSS',NOW(),'local',1,'/images/users/people_1.png','/images/users/cover_1.jpg','alice','Designer e fotógrafa',0,0,'female','1992-05-14','[{"type":"site","value":"https://alice.dev"}]'),
  (2,'Bob Santos','bob@example.com','$2y$10$vBafMr4RLD64R0LmmfDggu.93lbTQOPy7CPSvZzH3wneOFLlHQoSS',NOW(),'local',1,'/images/users/people_2.png','/images/users/cover_2.jpg','bob','Dev full-stack',0,0,'male','1990-10-20','[{"type":"linkedin","value":"https://linkedin.com/in/bob"}]'),
  (3,'Carol Souza','carol@example.com','$2y$10$vBafMr4RLD64R0LmmfDggu.93lbTQOPy7CPSvZzH3wneOFLlHQoSS',NOW(),'local',1,'/images/users/people_3.png','/images/users/cover_3.jpg','carol','Produtora de vídeo',0,0,'female','1995-03-02','[{"type":"instagram","value":"https://instagram.com/carol"}]')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Posts (hpl)
INSERT INTO `hpl` (`id`,`us`,`tp`,`dt`,`cm`,`em`,`st`,`ct`)
VALUES
  (401,1,'image',NOW(),201,0,1,'{"media":[{"type":"image","url":"/uploads/posts/sample1.jpg","mimeType":"image/jpeg","w":800,"h":600}]}'),
  (402,2,'video',NOW(),0,102,1,'{"media":[{"type":"video","url":"/uploads/posts/sample2.webm","mimeType":"video/webm"}],"caption":"Vídeo de demo"}'),
  (403,3,'image',NOW(),0,0,1,'{"media":[{"type":"image","url":"/uploads/posts/sample3.jpg","mimeType":"image/jpeg","w":1024,"h":768}],"caption":"Café no estúdio"}')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Comentários (hpl_comments)
INSERT INTO `hpl_comments` (`id`,`pl`,`us`,`ds`,`dt`)
VALUES
  (501,401,2,'Mandou bem, Alice! 👏',NOW()),
  (502,402,1,'Curti o conteúdo! 👍',NOW())
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Curtidas (lke)
INSERT INTO `lke` (`id`,`pl`,`us`,`dt`)
VALUES
  (601,401,3,NOW()),
  (602,403,1,NOW())
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seguidores (usg)
INSERT INTO `usg` (`id`,`s0`,`s1`,`dt`)
VALUES
  (701,1,2,NOW()),
  (702,2,1,NOW()),
  (703,3,1,NOW())
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Depoimentos (testimonials)
INSERT INTO `testimonials` (`id`,`author`,`recipient`,`recipient_type`,`content`,`status`,`dt`)
VALUES
  (801,2,1,'people','Profissional excelente e colaborativo.',0,NOW()),
  (802,3,101,'businesses','Ótima experiência com a equipe da ACME!',1,NOW()),
  (803,1,201,'teams','Time muito organizado e ágil.',1,NOW())
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Histórico profissional (work_history)
INSERT INTO `work_history` (`id`,`us`,`em`,`tt`,`cf`,`type`,`location`,`start_date`,`end_date`,`visibility`,`verified`,`verified_by`,`verified_at`,`st`)
VALUES
  (901,1,101,'Product Manager','Gestão de produto e UX','clt','São Paulo','2022-02-01',NULL,1,0,NULL,NULL,1),
  (902,3,NULL,'Freelancer Video','Produção e edição de vídeo','freelancer','Remoto','2023-05-01',NULL,1,0,NULL,NULL,1)
ON DUPLICATE KEY UPDATE `id`=`id`;


-- =====================
-- workz_companies
-- =====================
USE `workz_companies`;

-- Empresas (companies)
INSERT INTO `companies` (`id`,`tt`,`im`,`bk`,`st`,`un`,`cf`,`page_privacy`,`feed_privacy`,`national_id`,`zip_code`,`country`,`state`,`city`,`district`,`address`,`complement`,`contacts`)
VALUES
  (101,'ACME Ltda','/images/businesses/acme.png','/images/businesses/acme_cover.jpg',1,'acme','Soluções de tecnologia',0,0,'12345678000100','01001000','Brasil','SP','São Paulo','Centro','Rua do Progresso, 100','Conj 12','[{"type":"site","value":"https://acme.com"}]'),
  (102,'BetaWorks','/images/businesses/betaworks.png','/images/businesses/betaworks_cover.jpg',1,'betaworks','Consultoria em dados',0,0,'00987654000122','20040002','Brasil','RJ','Rio de Janeiro','Centro','Av. Central, 200','Sala 804','[{"type":"linkedin","value":"https://linkedin.com/company/betaworks"}]')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Vínculos Usuário–Empresa (employees)
INSERT INTO `employees` (`id`,`us`,`em`,`nv`,`st`,`start_date`,`end_date`)
VALUES
  (111,1,101,4,1,'2022-02-01',NULL),
  (112,2,101,2,1,'2023-01-10',NULL),
  (113,2,102,4,1,'2021-03-05',NULL),
  (114,3,102,1,1,'2023-06-01',NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Equipes (teams)
INSERT INTO `teams` (`id`,`tt`,`im`,`bk`,`st`,`un`,`us`,`usmn`,`em`,`cf`,`feed_privacy`,`contacts`)
VALUES
  (201,'Team Rocket','/images/teams/rocket.png','/images/teams/rocket_cover.jpg',1,'team-rocket',1,'["3"]',101,'Equipe de produto',0,'[{"type":"slack","value":"https://rocket.slack.com"}]'),
  (202,'Team Beta','/images/teams/beta.png','/images/teams/beta_cover.jpg',1,'team-beta',2,NULL,102,'Equipe de dados',0,NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Vínculos Usuário–Equipe (teams_users)
INSERT INTO `teams_users` (`id`,`us`,`cm`,`nv`,`st`)
VALUES
  (211,1,201,4,1),
  (212,3,201,2,1),
  (213,2,202,4,1)
ON DUPLICATE KEY UPDATE `id`=`id`;


-- =====================
-- workz_apps
-- =====================
USE `workz_apps`;

-- Apps (apps)
INSERT INTO `apps` (`id`,`tt`,`im`,`vl`,`st`,`dt`)
VALUES
  (301,'Workz CRM','/images/apps/crm.png',0.00,1,NOW()),
  (302,'Workz Analytics','/images/apps/analytics.png',9.90,1,NOW())
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Instalações/Assinaturas (gapp)
INSERT INTO `gapp` (`id`,`us`,`em`,`ap`,`subscription`,`start_date`,`end_date`)
VALUES
  (321,1,NULL,301,0,NULL,NULL),
  (322,2,NULL,302,1,CURDATE(),NULL),
  (323,NULL,101,302,1,CURDATE(),NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

