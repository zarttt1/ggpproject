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

    private function t($v) {
        if ($v === null) return '';
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        return trim(preg_replace('/\s+/', ' ', (string)$v));
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

    private function areNamesSimilar($name1, $name2) {
        $n1 = strtolower(trim(preg_replace('/\s+/', ' ', $name1)));
        $n2 = strtolower(trim(preg_replace('/\s+/', ' ', $name2)));

        if ($n1 === $n2) return true;
        if ($n1 === '' || $n2 === '') return false;

        if (strpos($n1, $n2) !== false || strpos($n2, $n1) !== false) return true;
        if (metaphone($n1) === metaphone($n2)) return true;

        $tokens1 = array_values(array_filter(explode(' ', preg_replace('/[^a-z ]/', '', $n1))));
        $tokens2 = array_values(array_filter(explode(' ', preg_replace('/[^a-z ]/', '', $n2))));

        if (empty($tokens1) || empty($tokens2)) return false;

        $matches = 0;
        foreach ($tokens1 as $t1) {
            foreach ($tokens2 as $t2) {
                if ($t1 === $t2 || metaphone($t1) === metaphone($t2)) {
                    $matches++;
                    break; 
                }
                if ((strlen($t1) === 1 && substr($t2, 0, 1) === $t1) || (strlen($t2) === 1 && substr($t1, 0, 1) === $t2)) {
                    $matches += 0.9; 
                    break;
                }
            }
        }
        
        $minWords = min(count($tokens1), count($tokens2));
        if ($minWords === 0) return false;
        
        return ($matches / $minWords) > 0.65;
    }

    public function processFile($filePath, $ext, $username = 'System', $originalName = null) {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $scriptStart = microtime(true);
        $logs = []; 
        
        $countTotal = 0;
        $countNew = 0;
        $countDup = 0;
        $countSkip = 0;
        $countWarn = 0;

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

            $bu = [];
            foreach($this->db->query("SELECT nama_bu, id_bu FROM bu") as $r) $bu[strtolower(trim($r['nama_bu']))] = $r['id_bu'];

            $kar = [];
            foreach($this->db->query("SELECT index_karyawan, id_karyawan, nama_karyawan FROM karyawan") as $r) {
                $kar[strtolower(trim($r['index_karyawan']))] = [
                    'id' => $r['id_karyawan'],
                    'name' => strtolower(trim(preg_replace('/\s+/', ' ', $r['nama_karyawan'])))
                ];
            }

            $train = [];
            foreach($this->db->query("SELECT nama_training, id_training FROM training") as $r) $train[strtolower(trim($r['nama_training']))] = $r['id_training'];

            $func = [];
            foreach ($this->db->query("SELECT id_func, func_n1, func_n2 FROM func") as $r) {
                $k = strtolower(trim($r['func_n1'])) . '|' . strtolower(trim($r['func_n2'] ?? ''));
                $func[$k] = $r['id_func'];
            }

            $sess = [];
            foreach ($this->db->query("SELECT id_session, id_training, code_sub, date_start FROM training_session") as $r) {
                $k = $r['id_training'] . '|' . trim($r['code_sub'] ?? '') . '|' . $r['date_start'];
                $sess[$k] = $r['id_session'];
            }

            $existingScores = [];
            $qScores = $this->db->query("SELECT id_session, id_karyawan FROM score");
            while ($row = $qScores->fetch(\PDO::FETCH_NUM)) {
                $existingScores[$row[0] . '|' . $row[1]] = true;
            }

            $insBU    = $this->db->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
            $insFunc  = $this->db->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?,?)");
            $insKar   = $this->db->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?,?)");
            $insTrain = $this->db->prepare("INSERT INTO training (nama_training, jenis, type, instructor_name, lembaga) VALUES (?,?,?,?,?)");
            $insSess  = $this->db->prepare("INSERT INTO training_session (id_training, code_sub, class, date_start, date_end, credit_hour, place, method) VALUES (?,?,?,?,?,?,?,?)");

            if ($ext === 'csv') $reader = new CsvReader();
            else $reader = new XlsxReader();
            $reader->open($filePath);

            $BATCH_LIMIT = 500;
            $sqlScores = [];
            $sqlKeys = [];
            $excelRow = 0; 

            $baseScoreSQL = "INSERT INTO score (id_session, id_karyawan, id_bu, id_func, pre, post, statis_subject, instructor, statis_infras) VALUES ";
            $baseKeysSQL  = "INSERT INTO tmp_upload_keys (excel_row, id_session, id_karyawan, nama, training, date_start) VALUES ";
            $onDupSQL = " ON DUPLICATE KEY UPDATE id_bu=VALUES(id_bu), id_func=VALUES(id_func), pre=VALUES(pre), post=VALUES(post), statis_subject=VALUES(statis_subject), instructor=VALUES(instructor), statis_infras=VALUES(statis_infras)";

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $excelRow++;
                    if ($excelRow === 1) continue; 

                    $r = $row->toArray();
                    $countTotal++;
                    $rowStatus = 'New';
                    $hasWarning = false;
                    $rowMsg = [];

                    $idx  = $this->t($r[0] ?? '');
                    $name = $this->t($r[1] ?? '');
                    $subj = $this->t($r[2] ?? '');
                    $ds   = $this->parseDate($r[4] ?? null);

                    if (!$idx || !$subj || !$ds) { 
                        $countSkip++;
                        $logs[] = ['row' => $excelRow, 'status' => 'Skipped', 'msg' => 'Missing Index, Subject, or Date'];
                        continue; 
                    }

                    $b = $this->t($r[18] ?? '');
                    $bKey = strtolower($b);
                    if ($b !== '' && !isset($bu[$bKey])) {
                        $insBU->execute([$b]);
                        $bu[$bKey] = $this->db->lastInsertId();
                    }
                    $bid = $bu[$bKey] ?? null;

                    $f1 = $this->t($r[19] ?? '');
                    $f2 = $this->t($r[20] ?? '') ?: null;
                    $fk = strtolower($f1) . '|' . strtolower($f2 ?? '');
                    if ($f1 !== '' && !isset($func[$fk])) {
                        $insFunc->execute([$f1, $f2]);
                        $func[$fk] = $this->db->lastInsertId();
                    }
                    $fid = $func[$fk] ?? null;

                    $idxKey = strtolower($idx);
                    if (isset($kar[$idxKey])) {
                        $dbName = $kar[$idxKey]['name'];
                        
                        if (!$this->areNamesSimilar($name, $dbName)) {
                            $hasWarning = true;
                            $rowMsg[] = "ID '$idx' in DB is '$dbName', but File says '$name'. Inserted using DB Identity.";
                        } 
                        $kid = $kar[$idxKey]['id'];
                    } else {
                        $insKar->execute([$idx, $name]);
                        $kid = $this->db->lastInsertId();
                        $kar[$idxKey] = ['id' => $kid, 'name' => strtolower(trim(preg_replace('/\s+/', ' ', $name)))];
                    }

                    $subjKey = strtolower($subj);
                    if (!isset($train[$subjKey])) {
                        $insTrain->execute([$subj, $this->t($r[21] ?? ''), $this->t($r[8] ?? ''), $this->t($r[13] ?? ''), $this->t($r[14] ?? '')]);
                        $train[$subjKey] = $this->db->lastInsertId();
                    }
                    $tid = $train[$subjKey];

                    $codeSub = $this->t($r[3] ?? '');
                    $sk = $tid . '|' . $codeSub . '|' . $ds;
                    
                    if (isset($sess[$sk])) {
                        $sid = $sess[$sk];
                    } else {
                        $de = $this->parseDate($r[5] ?? null) ?: $ds;
                        $rawCredit = $r[6] ?? 0;
                        $creditHours = (float)(is_numeric($rawCredit) ? $rawCredit : str_replace(',', '.', (string)$rawCredit));
                        $insSess->execute([$tid, $codeSub, $this->t($r[10] ?? ''), $ds, $de, $creditHours, $this->t($r[7] ?? ''), $this->t($r[9] ?? '')]);
                        $sid = $this->db->lastInsertId();
                        $sess[$sk] = $sid;
                    }

                    $scoreKey = $sid . '|' . $kid;
                    $isDuplicateScore = isset($existingScores[$scoreKey]);

                    if ($isDuplicateScore) {
                        $rowStatus = 'Duplicate';
                        $countDup++;
                        $rowMsg[] = "Record updated.";
                    } else {
                        $rowStatus = 'New';
                        $countNew++;
                    }

                    if ($hasWarning) {
                        $rowStatus = 'Warning';
                        $countWarn++;
                    }

                    if (!$isDuplicateScore) $existingScores[$scoreKey] = true;

                    $logs[] = [
                        'row' => $excelRow, 
                        'status' => $rowStatus, 
                        'msg' => empty($rowMsg) ? 'Successfully inserted.' : implode(' | ', $rowMsg)
                    ];

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

            $time = number_format(microtime(true) - $scriptStart, 2);
            $displayName = $originalName ?? basename($filePath);
            $stmtLog = $this->db->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?,?,?,?)");
            $stmtLog->execute([$displayName, $username, 'Success', $countTotal]);

            return [
                'status' => 'success',
                'message' => "Processed in <b>{$time}s</b>",
                'stats' => [
                    'total' => $countTotal, 
                    'unique' => $countNew, 
                    'duplicates' => $countDup, 
                    'skipped' => $countSkip,
                    'warnings' => $countWarn
                ],
                'logs' => $logs
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