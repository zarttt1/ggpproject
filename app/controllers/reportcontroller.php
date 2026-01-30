<?php
// app/controllers/ReportController.php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
require_once __DIR__ . '/../models/Training.php';

class ReportController {
    private $trainingModel;

    public function __construct($pdo) {
        $this->trainingModel = new Training($pdo);
    }

    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=show_login");
            exit();
        }
    }

    public function index() {
        $this->checkAuth();

        $filters = $this->getFiltersFromRequest();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $data = $this->trainingModel->getAllSessions($filters, $page);
        
        $categories_opt = $this->trainingModel->getCategories(); // Column: jenis
        $types_opt = $this->trainingModel->getTrainingTypes();   // Column: type
        $methods_opt = $this->trainingModel->getMethods();
        $codes_opt = $this->trainingModel->getCodes();

        $results = $data['data'];
        $total_records = $data['total_records'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];
        
        $has_active_filters = (
            $filters['category'] !== 'All Categories' || 
            $filters['type'] !== 'All Types' || 
            $filters['method'] !== 'All Methods' || 
            $filters['code'] !== 'All Codes' || 
            !empty($filters['start']) || 
            !empty($filters['end'])
        );

        require 'app/views/reports.php';
    }

    public function search() {
        $this->checkAuth();

        $filters = $this->getFiltersFromRequest();
        if (isset($_GET['ajax_search'])) {
            $filters['search'] = $_GET['ajax_search'];
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $data = $this->trainingModel->getAllSessions($filters, $page);

        $tableHtml = $this->renderTableRows($data['data']);
        $paginationHtml = $this->renderPagination($data);

        header('Content-Type: application/json');
        echo json_encode(['table' => $tableHtml, 'pagination' => $paginationHtml]);
        exit;
    }

    private function getFiltersFromRequest() {
        return [
            'search' => $_GET['search'] ?? '',
            'category' => $_GET['category'] ?? 'All Categories',
            'type' => $_GET['type'] ?? 'All Types',
            'method' => $_GET['method'] ?? 'All Methods',
            'code' => $_GET['code'] ?? 'All Codes',
            'start' => $_GET['start'] ?? '',
            'end' => $_GET['end'] ?? ''
        ];
    }

    private function renderTableRows($rows) {
        ob_start();
        if (count($rows) > 0) {
            foreach($rows as $row) {
                $category = $row['category'] ?? '';
                $method = $row['method'] ?? '';
                $training_type = $row['training_type'] ?? '';
                
                $catClass = (stripos($category, 'Technical') !== false) ? 'type-tech' : ((stripos($category, 'Soft') !== false) ? 'type-soft' : 'type-default');
                $methodClass = (stripos($method, 'Inclass') !== false) ? 'method-inclass' : 'method-online';
                $avgScore = $row['avg_post'] ? number_format($row['avg_post'], 1) . '%' : '-';
                $date_display = formatDateRange($row['date_start'] ?? '', $row['date_end'] ?? '');
                
                ?>
                <tr>
                    <td>
                        <div class="training-cell">
                            <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                            <div>
                                <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training'] ?? ''); ?></div>
                                <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub'] ?? ''); ?></div>
                            </div>
                        </div>
                    </td>
                    
                    <td style="white-space: nowrap; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; color: #555;"><?php echo $date_display; ?></td>
                    
                    <td style="white-space: normal; width: 220px; min-width: 200px;">
                        <div style="display: flex; gap: 4px; row-gap: 4px; flex-wrap: wrap; align-items: center;">
                            <?php if($category): ?>
                                <span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($category); ?></span>
                            <?php endif; ?>
                            <?php if($training_type): ?>
                                <span class="badge type-info"><?php echo htmlspecialchars($training_type); ?></span>
                            <?php endif; ?>
                            <?php if($method): ?>
                                <span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($method); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td style="text-align:center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour'] ?? '0'); ?></td>
                    <td style="text-align:center;"><?php echo $row['participants']; ?></td>
                    <td class="score"><?php echo $avgScore; ?></td>
                    <td>
                        <button class="btn-view" onclick="window.location.href='index.php?action=details&id=<?php echo $row['id_session']; ?>'">
                            <span>View Details</span>
                            <svg><rect x="0" y="0"></rect></svg>
                        </button>
                    </td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="7" style="text-align:center; padding: 25px; color:#888;">No records found.</td></tr>';
        }
        return ob_get_clean();
    }

    private function renderPagination($data) {
        $page = $data['current_page'];
        $total_pages = $data['total_pages'];
        $total_records = $data['total_records'];
        $limit = 10;
        $offset = ($page - 1) * $limit;

        ob_start();
        ?>
        <div>Showing <?php echo ($total_records > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Records</div>
        <div class="pagination-controls">
            <?php if($page > 1): ?>
                <a href="#" onclick="changePage(<?php echo $page - 1; ?>); return false;" class="btn-next" style="transform: rotate(180deg); display:inline-block;">
                    <i data-lucide="chevron-right" style="width:16px;"></i>
                </a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <a href="#" onclick="changePage(<?php echo $i; ?>); return false;" class="page-num <?php if($i==$page) echo 'active'; ?>"><?php echo $i; ?></a>
                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                    <span class="dots">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="#" onclick="changePage(<?php echo $page + 1; ?>); return false;" class="btn-next">
                    Next <i data-lucide="chevron-right" style="width:16px;"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function details() {
        $this->checkAuth();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id === 0) {
            header("Location: index.php?action=reports");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_training'])) {
            if (($_SESSION['role'] ?? '') === 'admin') {
                $data = [
                    'title' => trim($_POST['title']),
                    'code' => trim($_POST['code']),
                    'credit_hour' => (float)$_POST['credit_hour'],
                    'date_start' => $_POST['date_start'],
                    'date_end' => $_POST['date_end']
                ];
                $this->trainingModel->updateSession($id, $data);
                header("Location: index.php?action=details&id=" . $id);
                exit();
            }
        }

        $meta = $this->trainingModel->getSessionById($id);
        if (!$meta) {
            echo "Session not found."; 
            exit();
        }
        
        $stats = $this->trainingModel->getSessionStats($id);
        $top_improvers = $this->trainingModel->getTopImprovers($id);
        
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $participantsData = $this->trainingModel->getParticipants($id, $search, $page);

        $training_name = $meta['nama_training'];
        $code_sub = $meta['code_sub'];
        $credit_hour = $meta['credit_hour'];
        $date_start_raw = $meta['date_start'];
        $date_end_raw = $meta['date_end'];
        $display_date = formatDateRange($date_start_raw, $date_end_raw);
        
        $total_participants = $stats['total'] > 0 ? $stats['total'] : 0;
        $avg_pre = number_format($stats['avg_pre'] ?? 0, 1);
        $avg_post = number_format($stats['avg_post'] ?? 0, 1);
        $sat_subject = $stats['avg_subject'] ? number_format($stats['avg_subject'], 1) : '-';
        $sat_instructor = $stats['avg_instructor'] ? number_format($stats['avg_instructor'], 1) : '-';
        $sat_infras = $stats['avg_infras'] ? number_format($stats['avg_infras'], 1) : '-';
        
        $histLabels = ['0-20', '21-40', '41-60', '61-80', '81-100'];
        $preHistData = [$stats['pre_0_20'], $stats['pre_21_40'], $stats['pre_41_60'], $stats['pre_61_80'], $stats['pre_81_100']];
        $postHistData = [$stats['post_0_20'], $stats['post_21_40'], $stats['post_41_60'], $stats['post_61_80'], $stats['post_81_100']];

        require 'app/views/details.php';
    }

    public function detailsSearch() {
        $this->checkAuth();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $search = $_GET['ajax_search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $data = $this->trainingModel->getParticipants($id, $search, $page);

        $tableHtml = $this->renderParticipantRows($data['data']);
        $paginationHtml = $this->renderDetailsPagination($data, $id, $search);

        header('Content-Type: application/json');
        echo json_encode(['table' => $tableHtml, 'pagination' => $paginationHtml]);
        exit;
    }
    
    private function renderParticipantRows($rows) {
        ob_start();
        if (count($rows) > 0) {
            foreach ($rows as $p) {
                $improvement = $p['post'] - $p['pre'];
                $impSign = ($improvement > 0) ? '+' : '';
                $badgeClass = ($improvement >= 0) ? 'badge-improvement' : 'badge-decline';
                $initials = strtoupper(substr($p['nama_karyawan'], 0, 1));
                ?>
                <tr>
                    <td style="font-family:'Poppins', sans-serif; font-weight:600; color:#555;"><?php echo htmlspecialchars($p['index_karyawan']); ?></td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?php echo $initials; ?></div> 
                            <span style="font-weight:600; color:#333;"><?php echo htmlspecialchars($p['nama_karyawan']); ?></span>
                        </div>
                    </td>
                    <td><span style="color:#666; font-size:13px;"><?php echo htmlspecialchars($p['nama_bu']); ?></span></td>
                    <td><span style="color:#666; font-size:13px;"><?php echo htmlspecialchars($p['func_n1']); ?></span></td>
                    <td style="text-align:center; color:#888;"><?php echo $p['pre']; ?></td>
                    <td style="text-align:center;"><strong style="color:#197B40"><?php echo $p['post']; ?></strong></td>
                    <td style="text-align:center;"><span class="<?php echo $badgeClass; ?>"><?php echo $impSign . $improvement; ?></span></td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="7" style="text-align:center; padding: 25px; color:#888;">No participants found.</td></tr>';
        }
        return ob_get_clean();
    }

    private function renderDetailsPagination($data, $id, $search) {
        $page = $data['current_page'];
        $total_pages = $data['total_pages'];
        $total_records = $data['total_records'];
        $limit = 10;
        $offset = ($page - 1) * $limit;

        ob_start();
        ?>
        <div>Showing <?php echo ($total_records > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Records</div>
        <div class="pagination-controls">
            <?php if($page > 1): ?>
                <a href="#" onclick="changePage(<?php echo $page - 1; ?>); return false;" class="page-btn">&lt;</a>
            <?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <a href="#" onclick="changePage(<?php echo $i; ?>); return false;" class="page-btn <?php if($i==$page) echo 'active'; ?>"><?php echo $i; ?></a>
                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                    <span class="dots">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="#" onclick="changePage(<?php echo $page + 1; ?>); return false;" class="page-btn">&gt;</a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

   public function exportSession() {
    $this->checkAuth();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id === 0) exit("Invalid ID");

    $meta = $this->trainingModel->getSessionById($id);
    if (!$meta) exit("Session not found");

    $stats = $this->trainingModel->getSessionStats($id);
    $participants = $this->trainingModel->getParticipantsForExport($id);

    $templatePath = __DIR__ . '/../../uploads/Training Reports.xlsx';

    if (!file_exists($templatePath)) {
        $templatePath = __DIR__ . '/../../public/Training Reports.xlsx';
        if (!file_exists($templatePath)) exit("Template not found.");
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('C7', ': ' . $meta['nama_training']);
    $sheet->setCellValue('C8', ': ' . date('d M Y', strtotime($meta['date_start'])));
    $sheet->setCellValue('C9', ': ' . $stats['total'] . ' Orang');
    $sheet->setCellValue('F7', ': ' . $meta['code_sub']);

    $scores = [
        '12' => $stats['avg_subject'],
        '13' => $stats['avg_instructor'],
        '14' => $stats['avg_infras']
    ];

    foreach ($scores as $rowIdx => $scoreValue) {
        $val = (float)$scoreValue;
        $sheet->setCellValue('C' . $rowIdx, ': ' . number_format($val, 2));

        if ($val >= 8.51) $ket = "SANGAT BAIK";
        elseif ($val >= 7.01) $ket = "BAIK";
        elseif ($val >= 5.01) $ket = "CUKUP";
        elseif ($val >= 3.01) $ket = "KURANG";
        else $ket = "SGT KURANG";

        $sheet->setCellValue('E' . $rowIdx, ': ' . $ket);
    }

    $row = 17;
    $no = 1;
    foreach ($participants as $p) {
        $sheet->setCellValue('A' . $row, $no);
        $sheet->setCellValue('B' . $row, $p['index_karyawan']);
        $sheet->setCellValue('C' . $row, $p['nama_karyawan']);
        $sheet->setCellValue('D' . $row, $p['func_n2']); 
        $sheet->setCellValue('E' . $row, $p['pre']);
        $sheet->setCellValue('F' . $row, $p['post']);

        $status = ($p['post'] >= 75) ? "Lulus" : "Remedial";
        $sheet->setCellValue('G' . $row, $status);

        $sheet->getStyle("A$row:G$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
        $no++;
    }

    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $meta['nama_training']);
    $filename = "Report_" . $cleanName . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'. $filename .'"');
    header('Cache-Control: max-age=0');

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}
}
?>