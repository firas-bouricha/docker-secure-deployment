USE td2;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(180) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO events (message)
VALUES
    ('Application initialisée'),
    ('Premier déploiement Docker'),
    ('Infrastructure opérationnelle');
