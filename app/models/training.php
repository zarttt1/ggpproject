<?php
// app/models/Training.php

class Training {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    private function buildFilterQuery($filters) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['bu']) && $filters['bu'] !== 'All') {
            $where[] = "b.nama_bu = ?";
            $params[] = $filters['bu'];
        }
        if (!empty($filters['func_n1']) && $filters['func_n1'] !== 'All') {
            $where[] = "f.func_n1 = ?";
            $params[] = $filters['func_n1'];
        }
        if (!empty($filters['func_n2']) && $filters['func_n2'] !== 'All') {
            $where[] = "f.func_n2 = ?";
            $params[] = $filters['func_n2'];
        }
        if (!empty($filters['type']) && $filters['type'] !== 'All') {
            $where[] = "t.jenis = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $where[] = "t.nama_training LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        if (!empty($filters['start']) && !empty($filters['end'])) {
            $where[] = "ts.date_start >= ? AND ts.date_start <= ?";
            $params[] = $filters['start'];
            $params[] = $filters['end'];
        } elseif (!empty($filters['start'])) {
            $where[] = "ts.date_start >= ?";
            $params[] = $filters['start'];
        } elseif (!empty($filters['end'])) {
            $where[] = "ts.date_start <= ?";
            $params[] = $filters['end'];
        }

        return ['sql' => implode(' AND ', $where), 'params' => $params];
    }

    public function getStats($filters) {
        $queryData = $this->buildFilterQuery($filters);
        $where = $queryData['sql'];
        $params = $queryData['params'];

        if (!empty($filters['training_name']) && $filters['training_name'] !== 'All') {
            $where .= " AND t.nama_training = ?";
            $params[] = $filters['training_name'];
        }

        $join = "FROM score s
                 JOIN training_session ts ON s.id_session = ts.id_session
                 JOIN training t ON ts.id_training = t.id_training
                 LEFT JOIN bu b ON s.id_bu = b.id_bu
                 LEFT JOIN func f ON s.id_func = f.id_func
                 WHERE $where";

        $stmt = $this->db->prepare("SELECT SUM(ts.credit_hour) FROM score s JOIN training_session ts ON s.id_session = ts.id_session JOIN training t ON ts.id_training = t.id_training LEFT JOIN bu b ON s.id_bu = b.id_bu LEFT JOIN func f ON s.id_func = f.id_func WHERE $where");
        $stmt->execute($params);
        $total = $stmt->fetchColumn() ?? 0;

        $stmt = $this->db->prepare("SELECT SUM(ts.credit_hour) FROM score s JOIN training_session ts ON s.id_session = ts.id_session JOIN training t ON ts.id_training = t.id_training LEFT JOIN bu b ON s.id_bu = b.id_bu LEFT JOIN func f ON s.id_func = f.id_func WHERE $where AND (ts.method LIKE '%Inclass%')");
        $stmt->execute($params);
        $offline = $stmt->fetchColumn() ?? 0;

        $stmt = $this->db->prepare("SELECT SUM(ts.credit_hour) FROM score s JOIN training_session ts ON s.id_session = ts.id_session JOIN training t ON ts.id_training = t.id_training LEFT JOIN bu b ON s.id_bu = b.id_bu LEFT JOIN func f ON s.id_func = f.id_func WHERE $where AND (ts.method LIKE '%Hybrid%' OR ts.method LIKE '%Webinar%' OR ts.method LIKE '%Self-paced%')");
        $stmt->execute($params);
        $online = $stmt->fetchColumn() ?? 0;

        $stmt = $this->db->prepare("SELECT COUNT(s.id_score) FROM score s JOIN training_session ts ON s.id_session = ts.id_session JOIN training t ON ts.id_training = t.id_training LEFT JOIN bu b ON s.id_bu = b.id_bu LEFT JOIN func f ON s.id_func = f.id_func WHERE $where");
        $stmt->execute($params);
        $participants = $stmt->fetchColumn() ?? 0;

        return [
            'total_hours' => $total,
            'offline_hours' => $offline,
            'online_hours' => $online,
            'participants' => $participants
        ];
    }

    public function getTrainingList($filters, $limit = 50) {
        $queryData = $this->buildFilterQuery($filters);
        
        $sql = "SELECT t.nama_training, ts.code_sub, ts.date_start, ts.date_end, ts.method
                FROM score s
                JOIN training_session ts ON s.id_session = ts.id_session
                JOIN training t ON ts.id_training = t.id_training
                LEFT JOIN bu b ON s.id_bu = b.id_bu
                LEFT JOIN func f ON s.id_func = f.id_func
                WHERE {$queryData['sql']}
                GROUP BY ts.id_session
                ORDER BY ts.date_start DESC
                LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryData['params']);
        return $stmt->fetchAll();
    }

    public function getBus() {
        return $this->db->query("SELECT DISTINCT nama_bu FROM bu WHERE nama_bu IS NOT NULL ORDER BY nama_bu")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getFuncN1($bu = 'All') {
        $sql = "SELECT DISTINCT f.func_n1 FROM func f";
        $params = [];
        if ($bu !== 'All') {
            $sql .= " JOIN score s ON f.id_func = s.id_func JOIN bu b ON s.id_bu = b.id_bu WHERE b.nama_bu = ? AND f.func_n1 IS NOT NULL";
            $params[] = $bu;
        } else {
            $sql .= " WHERE f.func_n1 IS NOT NULL";
        }
        $sql .= " ORDER BY f.func_n1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getFuncN2($bu = 'All', $n1 = 'All') {
        $sql = "SELECT DISTINCT f.func_n2 FROM func f";
        $params = [];
        
        if ($bu !== 'All') {
            $sql .= " JOIN score s ON f.id_func = s.id_func JOIN bu b ON s.id_bu = b.id_bu WHERE b.nama_bu = ?";
            $params[] = $bu;
            if ($n1 !== 'All') {
                $sql .= " AND f.func_n1 = ?";
                $params[] = $n1;
            }
            $sql .= " AND f.func_n2 IS NOT NULL";
        } elseif ($n1 !== 'All') {
            $sql .= " WHERE f.func_n1 = ? AND f.func_n2 IS NOT NULL";
            $params[] = $n1;
        } else {
            $sql .= " WHERE f.func_n2 IS NOT NULL";
        }
        
        $sql .= " ORDER BY f.func_n2";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTypes() {
        return $this->db->query("SELECT DISTINCT jenis FROM training WHERE jenis IS NOT NULL ORDER BY jenis")->fetchAll(PDO::FETCH_COLUMN);
    }

    // --- FOR REPORTS PAGE ---
    public function getCategories() {
        return $this->db->query("SELECT DISTINCT jenis FROM training WHERE jenis IS NOT NULL AND jenis != '' ORDER BY jenis")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getMethods() {
        return $this->db->query("SELECT DISTINCT method FROM training_session WHERE method IS NOT NULL AND method != '' ORDER BY method")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getCodes() {
        return $this->db->query("SELECT DISTINCT code_sub FROM training_session WHERE code_sub IS NOT NULL AND code_sub != '' ORDER BY code_sub")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getAllSessions($filters, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(t.nama_training LIKE ? OR ts.code_sub LIKE ?)";
            $params[] = "%" . $filters['search'] . "%";
            $params[] = "%" . $filters['search'] . "%";
        }
        if (!empty($filters['category']) && $filters['category'] !== 'All Categories') {
            $where[] = "t.jenis = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['type']) && $filters['type'] !== 'All Types') {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['method']) && $filters['method'] !== 'All Methods') {
            $where[] = "ts.method = ?";
            $params[] = $filters['method'];
        }
        if (!empty($filters['code']) && $filters['code'] !== 'All Codes') {
            $where[] = "ts.code_sub = ?";
            $params[] = $filters['code'];
        }
        
        if (!empty($filters['start']) && !empty($filters['end'])) {
            $where[] = "ts.date_start >= ? AND ts.date_start <= ?";
            $params[] = $filters['start'];
            $params[] = $filters['end'];
        } elseif (!empty($filters['start'])) {
            $where[] = "ts.date_start >= ?";
            $params[] = $filters['start'];
        } elseif (!empty($filters['end'])) {
            $where[] = "ts.date_start <= ?";
            $params[] = $filters['end'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(DISTINCT ts.id_session) 
                     FROM training_session ts 
                     JOIN training t ON ts.id_training = t.id_training 
                     WHERE $whereSql";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        $dataSql = "
            SELECT ts.id_session, t.nama_training, ts.code_sub, t.jenis AS category, t.type AS training_type, 
                   ts.method, ts.credit_hour, ts.date_start, ts.date_end, 
                   COUNT(s.id_score) as participants, AVG(s.pre) as avg_pre, AVG(s.post) as avg_post
            FROM training_session ts
            JOIN training t ON ts.id_training = t.id_training
            LEFT JOIN score s ON ts.id_session = s.id_session
            WHERE $whereSql
            GROUP BY ts.id_session
            ORDER BY ts.date_start DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmtData = $this->db->prepare($dataSql);
        $stmtData->execute($params);
        $results = $stmtData->fetchAll();

        return [
            'data' => $results,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'current_page' => $page
        ];
    }

    public function getTrainingTypes() {
        return $this->db->query("SELECT DISTINCT type FROM training WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
    }

    // --- FOR DETAILS PAGE ---

    public function getSessionById($id) {
        $sql = "SELECT t.nama_training, ts.code_sub, ts.date_start, ts.date_end, ts.credit_hour 
                FROM training_session ts 
                JOIN training t ON ts.id_training = t.id_training 
                WHERE ts.id_session = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getSessionStats($id) {
        // Calculates averages and histogram buckets
        $sql = "SELECT 
            COUNT(id_score) as total,
            AVG(pre) as avg_pre,
            AVG(post) as avg_post,
            AVG(statis_subject) as avg_subject,
            AVG(instructor) as avg_instructor,
            AVG(statis_infras) as avg_infras,
            SUM(CASE WHEN pre BETWEEN 0 AND 20 THEN 1 ELSE 0 END) as pre_0_20,
            SUM(CASE WHEN pre BETWEEN 21 AND 40 THEN 1 ELSE 0 END) as pre_21_40,
            SUM(CASE WHEN pre BETWEEN 41 AND 60 THEN 1 ELSE 0 END) as pre_41_60,
            SUM(CASE WHEN pre BETWEEN 61 AND 80 THEN 1 ELSE 0 END) as pre_61_80,
            SUM(CASE WHEN pre BETWEEN 81 AND 100 THEN 1 ELSE 0 END) as pre_81_100,
            SUM(CASE WHEN post BETWEEN 0 AND 20 THEN 1 ELSE 0 END) as post_0_20,
            SUM(CASE WHEN post BETWEEN 21 AND 40 THEN 1 ELSE 0 END) as post_21_40,
            SUM(CASE WHEN post BETWEEN 41 AND 60 THEN 1 ELSE 0 END) as post_41_60,
            SUM(CASE WHEN post BETWEEN 61 AND 80 THEN 1 ELSE 0 END) as post_61_80,
            SUM(CASE WHEN post BETWEEN 81 AND 100 THEN 1 ELSE 0 END) as post_81_100
        FROM score WHERE id_session = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getTopImprovers($id) {
        $sql = "SELECT k.nama_karyawan, (s.post - s.pre) as improvement, s.post, s.pre
                FROM score s 
                JOIN karyawan k ON s.id_karyawan = k.id_karyawan
                WHERE s.id_session = ?
                ORDER BY improvement DESC, s.pre ASC
                LIMIT 3";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function getParticipants($id, $search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $params = [$id];
        $where = "WHERE s.id_session = ?";
        
        if (!empty($search)) {
            $where .= " AND (k.nama_karyawan LIKE ? OR k.index_karyawan LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count
        $countSql = "SELECT COUNT(*) FROM score s JOIN karyawan k ON s.id_karyawan = k.id_karyawan $where";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        // Fetch Data
        $sql = "SELECT k.index_karyawan, k.nama_karyawan, b.nama_bu, f.func_n1, s.pre, s.post
                FROM score s
                JOIN karyawan k ON s.id_karyawan = k.id_karyawan
                LEFT JOIN bu b ON s.id_bu = b.id_bu
                LEFT JOIN func f ON s.id_func = f.id_func
                $where
                ORDER BY k.nama_karyawan ASC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        return [
            'data' => $results,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'current_page' => $page
        ];
    }

    public function updateSession($id, $data) {
        try {
            $this->db->beginTransaction();

            // Update Training Name
            $stmt1 = $this->db->prepare("UPDATE training t JOIN training_session ts ON t.id_training = ts.id_training SET t.nama_training = ? WHERE ts.id_session = ?");
            $stmt1->execute([$data['title'], $id]);
            
            // Update Session Details
            $stmt2 = $this->db->prepare("UPDATE training_session SET code_sub = ?, credit_hour = ?, date_start = ?, date_end = ? WHERE id_session = ?");
            $stmt2->execute([$data['code'], $data['credit_hour'], $data['date_start'], $data['date_end'], $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getParticipantsForExport($id) {
        $sql = "SELECT k.index_karyawan, k.nama_karyawan, f.func_n2, s.pre, s.post
                FROM score s
                JOIN karyawan k ON s.id_karyawan = k.id_karyawan
                LEFT JOIN func f ON s.id_func = f.id_func
                WHERE s.id_session = ?
                ORDER BY k.nama_karyawan ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
?>