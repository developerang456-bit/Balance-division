<?php
date_default_timezone_set('Asia/Bishkek');
require_once 'DataBase.php';
require_once 'MyLogger.php';
require_once 'Pay.php';
require_once 'CRM.php';

$logger = new MyLogger('payment_services/oimo/payments.log');
// $logger->log($_SERVER['REQUEST_METHOD']);
// $logger->log($_SERVER['CONTENT_TYPE']);
header('Content-Type: application/json; charset=utf-8');

class DatabaseException extends Exception {
}
class PaymentException extends Exception {
}
try {
	$dbConn = DataBase::getConn();
	$pay = new Pay();
	$CRM = new CRM();

	$response = $pay->run();

	if (is_array($response)) {
		$a = $response['amount'];
		$amount = sprintf("%.2f", $a);
		if ($response['action'] == 'pay') {
			$logger->log($response['command'] . " сумма " . $amount . " номер платежа " . $response['cf_txnid']);
			print json_encode(array('success' => true, 'txn_id' => $response['cf_txnid'], 'sum' => $amount, 'comment' => $response['command'], 'result' => 0));
		} elseif ($response['action'] == 'check_pay_status') {
			// print json_encode($response);
			print json_encode(array('success' => true, 'txn_id' => $response['cf_txnid'], 'sum' => $amount, 'comment' => $response['comment'], 'result' => 0));
		} else {
			print json_encode(array('success' => true, 'comment' => $response, 'result' => 0));
		}
	} else {
		$logger->log($response);
		print json_encode(array('success' => true, 'comment' => $response, 'result' => 0));
	}
} catch (Exception $e) {
	handleException($e);
} finally {
	$dbConn->close();
}

function handleException($e) {
	global $logger;
	$logger->log($e->getMessage());
	$message = 'Произошла ошибка. Обратитесь в службу поддержки.';

	if ($e instanceof PaymentException && strpos($e, 'Абонента с данным лицевым счетом не существует')) {
		$message = 'Абонент не найден';
		$errorCode = 1;
	} elseif ($e instanceof PaymentException && strpos($e, 'Пустой лицевой счет')) {
		$message = 'Пустой лицевой счет';
		$errorCode = 2;
	} elseif ($e instanceof PaymentException && strpos($e, 'В системе уже есть оплата с данным идентификатором платежа.')) {
		$message = 'В системе уже есть оплата с данным идентификатором платежа';
		$errorCode = 3;
	} elseif ($e instanceof PaymentException && strpos($e, 'Не определено поле команды')) {
		$message = 'Не определено поле команды';
		$errorCode = 4;
	} elseif ($e instanceof PaymentException && strpos($e, 'Не определено поле токена')) {
		$message = 'Не определено поле токена';
		$errorCode = 5;
	} elseif ($e instanceof PaymentException && strpos($e, 'Индентификатор платежа пустой')) {
		$message = 'Индентификатор платежа пустой';
		$errorCode = 6;
	} elseif ($e instanceof PaymentException && strpos($e, 'Не определено поле даты')) {
		$message = 'Не определено поле даты';
		$errorCode = 7;
	} elseif ($e instanceof PaymentException && strpos($e, 'Не определено поле суммы')) {
		$message = 'Не определено поле суммы';
		$errorCode = 8;
	} elseif ($e instanceof PaymentException && strpos($e, 'Не определен тип оплачиваемой услуги')) {
		$message = 'Не определен тип оплачиваемой услуги';
		$errorCode = 9;
	} elseif ($e instanceof PaymentException && strpos($e, 'Неправильный формат даты')) {
		$message = 'Неправильный формат даты';
		$errorCode = 10;
	} elseif ($e instanceof PaymentException && strpos($e, 'Неверная команда')) {
		$message = 'Неверная команда';
		$errorCode = 11;
	} elseif ($e instanceof PaymentException && strpos($e, 'Указанная услуга не найдена')) {
		$message = 'Указанная услуга не найдена';
		$errorCode = 12;
	} elseif ($e instanceof PaymentException && strpos($e, 'Услуги отсутствуют')) {
		$message = 'Услуги отсутствуют';
		$errorCode = 13;
	} elseif ($e instanceof PaymentException && strpos($e, 'В системе оказалось больше одного абонента с данным лицевым счетом')) {
		$message = 'В системе оказалось больше одного абонента с данным лицевым счетом';
		$errorCode = 14;
	} elseif ($e instanceof PaymentException && strpos($e, 'Оплата с данным идентификатором платежа не найдена')) {
		$message = 'Оплата с данным идентификатором платежа не найдена';
		$errorCode = 15;
	} elseif ($e instanceof PaymentException && strpos($e, 'Обнаружено более одного платежа с таким же идентификатором')) {
		$message = 'Обнаружено более одного платежа с таким же идентификатором';
		$errorCode = 16;
	} elseif ($e instanceof DatabaseException) {
		$message = 'Произошла ошибка. Обратитесь в службу поддержки.';
		$errorCode = 500;
	}
	print json_encode([
		'success' => false,
		'message' => $message,
		'result' => $errorCode
	]);
}

