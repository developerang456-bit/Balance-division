<?php
class Pay {
	public function run() {
		$payment = $this->getPaymentData();
		$pay_system = $this->checkSystem($payment);

		switch ($payment->command) {
			case 'check':
				return $this->checkAccount($payment->account);
    case 'pay':
    $this->validatePayment($payment);
    $result = $this->makePayment($payment, $pay_system);

    // считаем сумму
    if (is_array($result)) {
        $sum = 0;
        foreach ($result as $r) {
            if (is_array($r) && isset($r['amount'])) {
                $sum += (float)$r['amount'];
            }
        }
    } else {
        $sum = (float)$payment->sum;
    }

    $response = new stdClass();
    $response->success = true;
    $response->txn_id  = (string)$payment->txn_id;
    $response->sum     = number_format($sum, 2, '.', '');
    $response->comment = 'Платеж успешно сохранен в системе.';
    $response->result  = 0;

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
			case 'show_service':
				return $this->showService();
			case 'check_pay_status':
				return $this->checkPaymentStatus($payment);
			default:
				throw new PaymentException('Неверная команда');
		}
	}

	private function getPaymentData() {
		$input = file_get_contents('php://input');
		$payment = json_decode($input);
		if (empty($payment->token)) {
			throw new PaymentException('Не определено поле токена');
		}

		if (empty($payment->command)) {
			throw new PaymentException('Не определено поле команды');
		}

		return $payment;
	}
	private function checkSystem($payment) {

		$payment_token = $payment->token;
		$system_ip = $_SERVER['REMOTE_ADDR'];

		return $this->check_system($payment_token, $system_ip);
	}
	private function validatePayment($payment) {
		$this->validateProperty($payment, 'txn_id', 'Индентификатор платежа пустой');
		$this->validateProperty($payment, 'txn_date', 'Не определено поле даты');
		$this->validateProperty($payment, 'sum', 'Не определено поле суммы');
		$this->validateProperty($payment, 'service', 'Не определен тип оплачиваемой услуги');

		if (strtotime($payment->txn_date) === false) {
			throw new PaymentException("Неправильный формат даты");
		}
		if (empty($payment->account)) {
			throw new PaymentException('Пустой лицевой счет');
		}

	}
	private function validateProperty($payment, $property, $message) {
		if (empty($payment->$property)) {
			throw new PaymentException($message);
		}
	}
private function makePayment($payment, $pay_system) {
	global $dbConn;
		global $adb;

    if ($payment->service == '-') {
        $type_system = '';
    } else {
        $type_system = $this->getServiceType($payment->service);
    }

    $this->checkDuplicatePayment($payment->txn_id);

$data = $this->findContactBy($payment->account);

// 🔹 Берем только первую часть до двоеточия, если есть
$account_parts = explode(':', $payment->account);
$estate_number3 = trim($account_parts[0]);

$estate_data = $data['account20'];
// $account30 = $data['account30'];
$account30 = $estate_number3;


	// 1. Получаем все поля баланса и их категории
$balance_fields_query = "
               SELECT
                vbr.fieldname,
                pc.cf_paid_service as action_service
            FROM vtiger_balance_rules vbr
                LEFT JOIN vtiger_cf_paid_service pc ON pc.cf_paid_serviceid = vbr.action_service
            ORDER BY vbr.createdtime DESC
";
$balance_fields = $adb->run_query_allrecords($balance_fields_query);

// Создаем ассоциативный массив field => category
$field_categories = [];
$balance_fieldnames = [];
foreach ($balance_fields as $row) {
    $field_categories[$row['fieldname']] = $row['action_service'];
    $balance_fieldnames[] = $row['fieldname'];
}

// 2. Получаем значения балансов для конкретного объекта
$fields_str = implode(', ', $balance_fieldnames); // создаем список полей для SELECT
$res_invoice = "
    SELECT $fields_str 
    FROM vtiger_estates ve
    INNER JOIN vtiger_crmentity vc ON vc.crmid = ve.estatesid
    WHERE vc.deleted = 0 AND ve.estate_number = '$estate_number3'
";
$invoice_info = $adb->run_query_allrecords($res_invoice)[0];

// 3. Обрабатываем суммы и записываем в массив $balances с категориями
$balances = [];
$total_balance = 0;

foreach ($balance_fieldnames as $field) {
    $value = isset($invoice_info[$field]) ? (float)$invoice_info[$field] : 0;

    // Если меньше 0, устанавливаем 0
    if ($value < 0) {
        $value = 0;
    }

    // Записываем значение и категорию в массив
    $balances[$field] = [
        'value' => $value,
        'category' => $field_categories[$field] ?? ''
    ];

    // Суммируем только 0 и выше
    $total_balance += $value;
}

if ($type_system === 'Общая услуга') {

    // Используем наши динамические $balances и $total_balance
    // Преобразуем для совместимости с логикой пропорций: field => value
    $simpleBalances = [];
    $balanceTypes = [];
    foreach ($balances as $field => $data) {
        $simpleBalances[$field] = $data['value'];
        $balanceTypes[$field] = $data['category'];
    }

    $totalBalance = array_sum($simpleBalances); // сумма всех положительных балансов
    $paymentSum = $payment->sum;
    $payments = [];

    // активные балансы (где сумма > 0)
    $activeBalances = array_filter($simpleBalances, fn($b) => $b > 0);
    $activeCount = count($activeBalances);

    // если сумма меньше общей — делим пропорционально
    if ($paymentSum < $totalBalance && $activeCount > 0) {
        foreach ($activeBalances as $key => $b) {
            $proportion = $b / $totalBalance;
            $share = round($paymentSum * $proportion, 2);

            if ($share > 0) {
                $currentType = $balanceTypes[$key];
                $comment = 'Сумма балансов ' . $totalBalance . ' сумма Общего платежа ' . $paymentSum;

                $partialData = $this->preparePaymentData(
                    (object)[
                        'txn_date' => $payment->txn_date,
                        'sum' => $share,
                        'txn_id' => $payment->txn_id,
                        'service' => $payment->service,
                        'cf_comment' => $comment,
                        'account' => $payment->account
                    ],
                    $pay_system,
                    $currentType,
                    $estate_data,
                    $account30
                );

                $payments[] = $this->processPayment($partialData);
            }
        }

        return $payments;
    }

    // если сумма больше или равна
    $extra = $paymentSum - $totalBalance;

    if ($extra > 0 && $activeCount > 0) {
        $addPerBalance = round($extra / $activeCount, 2);
        foreach ($activeBalances as $key => $b) {
            $simpleBalances[$key] = $b + $addPerBalance;
        }
    }

    // создаем платежи
    foreach ($simpleBalances as $key => $b) {
        if ($b > 0) {
            $currentType = $balanceTypes[$key];
            $comment = 'Сумма балансов ' . $totalBalance . ' сумма Общего платежа ' . $paymentSum;

            $partialData = $this->preparePaymentData(
                (object)[
                    'txn_date' => $payment->txn_date,
                    'sum' => $b,
                    'txn_id' => $payment->txn_id,
                    'service' => $payment->service,
                    'cf_comment' => $comment,
                    'account' => $payment->account
                ],
                $pay_system,
                $currentType,
                $estate_data,
                $account30
            );

            $payments[] = $this->processPayment($partialData);
        }
    }

    return $payments;
}



// логика обычной услуги
$data = $this->preparePaymentData($payment, $pay_system, $type_system, $estate_data, $account30);
return $this->processPayment($data);



    // обычная услуга
    $data = $this->preparePaymentData($payment, $pay_system, $type_system, $estate_data, $account30);
    return $this->processPayment($data);
}
	private function getServiceType($service_id) {
		global $dbConn;
		$sql = "SELECT cf_paid_service FROM vtiger_cf_paid_service WHERE cf_paid_serviceid = ?";
		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException("Ошибка подготовки запроса: " . $dbConn->error);
		}
		$stmt->bind_param("i", $service_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows === 0) {
			throw new PaymentException("Указанная услуга не найдена");
		}
		$row = $result->fetch_assoc();
		return $row['cf_paid_service'];
	}

	private function checkDuplicatePayment($txn_id) {
		global $dbConn;
		$sql = "SELECT p.cf_txnid FROM vtiger_payments p
            INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
            WHERE vc.deleted = 0 AND p.cf_txnid = ?";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}
		$stmt->bind_param('s', $txn_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows > 0) {
			throw new PaymentException('В системе уже есть оплата с данным идентификатором платежа. Txn_id - ' . $txn_id);
		}
		$stmt->close();
	}

	private function preparePaymentData($payment, $pay_system, $type_system, $estate_data, $account30) {
		global $adb;



	     $result = $adb->pquery("SELECT smownerid FROM vtiger_crmentity WHERE crmid = ?", array($estate_data['estatesid']));

		// Проверка на наличие данных
		if ($adb->num_rows($result) === 0) {
			$smownerid = 12; // Значение по умолчанию
		} else {
			$row = $adb->fetch_array($result);
			$smownerid = $row['smownerid'];
		}


	
		return array(
    "assigned_user_id" => 1,
    "cf_payment_source" => $pay_system['payer_title'],
    "cf_pay_date" => $payment->txn_date,
    "amount" => $payment->sum,
    "cf_txnid" => $payment->txn_id,
    "cf_paid_service" => $type_system,
    "cf_invoice_id" => $account30,
    "cf_paid_object" => $estate_data['estatesid'],
    "cf_comment" => isset($payment->cf_comment) ? $payment->cf_comment : ''
       );

	}
	private function processPayment($data) {
		global $CRM;
		return $CRM->createPayment($data);
	}
public function findContactBy($accountNumber) {
	require_once 'MyLogger.php';
	$logger = new MyLogger('payment_services/oimo/payments.log');
	global $dbConn;

	if (empty($accountNumber)) {
		throw new PaymentException('Пустой лицевой счет');
	}

	// 🔹 Разделение лицевого счёта на основную часть и добавочную
	if (strpos($accountNumber, ':') !== false) {
		list($account20, $account30) = explode(':', $accountNumber, 2);
		
	} else {
		$account20 = $accountNumber;
		$account30 = null;
	}


	$sql = "SELECT es.estatesid FROM vtiger_estates es
		INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
		LEFT JOIN vtiger_contactdetails cd ON es.cf_contact_id = cd.contactid 
		WHERE vc.deleted = 0
		AND es.estate_number = ?";

	$stmt = $dbConn->prepare($sql);
	if (!$stmt) {
		throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
	}

	$stmt->bind_param("s", $account20); // 🔹 Используем только account20
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows == 0) {
		$stmt->close();
		throw new PaymentException('Абонента с данным лицевым счетом не существует. ЛС - ' . $account20);
	} elseif ($result->num_rows > 1) {
		$stmt->close();
		throw new PaymentException('В системе оказалось больше одного абонента с данным лицевым счетом. ЛС - ' . $account20);
	}

	$row = $result->fetch_assoc();
	$stmt->close();



	return [
    'account20' => $row,       // всё, что вернула БД (например, estatesid и т.д.)
    'account30' => $account30  // то, что было после ":", если есть
];

}


	private function check_system($payment_token, $system_ip) {
		require_once 'MyLogger.php';
		$logger = new MyLogger('payment_services/oimo/payments.log');
		global $dbConn;

		$sql = "SELECT payer_title FROM vtiger_pymentssystem vp 
            INNER JOIN vtiger_crmentity vc ON vp.pymentssystemid = vc.crmid 
            WHERE vc.deleted = 0 AND cf_payer_token = ? AND ? IN (vp.cf_payer_ip_1, vp.cf_payer_ip_2, vp.cf_payer_ip_3, '77.235.30.61')";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}
		$stmt->bind_param("ss", $payment_token, $system_ip);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			throw new DatabaseException("Не зарегистрированная платежная система IP $system_ip, Token $payment_token");
		} elseif ($result->num_rows > 1) {
			throw new DatabaseException('В системе оказалось больше одной платежной системы с токеном - ' . $payment_token . 'IP - ' . $system_ip);
		}
		$data = $result->fetch_assoc();
		$stmt->close();
		return $data;
	}

	private function showService() {
		global $dbConn;

		$sql = "SELECT cf_paid_serviceid as id, cf_paid_service as service  FROM vtiger_cf_paid_service";

		$result = $dbConn->query($sql);

		if ($result) {

			if ($result->num_rows == 0) {
				throw new PaymentException('Услуги отсутствуют');
			} else {
				$rows = array();
				while ($row = $result->fetch_assoc()) {
					$rows[] = $row;
				}
				return $rows;
			}
		} else {
			throw new DatabaseException('При поиске услуг произошла ошибка. Ошибка MySQL - ' . $dbConn->error);
		}
	}
	private function checkAccount($accountNumber) {
		global $dbConn;

		if (empty($accountNumber)) {
			throw new PaymentException('Пустой лицевой счет');
		}

			// 🔹 Разделение лицевого счёта на основную часть и добавочную
	if (strpos($accountNumber, ':') !== false) {
		list($account20, $account30) = explode(':', $accountNumber, 2);
		
	} else {
		$account20 = $accountNumber;
		$account30 = null;
	}


	

		$sql = "SELECT cd.lastname, 
            es.cf_balance as debt, 
            es.cf_streets as street, 
            es.cf_house_number as house_number 
            FROM vtiger_estates es
            INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
            LEFT JOIN vtiger_contactdetails cd ON es.cf_contact_id = cd.contactid 
            WHERE vc.deleted = 0
            AND es.estate_number = ?";

		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException('Ошибка подготовки запроса: ' . $dbConn->error);
		}

		$stmt->bind_param("s", $account20);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			$stmt->close();
			throw new PaymentException('Абонента с данным лицевым счетом не существует. ЛС - ' . $accountNumber);
		} elseif ($result->num_rows > 1) {
			$stmt->close();
			throw new PaymentException('В системе оказалось больше одного абонента с данным лицевым счетом. ЛС - ' . $accountNumber);
		}

		$row = $result->fetch_assoc();
		$stmt->close();
		$lastname = $row['lastname'];
		$debt = round($row['debt'], 2);
		if ($debt < 0) {
			$lastname .= ': Переплата ' . abs($debt) . ' сом';
		} elseif ($debt > 0) {
			$lastname .= ': Задолженность ' . $debt . ' сом';
		}

		return $lastname;
	}

	public function checkPaymentStatus($payment) {
		global $dbConn;

		if (empty($payment->txn_id)) {
			throw new PaymentException('Индентификатор платежа пустой');
		}
		$sql = "SELECT p.amount, 
				p.cf_status, 
				es.estate_number, 
				cd.lastname, 
				p.cf_pay_date,
				p.cf_txnid
				FROM vtiger_payments p
				INNER JOIN vtiger_estates es ON p.cf_paid_object = es.estatesid 
				INNER JOIN vtiger_contactdetails cd ON cd.contactid = es.cf_contact_id 
				INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
				WHERE vc.deleted = 0
                AND p.cf_txnid = ?";
		$stmt = $dbConn->prepare($sql);
		if (!$stmt) {
			throw new DatabaseException("Ошибка подготовки запроса: " . $dbConn->error);
		}
		$stmt->bind_param("s", $payment->txn_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows == 0) {
			throw new PaymentException('Оплата с данным идентификатором платежа не найдена. Txn_id - ' . $payment->txn_id);
		} elseif ($result->num_rows > 1) {
		$data = [];
$totalAmount = 0;
$latestDate = null;
$lastname = '';
$estate_number = '';
$cf_txnid = $payment->txn_id;

while ($row = $result->fetch_assoc()) {
    $totalAmount += $row['amount'];
    if (!$latestDate || strtotime($row['cf_pay_date']) > strtotime($latestDate)) {
        $latestDate = $row['cf_pay_date'];
    }
    $lastname = $row['lastname']; // можно оставить любой или объединить
    $estate_number = $row['estate_number'];
}

$stmt->close();

$totalAmount = round($totalAmount, 2);

$data = [
    'amount' => $totalAmount,
    'comment' => 'Платеж с данным идентификатором сохранен в системе.'
];

return $data;

		}
		$data = $result->fetch_assoc();
		$stmt->close();

		$data['action'] = "check_pay_status";
		$data['comment'] = "Платеж с данным идентификатором сохранен в системе.";

		return $data;

	}

}
