<?php
// Отримуємо сирі дані від Trello
$input = file_get_contents('php://input');

// Розшифровуємо JSON дані
$data = json_decode($input, true);

// Перевірка на помилки під час декодування JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Неправильний формат JSON
		file_put_contents('./trello_log.txt', 'Invalid JSON' . PHP_EOL, FILE_APPEND);		
    exit('Invalid JSON');
}

// Перевірка типу події
$actionType = $data['action']['type'] ?? '';
$listBefore = $data['action']['data']['listBefore']['name'] ?? '';
$listAfter = $data['action']['data']['listAfter']['name'] ?? '';
$message = '';

// Фільтрування події переміщення карток між колонками "InProgress" та "Done"
if ($actionType === 'updateCard' && $listBefore && $listAfter) {
  if (($listBefore === 'InProgress' && $listAfter === 'Done') ||
    ($listBefore === 'Done' && $listAfter === 'InProgress')) {
    // Логіка обробки переміщення картки
    $cardName = $data['action']['data']['card']['name'] ?? 'Unknown Card';
    $boardName = $data['model']['name'] ?? 'Unknown Board';
    $message = "Card '{$cardName}' moved from '{$listBefore}' to '{$listAfter}' on board '{$boardName}'.";
  }
} else {
    // Інші типи подій можна ігнорувати або обробляти додатково
   //$message = 'Ignored event type.';
}
file_put_contents('./trello_log.txt', $message . PHP_EOL, FILE_APPEND);
// Повернення 200 OK для підтвердження отримання запиту
http_response_code(200);
?>
