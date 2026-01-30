<?php
// app/controllers/EmployeeController.php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Training.php';

class EmployeeController {
    private $empModel;
    private $trainingModel;

    public function __construct($pdo) {
        $this->empModel = new Employee($pdo);
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

        $filters = [
            'search' => $_GET['search'] ?? '',
            'bu' => $_GET['bu'] ?? 'All BUs',
            'fn1' => $_GET['fn1'] ?? 'All Func N-1',
            'fn2' => $_GET['fn2'] ?? 'All Func N-2'
        ];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $data = $this->empModel->getAllEmployees($filters, $page);
        
        $bu_opts = $this->trainingModel->getBus();
        $other_opts = $this->empModel->getFilterOptions($filters['bu'], $filters['fn1']);
        $fn1_opts = $other_opts['fn1'];
        $fn2_opts = $other_opts['fn2'];

        require 'app/views/employee_reports.php';
    }

    public function search() {
        $this->checkAuth();

        $filters = [
            'search' => $_GET['ajax_search'] ?? '',
            'bu' => $_GET['bu'] ?? 'All BUs',
            'fn1' => $_GET['fn1'] ?? 'All Func N-1',
            'fn2' => $_GET['fn2'] ?? 'All Func N-2'
        ];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $data = $this->empModel->getAllEmployees($filters, $page);

        $tableHtml = $this->renderRows($data['data']);
        $paginationHtml = $this->renderPagination($data);

        header('Content-Type: application/json');
        echo json_encode(['table' => $tableHtml, 'pagination' => $paginationHtml]);
        exit;
    }

    public function filterOptions() {
        $this->checkAuth();
        $bu = $_GET['bu'] ?? 'All BUs';
        $fn1 = $_GET['fn1'] ?? 'All Func N-1';
        
        $data = $this->empModel->getFilterOptions($bu, $fn1);
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function getAbbreviation($name) {
        if (empty($name) || $name === '-') return '-';
        $manual_map = [
            'Human Resources' => 'HR', 'Information Technology' => 'IT',
            'Quality Assurance' => 'QA', 'General Affairs' => 'GA',
            'Supply Chain' => 'SCM', 'Research and Development' => 'R&D',
            'Production' => 'PROD', 'Finance' => 'FIN'
        ];
        if (isset($manual_map[$name])) return $manual_map[$name];
        
        $words = explode(' ', $name);
        if (count($words) > 1) {
            $acronym = '';
            foreach ($words as $w) $acronym .= strtoupper(substr($w, 0, 1));
            return $acronym;
        }
        return (strlen($name) > 4) ? strtoupper(substr($name, 0, 3)) : strtoupper($name);
    }

    private function renderRows($rows) {
        ob_start();
        if (count($rows) > 0) {
            foreach ($rows as $e) {
                $initials = strtoupper(substr($e['nama_karyawan'], 0, 1));
                $partCount = $e['total_participation'];
                $badgeClass = ($partCount > 5) ? 'badge-high' : (($partCount > 0) ? 'badge-med' : 'badge-low');
                $bu = $this->getAbbreviation($e['latest_bu'] ?? '-');
                ?>
                <tr>
                    <td style="font-family:'Poppins', sans-serif; font-weight:600; color:#555;"><?php echo htmlspecialchars($e['index_karyawan']); ?></td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?php echo $initials; ?></div> 
                            <span style="font-weight:600; color:#333;"><?php echo htmlspecialchars($e['nama_karyawan']); ?></span>
                        </div>
                    </td>
                    <td><span class="text-subtle"><?php echo htmlspecialchars($bu); ?></span></td>
                    <td><span class="text-subtle"><?php echo htmlspecialchars($e['latest_func_n1'] ?? '-'); ?></span></td>
                    <td><span class="text-subtle"><?php echo htmlspecialchars($e['latest_func_n2'] ?? '-'); ?></span></td>
                    <td style="text-align:center;"><span class="participation-badge <?php echo $badgeClass; ?>"><?php echo $partCount; ?> Session</span></td>
                    <td>
                        <button class="btn-view" onclick="window.location.href='index.php?action=employee_history&id=<?php echo $e['id_karyawan']; ?>'">
                            <span>View History</span>
                            <svg><rect x="0" y="0"></rect></svg>
                        </button>
                    </td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="7" style="text-align:center; padding: 25px; color:#888;">No employees found.</td></tr>';
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
    
    public function history() {
        $this->checkAuth();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id === 0) {
            header("Location: index.php?action=employees");
            exit();
        }

        $employee = $this->empModel->getEmployeeById($id);
        if (!$employee) {
            die("Employee not found.");
        }

        $stats = $this->empModel->getEmployeeStats($id);
        
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $historyData = $this->empModel->getTrainingHistory($id, $search, $page);

        $total_sessions = $stats['total_sessions'];
        $total_hours = $stats['total_hours'] ?? 0;
        $count_tech = $stats['count_tech'] ?? 0;
        $count_soft = $stats['count_soft'] ?? 0;

        require 'app/views/employee_history.php';
    }

    public function historySearch() {
        $this->checkAuth();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $search = $_GET['ajax_search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $data = $this->empModel->getTrainingHistory($id, $search, $page);

        $tableHtml = $this->renderHistoryRows($data['data']);
        $paginationHtml = $this->renderPagination($data);

        header('Content-Type: application/json');
        echo json_encode(['table' => $tableHtml, 'pagination' => $paginationHtml]);
        exit;
    }

    private function renderHistoryRows($rows) {
        ob_start();
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $category = $row['category'] ?? '';
                $training_type = $row['training_type'] ?? '';
                $method = $row['method'] ?? '';

                $catClass = (stripos($category, 'Technical') !== false) ? 'type-tech' : ((stripos($category, 'Soft') !== false) ? 'type-soft' : 'type-default');
                $methodClass = (stripos($method, 'Online') !== false) ? 'method-online' : 'method-class';
                $date_display = formatDateRange($row['date_start'], $row['date_end'] ?? '');
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600; color:#333; line-height:1.4;">
                            <?php echo htmlspecialchars($row['nama_training']); ?>
                        </div>
                    </td>
                    <td style="color:#666; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; white-space: nowrap;">
                        <?php echo $date_display; ?>
                    </td>
                    
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

                    <td style="text-align: center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour']); ?></td>
                    <td style="text-align: center; color:#888;"><?php echo $row['pre']; ?></td>
                    <td style="text-align: center;">
                        <span class="score-box"><?php echo $row['post']; ?></span>
                    </td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="6" style="text-align:center; padding: 25px; color:#888;">No training history found.</td></tr>';
        }
        return ob_get_clean();
    }

    public function exportHistory() {
        $this->checkAuth();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id === 0) exit("Invalid ID");

        $user = $this->empModel->getEmployeeById($id);
        if (!$user) exit("Employee not found");

        $templatePath = __DIR__ . '/../../uploads/Employee Reports.xlsx';
        if (!file_exists($templatePath)) exit("Template not found at: $templatePath");

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('C7', ': ' . $user['index_karyawan']);
        $sheet->setCellValue('C8', ': ' . $user['nama_karyawan']);
        $sheet->setCellValue('C9', ': ' . ($user['bu'] ?? '-'));
        $sheet->setCellValue('C10', ': ' . ($user['func'] ?? '-'));
        $sheet->setCellValue('C11', ': ' . ($user['func2'] ?? '-'));

        $row = 14;
        $no = 1;

        if (!empty($history['data'])) {
            foreach ($history['data'] as $h) {
                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, $h['nama_training']);
                $sheet->setCellValue('C' . $row, date('d-M-Y', strtotime($h['date_start'])));
                $sheet->setCellValue('D' . $row, $h['credit_hour']);      
                $sheet->setCellValue('E' . $row, $h['instructor_name']); 
                $sheet->setCellValue('F' . $row, $h['lembaga']);         
                $sheet->setCellValue('G' . $row, $h['training_type']);   
                $sheet->setCellValue('H' . $row, $h['method']);          
                $sheet->setCellValue('I' . $row, $h['pre']);             
                $sheet->setCellValue('J' . $row, $h['post']);            

                $sheet->getStyle("A$row:J$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
                $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C$row:J$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $row++;
                $no++;
            }
        }

        $startWatermark = $row;
        $endWatermark   = $row + 1;

        $sheet->mergeCells("A$startWatermark:J$endWatermark");
        $sheet->setCellValue("A$startWatermark", "Created By Dashboard Training Coverage");

        $sheet->getStyle("A$startWatermark:J$endWatermark")->applyFromArray([
            'font' => [
                'italic' => true,
                'size'   => 10,
                'color'  => ['argb' => 'FF555555'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $user['nama_karyawan']);
        $filename = "Employee_Reports_" . $cleanName . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'. $filename .'"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
?>