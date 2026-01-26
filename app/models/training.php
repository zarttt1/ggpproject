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
}
?>