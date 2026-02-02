<?php
// app/models/Employee.php

class Employee {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getAllEmployees($filters, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $subBU = "(SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";
        $subFN1 = "(SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";
        $subFN2 = "(SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";

        $where = ["1=1"];
        
        if (!empty($filters['search'])) {
            $where[] = "(k.nama_karyawan LIKE ? OR k.index_karyawan LIKE ?)";
            $params[] = "%" . $filters['search'] . "%";
            $params[] = "%" . $filters['search'] . "%";
        }

        if (!empty($filters['bu']) && $filters['bu'] !== 'All BUs') {
            $where[] = "$subBU = ?";
            $params[] = $filters['bu'];
        }
        if (!empty($filters['fn1']) && $filters['fn1'] !== 'All Func N-1') {
            $where[] = "$subFN1 = ?";
            $params[] = $filters['fn1'];
        }
        if (!empty($filters['fn2']) && $filters['fn2'] !== 'All Func N-2') {
            $where[] = "$subFN2 = ?";
            $params[] = $filters['fn2'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM karyawan k WHERE $whereSql";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        $sql = "SELECT 
                    k.id_karyawan, k.index_karyawan, k.nama_karyawan,
                    (SELECT COUNT(*) FROM score s WHERE s.id_karyawan = k.id_karyawan) as total_participation,
                    $subBU as latest_bu,
                    $subFN1 as latest_func_n1,
                    $subFN2 as latest_func_n2
                FROM karyawan k
                WHERE $whereSql
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

    public function getFilterOptions($bu = 'All BUs', $fn1 = 'All Func N-1') {
        $fn1Opts = [];
        $fn2Opts = [];

        $sql1 = "SELECT DISTINCT f.func_n1 FROM func f 
                 JOIN score s ON f.id_func = s.id_func 
                 JOIN bu b ON s.id_bu = b.id_bu 
                 WHERE f.func_n1 IS NOT NULL AND f.func_n1 != ''";
        $p1 = [];
        if ($bu !== 'All BUs') {
            $sql1 .= " AND b.nama_bu = ?";
            $p1[] = $bu;
        }
        $sql1 .= " ORDER BY f.func_n1";
        $stmt1 = $this->db->prepare($sql1);
        $stmt1->execute($p1);
        $fn1Opts = $stmt1->fetchAll(PDO::FETCH_COLUMN);

        $sql2 = "SELECT DISTINCT f.func_n2 FROM func f 
                 JOIN score s ON f.id_func = s.id_func 
                 JOIN bu b ON s.id_bu = b.id_bu 
                 WHERE f.func_n2 IS NOT NULL AND f.func_n2 != ''";
        $p2 = [];
        if ($bu !== 'All BUs') {
            $sql2 .= " AND b.nama_bu = ?";
            $p2[] = $bu;
        }
        if ($fn1 !== 'All Func N-1') {
            $sql2 .= " AND f.func_n1 = ?";
            $p2[] = $fn1;
        }
        $sql2 .= " ORDER BY f.func_n2";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute($p2);
        $fn2Opts = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        return ['fn1' => $fn1Opts, 'fn2' => $fn2Opts];
    }

    public function getEmployeeById($id) {
        $sql = "SELECT 
                    k.nama_karyawan, k.index_karyawan,
                    (SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as bu,
                    (SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as func,
                    (SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as func2
                FROM karyawan k
                WHERE k.id_karyawan = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getEmployeeStats($id) {
        $sql = "SELECT 
                    COUNT(id_score) as total_sessions,
                    AVG(post) as avg_score,
                    SUM(CASE WHEN t.jenis LIKE '%Technical%' THEN 1 ELSE 0 END) as count_tech,
                    SUM(CASE WHEN t.jenis LIKE '%Soft%' THEN 1 ELSE 0 END) as count_soft,
                    SUM(ts.credit_hour) as total_hours  
                FROM score s
                JOIN training_session ts ON s.id_session = ts.id_session
                JOIN training t ON ts.id_training = t.id_training
                WHERE s.id_karyawan = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getTrainingHistory($id, $search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $params = [$id];
        $where = "WHERE s.id_karyawan = ?";

        if (!empty($search)) {
            $where .= " AND (t.nama_training LIKE ? OR t.jenis LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $countSql = "SELECT COUNT(*) 
                     FROM score s 
                     JOIN training_session ts ON s.id_session = ts.id_session
                     JOIN training t ON ts.id_training = t.id_training 
                     $where";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        $sql = "SELECT 
                    t.nama_training, t.jenis AS category, t.type AS training_type, t.instructor_name AS instructor_name,t.lembaga,
                    ts.date_start, ts.date_end, ts.method, ts.place, ts.credit_hour,
                    s.pre, s.post
                FROM score s
                JOIN training_session ts ON s.id_session = ts.id_session
                JOIN training t ON ts.id_training = t.id_training
                $where
                ORDER BY ts.date_start DESC
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
}
?>