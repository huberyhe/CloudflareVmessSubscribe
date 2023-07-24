<?php
namespace MyNamespace\TransferHistory;

use SQLite3;

class TransferHistory
{
    private $db;
    public function __construct(string $db_file)
    {
        $this->db = new SQLite3($db_file);
        $this->init_table();
    }

    private function init_table()
    {
        $create_tb_sql = <<<'EOT'
CREATE TABLE IF NOT EXISTS tb_transfer_his (
    id INTEGER PRIMARY KEY, 
    url varchar(256), 
    short_url varchar(256),
    delete_url varchar(256),
    is_delete boolean default false, 
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TRIGGER IF NOT EXISTS [tg_last_update]
AFTER UPDATE
ON tb_transfer_his
FOR EACH ROW
BEGIN
UPDATE tb_transfer_his SET last_update = CURRENT_TIMESTAMP WHERE id = old.id;
END;
EOT;
        $this->db->query($create_tb_sql);
    }

    public function add_record(string $url, string $short_url, string $del_url): bool
    {
        $sql = <<<'EOT'
INSERT INTO tb_transfer_his (url, short_url, delete_url, is_delete) VALUES (:p_url, :p_short_url, :p_delete_url, false)
EOT;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':p_url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':p_short_url', $short_url, SQLITE3_TEXT);
        $stmt->bindValue(':p_delete_url', $del_url, SQLITE3_TEXT);

        $result = $stmt->execute();
        if ($result) {
            return true;
        }
        return false;
    }

    public function get_all(): array
    {
        $records = array();
        $sql = 'SELECT * from tb_transfer_his WHERE is_delete = false';
        $result = $this->db->query($sql);
        if ($result) {
            while($record = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($record) {
                    $records[] = $record;
                }
            }
        }
        $result->finalize();

        return $records;
    }

    public function del_record(int $id): bool
    {
        $sql = 'UPDATE tb_transfer_his SET is_delete = true,last_update = CURRENT_TIMESTAMP WHERE id = :p_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':p_id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result) {
            return true;
        }
        return false;
    }

    public function __destruct()
    {
        $this->db->close();
    }
}