CREATE TABLE albums (
"id" serial,
"title" character varying (100) NOT NULL DEFAULT '',
"description" text,
"sysstamp" integer NOT NULL DEFAULT 0,
"public" integer NOT NULL DEFAULT 0,
"visible" integer NOT NULL DEFAULT 1,
"downloadable" integer NOT NULL DEFAULT 0,
"password" character varying (100)
);

CREATE TABLE photos (
"id" serial,
"title" character varying (100),
"description" text,
"url" character varying (100),
"tags" text,
"public" integer,
"type" character varying (10),
"width" integer,
"height" integer,
"size" character varying (20),
"iso" character varying (15),
"aperture" character varying (20),
"make" character varying (50),
"model" character varying (50),
"shutter" character varying (30),
"focal" character varying (20),
"takestamp" integer,
"star" integer,
"thumbUrl" character varying (50),
"album" character varying (30),
"checksum" character varying (100),
"medium" integer
);

CREATE TABLE settings (
  "key" character varying (50),
  "value" character varying (200)
);

INSERT INTO settings ("key", "value") VALUES ('version','');
INSERT INTO settings ("key", "value") VALUES ('username','');
INSERT INTO settings ("key", "value") VALUES ('password','');
INSERT INTO settings ("key", "value") VALUES ('thumbQuality','90');
INSERT INTO settings ("key", "value") VALUES ('checkForUpdates','1');
INSERT INTO settings ("key", "value") VALUES ('sortingPhotos','ORDER BY id DESC');
INSERT INTO settings ("key", "value") VALUES ('sortingAlbums','ORDER BY id DESC');
INSERT INTO settings ("key", "value") VALUES ('medium','1');
INSERT INTO settings ("key", "value") VALUES ('imagick','1');
INSERT INTO settings ("key", "value") VALUES ('identifier','');
INSERT INTO settings ("key", "value") VALUES ('skipDuplicates','0');
INSERT INTO settings ("key", "value") VALUES ('plugins','');

