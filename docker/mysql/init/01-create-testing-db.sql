-- Banco separado para os testes (PHPUnit roda contra MySQL de verdade, não
-- SQLite, porque o teste de conflito de agenda sob concorrência depende de
-- locking real do InnoDB — SELECT ... FOR UPDATE).
CREATE DATABASE IF NOT EXISTS dragenda_testing;
GRANT ALL PRIVILEGES ON dragenda_testing.* TO 'dragenda'@'%';
FLUSH PRIVILEGES;
