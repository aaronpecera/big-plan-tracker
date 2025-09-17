<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/UserManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$userManager = new UserManager();
$user = $userManager->getUserById($_SESSION['user_id']);

if (!$user) {
    http_response_code(403);
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit;
}

$db = MongoDBConnection::getInstance()->getDatabase();
$messagesCollection = $db->messages;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $chatWith = $_GET['chat_with'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        if (!$chatWith) {
            // Obtener lista de conversaciones
            $conversations = getConversations($_SESSION['user_id'], $messagesCollection, $userManager);
            echo json_encode([
                'success' => true,
                'conversations' => $conversations
            ]);
        } else {
            // Obtener mensajes de una conversación específica
            $messages = getMessages($_SESSION['user_id'], $chatWith, $messagesCollection, $limit, $offset);
            
            // Marcar mensajes como leídos
            markMessagesAsRead($_SESSION['user_id'], $chatWith, $messagesCollection);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener mensajes: ' . $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $action = $input['action'] ?? 'send';
        
        switch ($action) {
            case 'send':
                $result = sendMessage($input, $_SESSION['user_id'], $messagesCollection, $userManager);
                break;
            case 'mark_read':
                $result = markMessagesAsRead($_SESSION['user_id'], $input['chat_with'], $messagesCollection);
                break;
            case 'delete':
                $result = deleteMessage($input['message_id'], $_SESSION['user_id'], $messagesCollection);
                break;
            default:
                throw new Exception('Acción no válida');
        }

        echo json_encode($result);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al procesar solicitud: ' . $e->getMessage()]);
    }
}

function getConversations($userId, $messagesCollection, $userManager) {
    $pipeline = [
        [
            '$match' => [
                '$or' => [
                    ['sender_id' => new MongoDB\BSON\ObjectId($userId)],
                    ['recipient_id' => new MongoDB\BSON\ObjectId($userId)]
                ]
            ]
        ],
        [
            '$sort' => ['created_at' => -1]
        ],
        [
            '$group' => [
                '_id' => [
                    '$cond' => [
                        'if' => ['$eq' => ['$sender_id', new MongoDB\BSON\ObjectId($userId)]],
                        'then' => '$recipient_id',
                        'else' => '$sender_id'
                    ]
                ],
                'last_message' => ['$first' => '$$ROOT'],
                'unread_count' => [
                    '$sum' => [
                        '$cond' => [
                            'if' => [
                                '$and' => [
                                    ['$eq' => ['$recipient_id', new MongoDB\BSON\ObjectId($userId)]],
                                    ['$eq' => ['$read', false]]
                                ]
                            ],
                            'then' => 1,
                            'else' => 0
                        ]
                    ]
                ]
            ]
        ]
    ];

    $conversations = [];
    $results = $messagesCollection->aggregate($pipeline);

    foreach ($results as $result) {
        $otherUserId = (string)$result['_id'];
        $otherUser = $userManager->getUserById($otherUserId);
        
        if ($otherUser) {
            $conversations[] = [
                'user_id' => $otherUserId,
                'user_name' => $otherUser['name'],
                'user_email' => $otherUser['email'],
                'user_role' => $otherUser['role'],
                'last_message' => [
                    'content' => $result['last_message']['content'],
                    'created_at' => $result['last_message']['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'sender_id' => (string)$result['last_message']['sender_id']
                ],
                'unread_count' => $result['unread_count']
            ];
        }
    }

    return $conversations;
}

function getMessages($userId, $chatWith, $messagesCollection, $limit, $offset) {
    $filter = [
        '$or' => [
            [
                'sender_id' => new MongoDB\BSON\ObjectId($userId),
                'recipient_id' => new MongoDB\BSON\ObjectId($chatWith)
            ],
            [
                'sender_id' => new MongoDB\BSON\ObjectId($chatWith),
                'recipient_id' => new MongoDB\BSON\ObjectId($userId)
            ]
        ]
    ];

    $options = [
        'sort' => ['created_at' => -1],
        'limit' => $limit,
        'skip' => $offset
    ];

    $messages = [];
    $results = $messagesCollection->find($filter, $options);

    foreach ($results as $message) {
        $messages[] = [
            'id' => (string)$message['_id'],
            'content' => $message['content'],
            'sender_id' => (string)$message['sender_id'],
            'recipient_id' => (string)$message['recipient_id'],
            'created_at' => $message['created_at']->toDateTime()->format('Y-m-d H:i:s'),
            'read' => $message['read'] ?? false,
            'message_type' => $message['message_type'] ?? 'text',
            'attachments' => $message['attachments'] ?? []
        ];
    }

    return array_reverse($messages); // Mostrar en orden cronológico
}

function sendMessage($input, $senderId, $messagesCollection, $userManager) {
    $recipientId = $input['recipient_id'] ?? null;
    $content = trim($input['content'] ?? '');
    $messageType = $input['message_type'] ?? 'text';
    $attachments = $input['attachments'] ?? [];

    if (!$recipientId || !$content) {
        throw new Exception('Destinatario y contenido son requeridos');
    }

    // Verificar que el destinatario existe
    $recipient = $userManager->getUserById($recipientId);
    if (!$recipient) {
        throw new Exception('Destinatario no encontrado');
    }

    $message = [
        'sender_id' => new MongoDB\BSON\ObjectId($senderId),
        'recipient_id' => new MongoDB\BSON\ObjectId($recipientId),
        'content' => $content,
        'message_type' => $messageType,
        'attachments' => $attachments,
        'read' => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $messagesCollection->insertOne($message);

    if ($result->getInsertedCount() > 0) {
        return [
            'success' => true,
            'message_id' => (string)$result->getInsertedId(),
            'message' => 'Mensaje enviado exitosamente'
        ];
    } else {
        throw new Exception('Error al enviar mensaje');
    }
}

function markMessagesAsRead($userId, $chatWith, $messagesCollection) {
    $filter = [
        'sender_id' => new MongoDB\BSON\ObjectId($chatWith),
        'recipient_id' => new MongoDB\BSON\ObjectId($userId),
        'read' => false
    ];

    $update = [
        '$set' => [
            'read' => true,
            'read_at' => new MongoDB\BSON\UTCDateTime()
        ]
    ];

    $result = $messagesCollection->updateMany($filter, $update);

    return [
        'success' => true,
        'marked_count' => $result->getModifiedCount()
    ];
}

function deleteMessage($messageId, $userId, $messagesCollection) {
    $filter = [
        '_id' => new MongoDB\BSON\ObjectId($messageId),
        'sender_id' => new MongoDB\BSON\ObjectId($userId) // Solo el remitente puede eliminar
    ];

    $result = $messagesCollection->deleteOne($filter);

    if ($result->getDeletedCount() > 0) {
        return [
            'success' => true,
            'message' => 'Mensaje eliminado exitosamente'
        ];
    } else {
        throw new Exception('No se pudo eliminar el mensaje');
    }
}
?>