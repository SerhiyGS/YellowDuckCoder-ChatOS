<?php
// Отримання сирих даних від Trello
$input = file_get_contents('php://input');
// Розшифровування JSON даних
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
// Обробка події переміщення карток між колонками
if ($actionType === 'updateCard' && $listBefore && $listAfter) {
  if (($listBefore === 'InProgress' && $listAfter === 'Done') ||
    ($listBefore === 'Done' && $listAfter === 'InProgress')) {
    // Логіка обробки переміщення картки
    $cardName = $data['action']['data']['card']['name'] ?? 'Unknown Card';
    $boardName = $data['model']['name'] ?? 'Unknown Board';
    $message = "Карта '<b>{$cardName}</b>' перетягнута з '<b>{$listBefore}</b>' до '<b>{$listAfter}</b>' на дошці '<b>{$boardName}</b>'.\n";
  }
} elseif ($actionType === 'addMemberToBoard') {// Перевірка події додавання нового учасника до дошки
  $newMemberId = $data['action']['member']['id'] ?? 'Unknown ID';
  $newMemberUsername = $data['action']['member']['username'] ?? 'Unknown Username';
  $newMemberFullName = $data['action']['member']['fullName'] ?? 'Unknown Full Name';
  // Обробка нового учасника (наприклад, збереження інформації або надсилння повідомлення)
  $message = "New member added: ID - $newMemberId, Username - $newMemberUsername, Full Name - $newMemberFullName";
} else {
	//$message = "actionType = [".$actionType."]";
}
// Якщо є повідомлення, - воно записується в лог-файл
if (!empty($message)) {
  file_put_contents('./trello_log.txt', $message . PHP_EOL, FILE_APPEND);
}
// Повернення 200 OK для підтвердження отримання запиту
http_response_code(200);
?>
