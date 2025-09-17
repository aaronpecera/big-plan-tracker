<?php
// CORS configuration
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Test data for companies
$companies = [
    [
        'id' => 1,
        'name' => 'Tech Solutions S.A.',
        'email' => 'contact@techsolutions.com',
        'phone' => '+34 912 345 678',
        'address' => 'Calle Mayor 123, Madrid',
        'industry' => 'Tecnología',
        'employees' => 150,
        'created_at' => '2024-01-15',
        'active' => true
    ],
    [
        'id' => 2,
        'name' => 'Marketing Pro Ltd.',
        'email' => 'info@marketingpro.com',
        'phone' => '+34 934 567 890',
        'address' => 'Passeig de Gràcia 45, Barcelona',
        'industry' => 'Marketing',
        'employees' => 75,
        'created_at' => '2024-02-20',
        'active' => true
    ],
    [
        'id' => 3,
        'name' => 'Construcciones García',
        'email' => 'admin@construccionesgarcia.es',
        'phone' => '+34 955 123 456',
        'address' => 'Avenida de la Constitución 78, Sevilla',
        'industry' => 'Construcción',
        'employees' => 200,
        'created_at' => '2024-03-10',
        'active' => true
    ],
    [
        'id' => 4,
        'name' => 'Consultoría Financiera Plus',
        'email' => 'contacto@cfplus.com',
        'phone' => '+34 963 789 012',
        'address' => 'Plaza del Ayuntamiento 12, Valencia',
        'industry' => 'Finanzas',
        'employees' => 50,
        'created_at' => '2024-04-05',
        'active' => false
    ]
];

// Get action from query parameter
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            echo json_encode([
                'success' => true,
                'companies' => $companies
            ]);
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $company = array_filter($companies, function($c) use ($id) {
                return $c['id'] === $id;
            });
            
            if (empty($company)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Empresa no encontrada']);
            } else {
                echo json_encode([
                    'success' => true,
                    'company' => array_values($company)[0]
                ]);
            }
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }

            $newCompany = [
                'id' => count($companies) + 1,
                'name' => $input['name'],
                'email' => $input['email'] ?? '',
                'phone' => $input['phone'] ?? '',
                'address' => $input['address'] ?? '',
                'industry' => $input['industry'] ?? '',
                'employees' => intval($input['employees'] ?? 0),
                'created_at' => date('Y-m-d'),
                'active' => true
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Empresa creada exitosamente',
                'company' => $newCompany
            ]);
            break;

        case 'update':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($_GET['id'] ?? 0);
            
            if (!$input || $id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Empresa actualizada exitosamente'
            ]);
            break;

        case 'delete':
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Empresa eliminada exitosamente'
            ]);
            break;

        case 'stats':
            $totalCompanies = count($companies);
            $activeCompanies = count(array_filter($companies, function($c) {
                return $c['active'];
            }));
            $totalEmployees = array_sum(array_column($companies, 'employees'));
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_companies' => $totalCompanies,
                    'active_companies' => $activeCompanies,
                    'inactive_companies' => $totalCompanies - $activeCompanies,
                    'total_employees' => $totalEmployees,
                    'average_employees' => $totalCompanies > 0 ? round($totalEmployees / $totalCompanies, 2) : 0
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>