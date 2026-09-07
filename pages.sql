-- Trongate Pages module - database schema
-- Install: import this file into your application database (or rely on the
-- v2 module import wizard, which runs pages.sql automatically in dev mode).

CREATE TABLE IF NOT EXISTS pages (
    id int(11) NOT NULL AUTO_INCREMENT,
    url_string varchar(255) NOT NULL,
    page_title varchar(255) NOT NULL,
    meta_keywords text,
    meta_description text,
    page_body text,
    date_created int(11) NOT NULL,
    last_updated int(11) NOT NULL DEFAULT 0,
    published tinyint(1) NOT NULL DEFAULT 0,
    created_by int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY url_string (url_string)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
