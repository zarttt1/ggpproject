<?php
// app/controllers/DashboardController.php

require_once __DIR__ . '/../models/Training.php';

class DashboardController {
    private $trainingModel;

    public function __construct($pdo) {
        $this->trainingModel = new Training($pdo);
    }

    public function index() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php");
            exit();
        }

        $filters = [
            'bu' => $_GET['bu'] ?? 'All',
            'func_n1' => $_GET['func_n1'] ?? 'All',
            'func_n2' => $_GET['func_n2'] ?? 'All',
            'type' => $_GET['type'] ?? 'All',
            'search' => $_GET['search'] ?? '',
            'start' => $_GET['start'] ?? '',
            'end' => $_GET['end'] ?? '',
            'training_name' => $_GET['training_name'] ?? 'All'
        ];

        $stats = $this->trainingModel->getStats($filters);
        $trainings = $this->trainingModel->getTrainingList($filters);
        
        $opt_bu = $this->trainingModel->getBus();
        $opt_func1 = $this->trainingModel->getFuncN1($filters['bu']);
        $opt_func2 = $this->trainingModel->getFuncN2($filters['bu'], $filters['func_n1']);
        $opt_type = $this->trainingModel->getTypes();

        require 'app/views/dashboard.php';
    }

    public function getFilterOptions() {
         $bu = $_GET['bu'] ?? 'All';
         $func_n1 = $_GET['func_n1'] ?? 'All';

         $response = [
             'fn1' => $this->trainingModel->getFuncN1($bu),
             'fn2' => $this->trainingModel->getFuncN2($bu, $func_n1)
         ];

         header('Content-Type: application/json');
         echo json_encode($response);
         exit;
    }

    public function search() {
        $search_term = $_GET['ajax_search'] ?? '';
        
        $filters = ['search' => $search_term]; 
        $results = $this->trainingModel->getTrainingList($filters, 50);

        if (count($results) > 0) {
            foreach($results as $row) {
                $date_display = formatDateRange($row['date_start'], $row['date_end']);
                $methodClass = (stripos($row['method'], 'Inclass') !== false) ? 'method-inclass' : 'method-online';
                
                echo '<tr onclick="selectTraining(this, \''.addslashes($row['nama_training']).'\')" style="cursor: pointer;">
                    <td>
                        <div class="training-cell">
                            <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                            <div>
                                <div class="training-name-text">'.htmlspecialchars($row['nama_training']).'</div>
                                <div style="font-size:11px; color:#888;">'.htmlspecialchars($row['code_sub']).'</div>
                            </div>
                        </div>
                    </td>
                    <td style="white-space: nowrap; font-family:\'Poppins\', sans-serif; font-size:12px; font-weight:500; color: #555;">'.$date_display.'</td>
                    <td><span class="badge '.$methodClass.'">'.htmlspecialchars($row['method']).'</span></td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="3" style="text-align:center; padding: 20px; color:#777;">No training programs found.</td></tr>';
        }
        exit;
    }
}
?>