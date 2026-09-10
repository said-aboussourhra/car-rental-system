<?php
/**
 * معالج جلسات قاعدة البيانات
 * يُستخدم على المنصات السحابية (Vercel) حيث نظام الملفات مؤقت
 * ولا تُشارك الملفات بين الخوادم.
 *
 * ملاحظة: يُستورد هذا الملف فقط عند توفر SessionHandlerInterface
 * (انظر includes/config.php).
 */

if (!interface_exists('SessionHandlerInterface')) {
    return;
}

class DbSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name) { return true; }

    #[\ReturnTypeWillChange]
    public function close() { return true; }

    #[\ReturnTypeWillChange]
    public function read($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = :id AND expires > UNIX_TIMESTAMP() LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            return $row ? (string)$row['data'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data) {
        try {
            $ttl = (int)ini_get('session.gc_maxlifetime') ?: 1440;
            $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, expires) VALUES (:id, :data, :exp)");
            return $stmt->execute([':id' => $id, ':data' => $data, ':exp' => time() + $ttl]);
        } catch (Exception $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id) {
        try {
            $this->pdo->prepare("DELETE FROM sessions WHERE id = :id")->execute([':id' => $id]);
        } catch (Exception $e) { /* ignore */ }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) {
        try {
            $this->pdo->prepare("DELETE FROM sessions WHERE expires < UNIX_TIMESTAMP()")->execute();
            return 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
