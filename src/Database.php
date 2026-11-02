<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'barberia_db';
            $user = getenv('DB_USER') ?: 'barber_user';
            $password = getenv('DB_PASS') ?: 'barber_secret_pass';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};";

            $maxRetries = 10;
            $retryCount = 0;
            $lastException = null;

            while ($retryCount < $maxRetries) {
                try {
                    self::$instance = new PDO($dsn, $user, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                    $retryCount++;
                    sleep(1);
                }
            }

            if (self::$instance === null) {
                throw new Exception("Error al conectar a PostgreSQL: " . ($lastException ? $lastException->getMessage() : 'Desconocido'));
            }

            // Ensure tables, constraints, triggers, and migrations are applied
            self::ensureSchema(self::$instance);
        }

        return self::$instance;
    }

    private static function ensureSchema(PDO $pdo): void {
        $sqlFile = __DIR__ . '/../database/init.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            try {
                $pdo->exec($sql);
            } catch (Throwable $t) {
                // Ignore error if schema already partially exists; continue to migrations
            }
        }

        // Migration safety: Ensure new columns, constraints, indexes exist in already running databases
        try {
            // Clientes columns
            $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cedula VARCHAR(20);");
            $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS email VARCHAR(120);");
            
            // Clean any 'V-' or non-numeric prefixes from existing records so all are pure digits
            $pdo->exec("UPDATE clientes SET cedula = REGEXP_REPLACE(cedula, '[^0-9]', '', 'g') WHERE cedula ~ '[^0-9]';");
            $pdo->exec("UPDATE clientes SET cedula = CAST(10000000 + id_cliente AS VARCHAR) WHERE cedula IS NULL OR cedula = '' OR length(cedula) < 4;");
            
            // Check unique index on cedula
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_cliente_cedula_idx ON clientes (cedula);");

            // Apply numeric-only CHECK constraint on PostgreSQL
            $pdo->exec("DO $$ 
            BEGIN
                ALTER TABLE clientes DROP CONSTRAINT IF EXISTS chk_cliente_cedula;
                ALTER TABLE clientes ADD CONSTRAINT chk_cliente_cedula CHECK (cedula ~ '^[0-9]{4,20}$');
            EXCEPTION WHEN OTHERS THEN
                NULL;
            END $$;");
            
            // Recreate anti-clash unique indexes for appointments
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_cita_barbero_fecha_hora ON citas (id_barbero, fecha, hora) WHERE estado != 'Cancelada';");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_cita_cliente_fecha_hora ON citas (id_cliente, fecha, hora) WHERE estado != 'Cancelada';");
        } catch (Throwable $e) {
            // Non-blocking schema migration catch
        }
    }
}
