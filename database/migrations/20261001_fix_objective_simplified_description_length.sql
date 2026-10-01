-- Objective simplified descriptions can contain long educational text.
ALTER TABLE objectius_aprenentatge
    MODIFY COLUMN descripcio_simplificada TEXT NOT NULL;
