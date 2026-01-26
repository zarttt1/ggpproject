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

    private function parseDate($v) {
        if (empty($v)) return null;
        if ($v instanceof \DateTime || $v instanceof \DateTimeImmutable) return $v->format('Y-m-d');
        if (is_string($v)) {
            $t = strtotime(str_replace('/', '-', $v));
            return $t ? date('Y-m-d', $t) : null;
        }
        return null;
    }

    private function f($v) {
        if ($v === '' || $v === null) return 'NULL';
        if (is_numeric($v)) return (float)$v;
        return (float) str_replace(',', '.', $v);
    }

    public function processFile($filePath, $ext, $username = 'System') {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $scriptStart = microtime(true);

        try {
            $this->db->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_upload_keys (
                excel_row INT, id_session INT, id_karyawan INT, 
                nama VARCHAR(255), training VARCHAR(255), date_start DATE, 
                KEY idx_chk (id_session, id_karyawan)
            ) ENGINE=InnoDB");
            $this->db->exec("DELETE FROM tmp_upload_keys");

            $this->db->beginTransaction();
            $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->db->exec("SET UNIQUE_CHECKS=0");

            $initialCount = (int)$this->db->query("SELECT COUNT(*) FROM score")->fetchColumn();

            $bu = [];
            foreach($this->db->query("SELECT nama_bu, id_bu FROM bu") as $r) $bu[trim($r['nama_bu'])] = $r['id_bu'];

            $kar = [];
            foreach($this->db->query("SELECT index_karyawan, id_karyawan FROM karyawan") as $r) $kar[trim($r['index_karyawan'])] = $r['id_karyawan'];

            $train = [];
            foreach($this->db->query("SELECT nama_training, id_training FROM training") as $r) $train[trim($r['nama_training'])] = $r['id_training'];

            $func = [];
            foreach ($this->db->query("SELECT id_func, func_n1, func_n2 FROM func") as $r) {
                $k = trim($r['func_n1']) . '|' . trim($r['func_n2'] ?? '');
                $func[$k] = $r['id_func'];
            }

            $sess = [];
            foreach ($this->db->query("SELECT id_session, id_training, code_sub, date_start FROM training_session") as $r) {
                $k = $r['id_training'] . '|' . trim($r['code_sub'] ?? '') . '|' . $r['date_start'];
                $sess[$k] = $r['id_session'];
            }

            $insBU    = $this->db->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
            $insFunc  = $this->db->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?,?)");
            $insKar   = $this->db->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?,?)");
            $insTrain = $this->db->prepare("INSERT INTO training (nama_training, jenis, type) VALUES (?,?,?)");
            $insSess  = $this->db->prepare("INSERT INTO training_session (id_training, code_sub, class, date_start, date_end, credit_hour, place, method) VALUES (?,?,?,?,?,?,?,?)");

            if ($ext === 'csv') $reader = new CsvReader();
            else $reader = new XlsxReader();
            $reader->open($filePath);

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

                    $idx  = trim($r[1] ?? '');
                    $name = trim($r[2] ?? '');
                    $subj = trim($r[3] ?? '');
                    $ds   = $this->parseDate($r[5] ?? null);

                    if (!$idx || !$subj || !$ds) { $rowsSkipped++; continue; }
                    
                    $b = trim($r[18] ?? '');
                    if (!isset($bu[$b])) {
                        $insBU->execute([$b]);
                        $bu[$b] = $this->db->lastInsertId();
                    }
                    $bid = $bu[$b];

                    $f1 = trim($r[19] ?? '');
                    $f2 = trim($r[20] ?? '') ?: null;
                    $fk = $f1 . '|' . ($f2 ?? '');
                    if (!isset($func[$fk])) {
                        $insFunc->execute([$f1, $f2]);
                        $func[$fk] = $this->db->lastInsertId();
                    }
                    $fid = $func[$fk];

                    if (!isset($kar[$idx])) {
                        $insKar->execute([$idx, $name]);
                        $kar[$idx] = $this->db->lastInsertId();
                    }
                    $kid = $kar[$idx];

                    if (!isset($train[$subj])) {
                        $insTrain->execute([
                            $subj, 
                            trim($r[21] ?? ''),
                            trim($r[9] ?? '')
                        ]);
                        $train[$subj] = $this->db->lastInsertId();
                    }
                    $tid = $train[$subj];


                    $codeSub = trim($r[4] ?? '');
                    $sk = $tid . '|' . $codeSub . '|' . $ds;
                    
                    if (!isset($sess[$sk])) {
                        $de = $this->parseDate($r[6] ?? null) ?: $ds;
                        $rawCredit = $r[7] ?? 0;
                        $creditHours = (float)(is_numeric($rawCredit) ? $rawCredit : str_replace(',', '.', $rawCredit));
                        
                        $insSess->execute([
                            $tid, 
                            $codeSub, 
                            '',
                            $ds, 
                            $de, 
                            $creditHours, 
                            trim($r[8] ?? ''),
                            trim($r[10] ?? '')
                        ]);
                        $sess[$sk] = $this->db->lastInsertId();
                    }
                    $sid = $sess[$sk];

                    $q_name = $this->db->quote($name);
                    $q_subj = $this->db->quote($subj);
                    $q_ds   = $this->db->quote($ds);
                    
                    $sqlKeys[] = "($excelRow, $sid, $kid, $q_name, $q_subj, $q_ds)";

                    $v_pre  = $this->f($r[11] ?? null);
                    $v_post = $this->f($r[12] ?? null);
                    $v_sub  = $this->f($r[15] ?? null);
                    $v_ins  = $this->f($r[16] ?? null);
                    $v_inf  = $this->f($r[17] ?? null);

                    $sqlScores[] = "($sid, $kid, $bid, $fid, $v_pre, $v_post, $v_sub, $v_ins, $v_inf)";

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