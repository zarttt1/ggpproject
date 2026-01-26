<?php
// app/controllers/ReportController.php

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
                    <td><span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($category); ?></span></td>
                    <td><span class="badge type-info"><?php echo htmlspecialchars($training_type); ?></span></td>
                    <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($method); ?></span></td>
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
            echo '<tr><td colspan="9" style="text-align:center; padding: 25px; color:#888;">No records found.</td></tr>';
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