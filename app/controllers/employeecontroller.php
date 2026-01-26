<?php
// app/controllers/EmployeeController.php

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
}
?>