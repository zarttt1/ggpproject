<?php
// app/models/Importer.php

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenSpout\Reader\Xlsx\Reader as XlsxReader;
use OpenSpout\Reader\CSV\Reader as CsvReader;

class Importer {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    // --- Helpers ---

    private function t($v) {
        if ($v === null) return '';
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        return trim((string)$v);
    }

    private function parseDate($v) {
        if (empty($v)) return null;
        if ($v instanceof \DateTime || $v instanceof \DateTimeImmutable) return $v->format('Y-m-d');
        if (is_string($v)) {
            if (is_numeric($v)) {
                $unix = ($v - 25569) * 86400;
                return gmdate('Y-m-d', $unix);
            }
            $t = strtotime(str_replace('/', '-', $v));
            return $t ? date('Y-m-d', $t) : null;
        }
        return null;
    }

    private function f($v) {
        if ($v === '' || $v === null) return 'NULL';
        if (is_numeric($v)) return (float)$v;
        return (float) str_replace(',', '.', (string)$v);
    }

    public function processFile($filePath, $ext, $username = 'System') {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $scriptStart = microtime(true);

        try {
            // 1. Prepare Temp Table
            $this->db->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_upload_keys (
                excel_row INT, id_session INT, id_karyawan INT, 
                nama VARCHAR(255), training VARCHAR(255), date_start DATE, 
                KEY idx_chk (id_session, id_karyawan)
            ) ENGINE=InnoDB");
            $this->db->exec("DELETE FROM tmp_upload_keys");

            // 2. Start Transaction
            $this->db->beginTransaction();
            $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->db->exec("SET UNIQUE_CHECKS=0");

            $initialCount = (int)$this->db->query("SELECT COUNT(*) FROM score")->fetchColumn();

            // 3. Cache Data (NORMALIZED TO LOWERCASE KEYS)
            $bu = [];
            foreach($this->db->query("SELECT nama_bu, id_bu FROM bu") as $r) {
                $bu[strtolower(trim($r['nama_bu']))] = $r['id_bu'];
            }

            $kar = [];
            foreach($this->db->query("SELECT index_karyawan, id_karyawan FROM karyawan") as $r) {
                // IDs usually don't need lowercase, but safe to trim
                $kar[trim($r['index_karyawan'])] = $r['id_karyawan'];
            }

            $train = [];
            foreach($this->db->query("SELECT nama_training, id_training FROM training") as $r) {
                $train[strtolower(trim($r['nama_training']))] = $r['id_training'];
            }

            $func = [];
            foreach ($this->db->query("SELECT id_func, func_n1, func_n2 FROM func") as $r) {
                $k = strtolower(trim($r['func_n1'])) . '|' . strtolower(trim($r['func_n2'] ?? ''));
                $func[$k] = $r['id_func'];
            }

            // Session keys rely on IDs and Dates, so lowercasing isn't strictly necessary here, 
            // but code_sub should be trimmed.
            $sess = [];
            foreach ($this->db->query("SELECT id_session, id_training, code_sub, date_start FROM training_session") as $r) {
                $k = $r['id_training'] . '|' . trim($r['code_sub'] ?? '') . '|' . $r['date_start'];
                $sess[$k] = $r['id_session'];
            }

            // 4. Prepared Statements
            $insBU    = $this->db->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
            $insFunc  = $this->db->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?,?)");
            $insKar   = $this->db->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?,?)");
            $insTrain = $this->db->prepare("INSERT INTO training (nama_training, jenis, type, instructor_name, lembaga) VALUES (?,?,?,?,?)");
            $insSess  = $this->db->prepare("INSERT INTO training_session (id_training, code_sub, class, date_start, date_end, credit_hour, place, method) VALUES (?,?,?,?,?,?,?,?)");

            // 5. Open File
            if ($ext === 'csv') $reader = new CsvReader();
            else $reader = new XlsxReader();
            $reader->open($filePath);

            // 6. Process Rows
            $BATCH_LIMIT = 500;
            $sqlScores = [];
            $sqlKeys = [];
            $rowsProcessed = 0;
            $rowsSkipped = 0;
            $excelRow = 0;

            $baseScoreSQL = "INSERT INTO score (id_session, id_karyawan, id_bu, id_func, pre, post, statis_subject, instructor, statis_infras) VALUES ";
            $baseKeysSQL  = "INSERT INTO tmp_upload_keys (excel_row, id_session, id_karyawan, nama, training, date_start) VALUES ";
            $onDupSQL = " ON DUPLICATE KEY UPDATE id_bu=VALUES(id_bu), id_func=VALUES(id_func), pre=VALUES(pre), post=VALUES(post), statis_subject=VALUES(statis_subject), instructor=VALUES(instructor), statis_infras=VALUES(statis_infras)";

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $excelRow++;
                    if ($excelRow === 1) continue; 

                    $r = $row->toArray();
                    $rowsProcessed++;

                    // MAPPING
                    $idx  = $this->t($r[0] ?? '');
                    $name = $this->t($r[1] ?? '');
                    $subj = $this->t($r[2] ?? '');
                    $ds   = $this->parseDate($r[4] ?? null);

                    if (!$idx || !$subj || !$ds) { $rowsSkipped++; continue; }

                    // --- Resolve IDs (Using Lowercase Keys) ---
                    
                    // BU
                    $b = $this->t($r[18] ?? '');
                    $bKey = strtolower($b); // Normalized Key
                    if ($b !== '' && !isset($bu[$bKey])) {
                        $insBU->execute([$b]); // Insert original case
                        $bu[$bKey] = $this->db->lastInsertId();
                    }
                    $bid = $bu[$bKey] ?? null;

                    // FUNC
                    $f1 = $this->t($r[19] ?? '');
                    $f2 = $this->t($r[20] ?? '') ?: null;
                    $fk = strtolower($f1) . '|' . strtolower($f2 ?? ''); // Normalized Key
                    if ($f1 !== '' && !isset($func[$fk])) {
                        $insFunc->execute([$f1, $f2]); // Insert original case
                        $func[$fk] = $this->db->lastInsertId();
                    }
                    $fid = $func[$fk] ?? null;

                    // KARYAWAN
                    if (!isset($kar[$idx])) {
                        $insKar->execute([$idx, $name]);
                        $kar[$idx] = $this->db->lastInsertId();
                    }
                    $kid = $kar[$idx];

                    // TRAINING
                    $subjKey = strtolower($subj); // Normalized Key
                    if (!isset($train[$subjKey])) {
                        $insTrain->execute([
                            $subj, // Insert original case
                            $this->t($r[21] ?? ''), 
                            $this->t($r[8] ?? ''),  
                            $this->t($r[13] ?? ''), 
                            $this->t($r[14] ?? '')  
                        ]);
                        $train[$subjKey] = $this->db->lastInsertId();
                    }
                    $tid = $train[$subjKey];

                    // SESSION (This handles date differences automatically)
                    $codeSub = $this->t($r[3] ?? '');
                    $sk = $tid . '|' . $codeSub . '|' . $ds;
                    
                    if (!isset($sess[$sk])) {
                        $de = $this->parseDate($r[5] ?? null) ?: $ds;
                        $rawCredit = $r[6] ?? 0;
                        $creditHours = (float)(is_numeric($rawCredit) ? $rawCredit : str_replace(',', '.', (string)$rawCredit));
                        
                        $insSess->execute([
                            $tid, 
                            $codeSub, 
                            $this->t($r[10] ?? ''), 
                            $ds, 
                            $de, 
                            $creditHours, 
                            $this->t($r[7] ?? ''),  
                            $this->t($r[9] ?? '')   
                        ]);
                        $sess[$sk] = $this->db->lastInsertId();
                    }
                    $sid = $sess[$sk];

                    // --- Batch ---
                    $q_name = $this->db->quote($name);
                    $q_subj = $this->db->quote($subj);
                    $q_ds   = $this->db->quote($ds);
                    
                    $sqlKeys[] = "($excelRow, $sid, $kid, $q_name, $q_subj, $q_ds)";

                    $v_pre  = $this->f($r[11] ?? null);
                    $v_post = $this->f($r[12] ?? null);
                    $v_sub  = $this->f($r[15] ?? null);
                    $v_ins  = $this->f($r[16] ?? null);
                    $v_inf  = $this->f($r[17] ?? null);

                    $sqlScores[] = "(" . (int)$sid . ", " . (int)$kid . ", " . ($bid ?? 'NULL') . ", " . ($fid ?? 'NULL') . ", $v_pre, $v_post, $v_sub, $v_ins, $v_inf)";

                    if (count($sqlScores) >= $BATCH_LIMIT) {
                        $this->db->exec($baseKeysSQL . implode(',', $sqlKeys));
                        $this->db->exec($baseScoreSQL . implode(',', $sqlScores) . $onDupSQL);
                        $sqlKeys = [];
                        $sqlScores = [];
                    }
                }
                break;
            }
            $reader->close();

            if (!empty($sqlScores)) {
                $this->db->exec($baseKeysSQL . implode(',', $sqlKeys));
                $this->db->exec($baseScoreSQL . implode(',', $sqlScores) . $onDupSQL);
            }

            $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
            $this->db->exec("SET UNIQUE_CHECKS=1");
            $this->db->commit();

            $finalCount = (int)$this->db->query("SELECT COUNT(*) FROM score")->fetchColumn();
            $processed = $rowsProcessed - $rowsSkipped;
            $inserted = max(0, $finalCount - $initialCount);
            $updated = max(0, $processed - $inserted);
            $time = number_format(microtime(true) - $scriptStart, 2);

            $stmtLog = $this->db->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?,?,?,?)");
            $stmtLog->execute([basename($filePath), $username, 'Success', $processed]);

            return [
                'status' => 'success',
                'message' => "Processed in <b>{$time}s</b> | Total: {$processed} | Inserted: {$inserted} | Updated: {$updated}",
                'stats' => ['total' => $processed, 'unique' => $inserted, 'duplicates' => $updated, 'skipped' => $rowsSkipped]
            ];

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function getHistory($limit = 10) {
        try {
            $stmt = $this->db->query("SELECT * FROM uploads ORDER BY upload_time DESC LIMIT $limit");
            return $stmt->fetchAll();
        } catch (\Exception $e) { return []; }
    }
}
?>